<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Booking;
use App\Models\Event;
use App\Models\Payout;
use App\Models\SupportThread;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

/**
 * Command Center — the money-first, action-first home for operators. Three bands:
 *   1. Money       — GMV, net (after refunds), refunds, discounts, payouts owed/settled, avg order.
 *   2. Payments    — success / refund / failed / pending health of the payment funnel.
 *   3. Radar       — "needs attention today": near-sold-out & zero-sale events, pending payouts,
 *                    open support threads, failed payments — each a deep link into the panel.
 *
 * Read-only. Refreshes live on Reverb content.updated (RefreshesOnContentUpdate) and polls as a
 * fallback. All revenue reads use lower(status) because booking/payout statuses are mixed-case.
 */
class CommandCenter extends Page
{
    protected string $view = 'filament.pages.command-center';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-command-line';

    protected static ?string $title = 'Command Center';

    protected static ?string $navigationLabel = 'Command Center';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?int $navigationSort = -10;

    /** Booking statuses that represent money actually collected (case-insensitive). */
    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    private const LOST = ['cancelled', 'canceled', 'refunded', 'failed'];

    /** @var array<string,mixed> Assembled once per render; the view reads these. */
    public array $money = [];

    public array $health = [];

    public array $radar = [];

    public ?string $range = '30d';

    public static function canAccess(): bool
    {
        $u = auth()->user();

        return (bool) ($u?->isSuperAdmin() || $u?->canManage('finance') || $u?->canManage('events'));
    }

    public function mount(): void
    {
        $this->build();
    }

    /** Re-assemble on poll / Reverb signal / range change. */
    public function build(): void
    {
        $since = match ($this->range) {
            '7d' => now()->subDays(7),
            '90d' => now()->subDays(90),
            'all' => null,
            default => now()->subDays(30),
        };
        $prevSince = $since ? $since->copy()->sub($since->diffAsCarbonInterval(now())) : null;

        $this->money = $this->buildMoney($since, $prevSince);
        $this->health = $this->buildHealth($since);
        $this->radar = $this->buildRadar();
    }

    public function setRange(string $range): void
    {
        $this->range = in_array($range, ['7d', '30d', '90d', 'all'], true) ? $range : '30d';
        $this->build();
    }

    /** Reverb push: a content.updated broadcast (via the panel realtime bridge) rebuilds live. */
    #[On('haraan-content-updated')]
    public function onContentUpdated(): void
    {
        $this->build();
    }

    // ---------------------------------------------------------------------

