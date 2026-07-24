<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\MessageLog;
use App\Models\MessagingUsage;
use App\Models\ScheduledMessage;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What Haraan actually sends, and what it costs — the read side of the messaging
 * ledger (see the MessageMeter service).
 *
 * Two jobs. First, sizing: partner plans will sell conversation quotas, and this
 * is where the volumes to price them against come from. Second, and more useful
 * day to day, it surfaces the sends that never left the building — a disabled
 * channel or an unapproved sender means tickets silently stop arriving, and until
 * now the only trace was a line in the log nobody reads.
 *
 * Super-admins only, read-only, no polling (this is a ledger, not a live probe).
 */
class MessagingUsagePage extends Page
{
    protected string $view = 'filament.pages.messaging-usage';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $title = 'Messaging';

    protected static ?string $navigationLabel = 'Messaging';

    protected static ?int $navigationSort = 91;

    /** Months back from the current one: 0 = this month, 1 = last month. */
    public int $monthsBack = 0;

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function showMonth(int $monthsBack): void
    {
        $this->monthsBack = max(0, min(11, $monthsBack));
    }

    private function periodStart(): Carbon
    {
        return Carbon::now()->startOfMonth()->subMonths($this->monthsBack);
    }

    public function periodLabel(): string
    {
        return $this->periodStart()->format('F Y');
    }

    /**
     * Platform totals for the period.
     *
     * @return array<string, mixed>
     */
    public function getTotals(): array
    {
        $rows = MessagingUsage::query()
            ->whereDate('period_start', $this->periodStart()->toDateString())
            ->get();

        $sent = (int) $rows->sum('messages_sent');
        $failed = (int) $rows->sum('messages_failed');
        $attempted = $sent + $failed;

        return [
            'conversations' => (int) $rows->sum('conversations_opened'),
            'sent' => $sent,
            'failed' => $failed,
            // The number to actually watch: anything above a few percent means
            // customers aren't getting their tickets.
            'failureRate' => $attempted > 0 ? round($failed / $attempted * 100, 1) : 0.0,
            'byChannel' => $rows->groupBy('channel')->map(fn ($g): array => [
                'sent' => (int) $g->sum('messages_sent'),
                'failed' => (int) $g->sum('messages_failed'),
                'conversations' => (int) $g->sum('conversations_opened'),
            ])->all(),
        ];
    }

    /**
     * Per-partner volumes, heaviest first — the raw material for sizing plan tiers.
     * The null-partner row is Haraan's own traffic (login OTPs) and is labelled as
     * such rather than hidden, since it's a real cost.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPartnerRows(): array
    {
        return MessagingUsage::query()
            ->with('partner:id,name')
            ->whereDate('period_start', $this->periodStart()->toDateString())
            ->get()
            ->groupBy(fn (MessagingUsage $u): string => (string) ($u->partner_id ?? 'platform'))
            ->map(function ($group) {
                $sent = (int) $group->sum('messages_sent');
                $failed = (int) $group->sum('messages_failed');
                $attempted = $sent + $failed;

                return [
                    'name' => $group->first()->partner?->name ?? 'Haraan (platform)',
                    'isPlatform' => $group->first()->partner_id === null,
                    'conversations' => (int) $group->sum('conversations_opened'),
                    'sent' => $sent,
                    'failed' => $failed,
                    'failureRate' => $attempted > 0 ? round($failed / $attempted * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('conversations')
            ->values()
            ->all();
    }

    /**
     * The most recent sends that didn't reach anyone, newest first — the alert
     * this page exists for.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentFailures(): array
    {
        return MessageLog::query()
            ->with('partner:id,name')
            ->where('status', '!=', MessageLog::STATUS_SENT)
            ->where('created_at', '>=', $this->periodStart())
            ->where('created_at', '<', $this->periodStart()->copy()->addMonth())
            ->latest('id')
            ->limit(25)
            ->get()
            ->map(fn (MessageLog $l): array => [
                'channel' => $l->channel,
                'status' => $l->status,
                'recipient' => $this->maskRecipient($l->recipient),
                'partner' => $l->partner?->name ?? 'Haraan',
                'template' => $l->template_key,
                'error' => $l->error !== null ? mb_substr($l->error, 0, 140) : null,
                'when' => $l->created_at?->diffForHumans(),
            ])
            ->all();
    }

    /**
     * A count of each non-delivery reason for the period. 'disabled' or
     * 'unconfigured' dominating means a config problem, not a provider one —
     * a distinction worth reading at a glance before opening the log.
     *
     * @return array<string, int>
     */
    public function getFailureReasons(): array
    {
        return MessageLog::query()
            ->where('status', '!=', MessageLog::STATUS_SENT)
            ->where('created_at', '>=', $this->periodStart())
            ->where('created_at', '<', $this->periodStart()->copy()->addMonth())
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($n): int => (int) $n)
            ->all();
    }

    /**
     * State of the outbound journey queue (reminders + review requests).
     *
     * `enabled` is surfaced first on purpose: with the master switch off the queue
     * fills and holds, which looks identical to "broken" unless you're told.
     *
     * @return array{enabled: bool, counts: array<string, int>, upcoming: array<int, array<string, mixed>>, skips: array<string, int>}
     */
    public function getJourneys(): array
    {
        $counts = ScheduledMessage::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($n): int => (int) $n)
            ->all();

        $upcoming = ScheduledMessage::query()
            ->with('partner:id,name')
            ->where('status', ScheduledMessage::STATUS_PENDING)
            ->orderBy('send_after')
            ->limit(10)
            ->get()
            ->map(fn (ScheduledMessage $m): array => [
                'template' => $m->template_key,
                'partner' => $m->partner?->name ?? 'Haraan',
                'recipient' => $this->maskRecipient($m->recipient),
                'when' => $m->send_after?->diffForHumans(),
                'due' => $m->send_after?->isPast() ?? false,
            ])
            ->all();

        $skips = ScheduledMessage::query()
            ->whereNotNull('skip_reason')
            ->select('skip_reason', DB::raw('count(*) as total'))
            ->groupBy('skip_reason')
            ->pluck('total', 'skip_reason')
            ->map(fn ($n): int => (int) $n)
            ->all();

        return [
            'enabled' => (bool) config('messaging.journeys.enabled', false),
            'counts' => $counts,
            'upcoming' => $upcoming,
            'skips' => $skips,
        ];
    }

    /** Keep the last few digits only — enough to identify a number, not to reuse it. */
    private function maskRecipient(string $recipient): string
    {
        $len = mb_strlen($recipient);

        return $len <= 4 ? $recipient : str_repeat('•', min(6, $len - 4)) . mb_substr($recipient, -4);
    }
}
