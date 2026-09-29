<?php

declare(strict_types=1);

namespace App\Filament\Clusters\GameHub\Pages;

use App\Filament\Clusters\GameHub\Concerns\SummarisesVenues;
use App\Filament\Clusters\GameHub\GameHubCluster;
use App\Models\MessageLog;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;

/**
 * What the platform actually tried to send (WhatsApp, SMS, email, push) and
 * what happened to it — read from message_log, which records every attempt,
 * including the ones that never left because a channel was off or unset.
 * That's the useful part: a quiet week of failed tickets shows up here.
 */
class GameHubNotifications extends Page
{
    use SummarisesVenues;

    protected static ?string $cluster = GameHubCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $title = 'Messages sent';

    protected static ?string $navigationLabel = 'Notifications';

    protected static ?int $navigationSort = 13;

    protected string $view = 'filament.clusters.game-hub.summary-page';

    private const CHANNEL_NAMES = ['whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'email' => 'Email', 'push' => 'App push', 'fcm' => 'App push', 'instagram' => 'Instagram'];

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('gamehub') ?? false;
    }

    private function logs(): Builder
    {
        $q = MessageLog::query()->where('created_at', '>=', now()->subDays(7));

        return ($id = static::partnerId()) !== null ? $q->where('partner_id', $id) : $q;
    }

    public function getPanels(): array
    {
        $out = fn () => $this->logs()->where('direction', '!=', 'in');

        $sent = $out()->where('status', MessageLog::STATUS_SENT)->count();
        $failed = $out()->where('status', MessageLog::STATUS_FAILED)->count();
        $notSent = $out()->whereIn('status', [MessageLog::STATUS_DISABLED, MessageLog::STATUS_UNCONFIGURED, MessageLog::STATUS_UNROUTABLE])->count();
        $received = $this->logs()->where('status', MessageLog::STATUS_RECEIVED)->count();
        $attempts = $sent + $failed + $notSent;

        $byChannel = $out()->where('status', MessageLog::STATUS_SENT)
            ->selectRaw('lower(channel) as c, COUNT(*) as n')
            ->groupBy('c')
            ->pluck('n', 'c')
            ->mapWithKeys(fn ($n, $c) => [self::CHANNEL_NAMES[$c] ?? ucfirst((string) $c) => (int) $n])
            ->all();

        $problems = $out()
            ->whereIn('status', [MessageLog::STATUS_FAILED, MessageLog::STATUS_DISABLED, MessageLog::STATUS_UNCONFIGURED, MessageLog::STATUS_UNROUTABLE])
            ->latest()
            ->limit(5)
            ->get();

        return [[
            'title' => 'Messages',
            'window' => 'Last 7 days',
            'stats' => [
                ['label' => 'Delivered to provider', 'value' => number_format($sent), 'sub' => self::pct($sent, $attempts) . ' of attempts'],
                ['label' => 'Failed', 'value' => number_format($failed), 'sub' => 'rejected by the provider', 'tone' => $failed > 0 ? 'warn' : null],
                ['label' => 'Never sent', 'value' => number_format($notSent), 'sub' => 'channel off, not set up, or bad number', 'tone' => $notSent > 0 ? 'warn' : null],
                ['label' => 'Replies in', 'value' => number_format($received)],
            ],
            'split' => ['label' => 'Delivered, by channel', 'parts' => self::parts($byChannel, money: false)],
            'list' => [
                'title' => 'Latest that didn\'t go out',
                'rows' => $problems->map(fn (MessageLog $m): array => [
                    'primary' => str((string) ($m->template_key ?: $m->category ?: 'message'))->replace(['_', '.'], ' ')->ucfirst()->toString(),
                    'secondary' => collect([
                        self::CHANNEL_NAMES[strtolower((string) $m->channel)] ?? $m->channel,
                        // Only the tail of the number — enough to recognise, not to copy.
                        $m->recipient ? '…' . substr(preg_replace('/\D/', '', (string) $m->recipient) ?: (string) $m->recipient, -4) : null,
                        $m->error ? str((string) $m->error)->limit(60)->toString() : $m->status,
                    ])->filter()->join(' · '),
                    'trailing' => $m->created_at?->diffForHumans(short: true) ?? '',
                ])->all(),
                'empty' => 'Every message in the last 7 days went out.',
            ],
        ]];
    }
}
