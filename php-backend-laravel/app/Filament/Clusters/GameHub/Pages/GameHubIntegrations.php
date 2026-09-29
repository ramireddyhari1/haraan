<?php

declare(strict_types=1);

namespace App\Filament\Clusters\GameHub\Pages;

use App\Filament\Clusters\GameHub\Concerns\SummarisesVenues;
use App\Filament\Clusters\GameHub\GameHubCluster;
use App\Models\Booking;
use App\Models\ChannelConnection;
use App\Models\MessageLog;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

/**
 * What the platform is actually connected to, and the last time each
 * connection did something. /control reads the service configuration (a key
 * present or not — never the key) plus real activity: the last paid Razorpay
 * order, the last message each channel delivered. /partner lists the
 * partner's own channel connections (Instagram, WhatsApp…).
 *
 * There is no hardware on the platform, so there is no device list.
 */
class GameHubIntegrations extends Page
{
    use SummarisesVenues;

    protected static ?string $cluster = GameHubCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $title = 'Integrations';

    protected static ?string $navigationLabel = 'Integrations';

    protected static ?int $navigationSort = 15;

    protected string $view = 'filament.clusters.game-hub.summary-page';

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('gamehub') ?? false;
    }

    public function getPanels(): array
    {
        return static::inPartnerConsole() ? [$this->partnerChannels()] : [$this->platform()];
    }

    private function platform(): array
    {
        $set = fn (?string $v): bool => filled($v);
        $lastSent = function (string $channel): ?Carbon {
            $at = MessageLog::where('channel', $channel)->where('status', MessageLog::STATUS_SENT)->latest()->value('created_at');

            return $at ? Carbon::parse($at) : null;
        };
        $lastPayment = Booking::whereNotNull('razorpay_payment_id')->latest()->value('created_at');
        $fcmPath = (string) config('services.fcm.credentials');

        $services = [
            ['Razorpay', 'Online payments', $set(config('services.razorpay.key')) && $set(config('services.razorpay.secret')),
                $lastPayment ? 'last paid order ' . Carbon::parse($lastPayment)->diffForHumans(short: true) : 'no paid order yet'],
            ['WhatsApp', 'Tickets, OTPs and alerts (' . config('services.whatsapp.driver', 'meta') . ')',
                (bool) config('services.whatsapp.enabled') && ($set(config('services.whatsapp.access_token')) || $set(config('services.whatsapp.msg91.auth_key'))),
                ($t = $lastSent('whatsapp')) ? 'last delivered ' . $t->diffForHumans(short: true) : 'nothing delivered yet'],
            ['Firebase push', 'App notifications', $fcmPath !== '' && is_file($fcmPath),
                ($t = $lastSent('push')) ? 'last delivered ' . $t->diffForHumans(short: true) : null],
            ['Firebase auth', 'Phone sign-in', $set(config('services.firebase.api_key')) && $set(config('services.firebase.project_id')), null],
            ['Google Maps', 'Places search and maps', $set(config('services.google_maps.key')), null],
            ['Gemini', 'Player career read', $set(config('services.gemini.key')), null],
            ['Anthropic', 'Partner support assistant', $set(config('services.anthropic.key')), null],
        ];

        $on = count(array_filter($services, fn ($s) => $s[2]));

        return [
            'title' => 'Connected services',
            'stats' => [
                ['label' => 'Set up', 'value' => $on . ' of ' . count($services)],
                ['label' => 'Not set up', 'value' => (string) (count($services) - $on), 'tone' => $on < count($services) ? 'warn' : null],
                ['label' => 'Partner channels', 'value' => number_format(ChannelConnection::where('status', ChannelConnection::STATUS_ACTIVE)->count()), 'sub' => 'active Instagram / WhatsApp links'],
            ],
            'list' => [
                'title' => 'Services',
                'rows' => array_map(fn ($s): array => [
                    'primary' => $s[0],
                    'secondary' => collect([$s[1], $s[3]])->filter()->join(' · '),
                    'trailing' => $s[2] ? 'Set up' : 'Not set up',
                ], $services),
                'empty' => '',
            ],
        ];
    }

    private function partnerChannels(): array
    {
        $links = ChannelConnection::where('partner_id', static::partnerId())->orderBy('channel')->get();

        return [
            'title' => 'Your connected channels',
            'stats' => [
                ['label' => 'Connected', 'value' => number_format($links->where('status', ChannelConnection::STATUS_ACTIVE)->count())],
                ['label' => 'Needs attention', 'value' => number_format($links->whereIn('status', [ChannelConnection::STATUS_ERROR, ChannelConnection::STATUS_DISCONNECTED])->count()),
                    'tone' => $links->whereIn('status', [ChannelConnection::STATUS_ERROR, ChannelConnection::STATUS_DISCONNECTED])->isNotEmpty() ? 'warn' : null],
            ],
            'list' => [
                'title' => 'Channels',
                'rows' => $links->map(fn (ChannelConnection $c): array => [
                    'primary' => ucfirst((string) $c->channel) . ($c->username ? ' · @' . ltrim((string) $c->username, '@') : ''),
                    'secondary' => $c->last_error ? str((string) $c->last_error)->limit(70)->toString() : ($c->token_expires_at ? 'access until ' . Carbon::parse($c->token_expires_at)->format('j M Y') : null),
                    'trailing' => ucfirst((string) $c->status),
                ])->all(),
                'empty' => 'No channels connected yet. Instagram and WhatsApp can be linked from your Haraan admin.',
            ],
        ];
    }
}