    /** @return array<string,mixed> */
    private function buildMoney(?Carbon $since, ?Carbon $prevSince): array
    {
        $paidQ = fn () => Booking::query()->whereIn(DB::raw('lower(status)'), self::PAID);

        $scope = function ($q) use ($since) {
            return $since ? $q->where('created_at', '>=', $since) : $q;
        };

        $gmv = (float) $scope($paidQ())->sum('total_amount');
        $discounts = (float) $scope($paidQ())->sum('discount');
        $refunds = (float) $scope(Booking::query()->whereRaw('lower(status) = ?', ['refunded']))->sum('total_amount');
        $net = $gmv - $refunds;
        $paidCount = (int) $scope($paidQ())->count();
        $avg = $paidCount > 0 ? $gmv / $paidCount : 0.0;

        // Prior window for the GMV trend arrow.
        $prevGmv = ($since && $prevSince)
            ? (float) $paidQ()->whereBetween('created_at', [$prevSince, $since])->sum('total_amount')
            : 0.0;

        // Payouts (partner settlements) — reuse the Finance status convention.
        $pendingPayouts = (float) Payout::whereRaw('lower(status) = ?', ['pending'])->sum('amount');
        $pendingPayoutCt = (int) Payout::whereRaw('lower(status) = ?', ['pending'])->count();
        $settled = (float) Payout::whereRaw('lower(status) = ?', ['processed'])->sum('amount');

        return [
            'hero' => [
                'gmv' => $gmv,
                'net' => $net,
                'trend' => $this->trend($gmv, $prevGmv),
                'paidCount' => $paidCount,
                'rangeLabel' => $this->rangeLabel(),
            ],
            'cards' => [
                $this->m('Net revenue', $net, 'after ₹' . $this->money0($refunds) . ' refunds', 'heroicon-o-banknotes', 'ok'),
                $this->m('Discounts given', $discounts, 'coupons + offers', 'heroicon-o-tag', $discounts > 0 ? 'warn' : 'idle'),
                $this->m('Refunds', $refunds, 'returned to customers', 'heroicon-o-arrow-uturn-left', $refunds > 0 ? 'warn' : 'ok'),
                $this->m('Avg order', $avg, $paidCount . ' paid bookings', 'heroicon-o-shopping-bag', 'ok'),
                $this->m('Payouts owed', $pendingPayouts, $pendingPayoutCt . ' partners awaiting', 'heroicon-o-clock', $pendingPayoutCt > 0 ? 'warn' : 'ok'),
                $this->m('Settled to partners', $settled, 'processed', 'heroicon-o-check-badge', 'ok'),
            ],
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function buildHealth(?Carbon $since): array
    {
        $base = Booking::query();
        if ($since) {
            $base->where('created_at', '>=', $since);
        }
        $rows = (clone $base)
            ->selectRaw('lower(status) as s, count(*) as c')
            ->groupBy('s')
            ->pluck('c', 's');

        $sum = fn (array $keys) => (int) collect($keys)->sum(fn ($k) => (int) ($rows[$k] ?? 0));

        $paid = $sum(self::PAID);
        $refunded = (int) ($rows['refunded'] ?? 0);
        $failed = (int) ($rows['failed'] ?? 0);
        $pending = (int) ($rows['pending'] ?? 0) + (int) ($rows['reserved'] ?? 0);
        $cancelled = (int) ($rows['cancelled'] ?? 0) + (int) ($rows['canceled'] ?? 0);
        $total = (int) $rows->sum();

        $successRate = $total > 0 ? round($paid / $total * 100) : 0;
        $refundRate = $paid > 0 ? round($refunded / max(1, $paid) * 100, 1) : 0.0;

        return [
            $this->h('Success rate', $successRate . '%', $paid . ' of ' . $total . ' orders paid', 'heroicon-o-check-circle', $successRate >= 70 ? 'ok' : ($successRate >= 40 ? 'warn' : 'down'), (int) $successRate),
            $this->h('Refund rate', $refundRate . '%', $refunded . ' refunded', 'heroicon-o-arrow-uturn-left', $refundRate <= 5 ? 'ok' : ($refundRate <= 15 ? 'warn' : 'down'), (int) min(100, $refundRate)),
            $this->h('Failed payments', (string) $failed, 'gateway declines / errors', 'heroicon-o-x-circle', $failed === 0 ? 'ok' : ($failed <= 5 ? 'warn' : 'down'), null),
            $this->h('Pending / holds', (string) $pending, 'awaiting payment', 'heroicon-o-clock', $pending === 0 ? 'ok' : 'warn', null),
            $this->h('Cancelled', (string) $cancelled, 'by user or admin', 'heroicon-o-no-symbol', 'idle', null),
        ];
    }

    /** @return array<int,array<string,mixed>> "Needs attention" actionable items. */
    private function buildRadar(): array
    {
        $items = [];
        $today = now()->startOfDay();

        // Near sold-out upcoming events (>=85% of slots gone, still some left).
        $nearSoldOut = Event::query()
            ->whereDate('date', '>=', $today)
            ->whereNotNull('total_slots')->where('total_slots', '>', 0)
            ->whereColumn('available_slots', '<=', DB::raw('total_slots * 0.15'))
            ->where('available_slots', '>', 0)
            ->count();
        $items[] = $this->r('Near sold-out', $nearSoldOut, 'upcoming events ≥85% gone — raise price / add slots', 'heroicon-o-fire', $nearSoldOut > 0 ? 'warn' : 'ok', 'control/events/events');

        // Upcoming events with zero paid bookings.
        $paidEventIds = Booking::query()->whereIn(DB::raw('lower(status)'), self::PAID)
            ->whereNotNull('event_id')->distinct()->pluck('event_id');
        $zeroSales = Event::query()->whereDate('date', '>=', $today)
            ->whereNotIn('id', $paidEventIds)->count();
        $items[] = $this->r('Zero sales', $zeroSales, 'upcoming events with no bookings — needs a boost', 'heroicon-o-megaphone', $zeroSales > 0 ? 'warn' : 'ok', 'control/events/events');

        // Pending payouts.
        $pendingPayoutCt = (int) Payout::whereRaw('lower(status) = ?', ['pending'])->count();
        $items[] = $this->r('Pending payouts', $pendingPayoutCt, 'partners awaiting settlement', 'heroicon-o-banknotes', $pendingPayoutCt > 0 ? 'warn' : 'ok', 'control/finance/payouts');

        // Open support threads (anything not closed).
        $openSupport = (int) SupportThread::query()->where('status', '!=', 'closed')->count();
        $items[] = $this->r('Open support', $openSupport, 'conversations needing a reply', 'heroicon-o-chat-bubble-left-right', $openSupport > 0 ? 'warn' : 'ok', 'control/support-threads');

        // Failed payments to review.
        $failed = (int) Booking::query()->whereRaw('lower(status) = ?', ['failed'])->count();
        $items[] = $this->r('Failed payments', $failed, 'declined orders to review', 'heroicon-o-exclamation-triangle', $failed > 0 ? ($failed > 10 ? 'down' : 'warn') : 'ok', 'control/events/bookings');

        return $items;
    }

    // --------------------------- shapers ---------------------------------

    /** @return array<string,mixed> money card (value is money, formatted in the view) */
    private function m(string $title, float $value, string $sub, string $icon, string $status): array
    {
        return ['title' => $title, 'value' => '₹' . $this->money0($value), 'sub' => $sub, 'icon' => $icon, 'status' => $status];
    }

    /** @return array<string,mixed> health card */
    private function h(string $title, string $value, string $sub, string $icon, string $status, ?int $meter): array
    {
        return compact('title', 'value', 'sub', 'icon', 'status', 'meter');
    }

    /** @return array<string,mixed> radar item */
    private function r(string $title, int $count, string $sub, string $icon, string $status, string $path): array
    {
        return [
            'title' => $title,
            'count' => $count,
            'sub' => $sub,
            'icon' => $icon,
            'status' => $count > 0 ? $status : 'ok',
            'url' => url($path),
        ];
    }

    /**
     * @return array{0:string,1:string} [label, direction ok|down|flat]
     */
    private function trend(float $current, float $previous): array
    {
        if ($previous <= 0) {
            return $current > 0 ? ['New', 'ok'] : ['—', 'flat'];
        }
        $pct = (int) round((($current - $previous) / $previous) * 100);

        return $pct > 0 ? ['+' . $pct . '%', 'ok'] : ($pct < 0 ? [$pct . '%', 'down'] : ['0%', 'flat']);
    }

    private function money0(float $amount): string
    {
        return number_format($amount);
    }

    private function rangeLabel(): string
    {
        return match ($this->range) {
            '7d' => 'last 7 days',
            '90d' => 'last 90 days',
            'all' => 'all time',
            default => 'last 30 days',
        };
    }
}
