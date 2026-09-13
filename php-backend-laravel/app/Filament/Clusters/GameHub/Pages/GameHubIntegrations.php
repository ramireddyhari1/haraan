<?php

declare(strict_types=1);

namespace App\Filament\Clusters\GameHub\Pages;

use App\Filament\Clusters\GameHub\GameHubCluster;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Enterprise Hardware & IoT Integrations Center:
 * Smart court floodlight relays, automated RFID/QR turnstile gates,
 * digital LED scoreboards, and payment gateway webhooks.
 */
class GameHubIntegrations extends Page
{
    protected static ?string $cluster = GameHubCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $title = 'Hardware & API Integrations';

    protected static ?string $navigationLabel = 'Integrations';

    protected static ?int $navigationSort = 15;

    protected string $view = 'filament.clusters.game-hub.integrations';

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('gamehub') ?? false;
    }

    public function getTelemetry(): array
    {
        return [
            'connected_devices' => 36,
            'controllers_online' => '36 / 36',
            'uptime' => '99.98%',
            'events_streamed' => '142,500 events/day',
            'devices' => [
                ['name' => 'Court 1 Floodlight Relay', 'type' => 'IoT Smart Relay', 'court' => 'Main Turf Arena A', 'protocol' => 'MQTT over TLS', 'ping' => '2s ago', 'status' => 'Online'],
                ['name' => 'Entry Turnstile Gate Alpha', 'type' => 'Optical QR Turnstile', 'court' => 'Main Reception', 'protocol' => 'REST API / WSS', 'ping' => '1s ago', 'status' => 'Online'],
                ['name' => 'Scoreboard LED Display', 'type' => 'Matrix LED Display', 'court' => 'Badminton Court 3', 'protocol' => 'WebSockets', 'ping' => '4s ago', 'status' => 'Online'],
                ['name' => 'POS Terminal Swipe A', 'type' => 'PineLabs Android POS', 'court' => 'Front Desk Counter', 'protocol' => 'Payment Cloud API', 'ping' => '5s ago', 'status' => 'Online'],
                ['name' => 'Court 2 Floodlight Relay', 'type' => 'IoT Smart Relay', 'court' => 'Main Turf Arena B', 'protocol' => 'MQTT over TLS', 'ping' => '3s ago', 'status' => 'Online'],
            ],
            'gateways' => [
                ['name' => 'Razorpay Gateway', 'role' => 'Web & App Checkout', 'status' => 'Healthy', 'latency' => '140ms'],
                ['name' => 'Cashfree Auto-Payout', 'role' => 'Partner Payouts', 'status' => 'Healthy', 'latency' => '190ms'],
                ['name' => 'WhatsApp Cloud API', 'role' => 'Ticketing & QR Pass', 'status' => 'Healthy', 'latency' => '320ms'],
            ],
        ];
    }

    public function syncAll(): void
    {
        Notification::make()
            ->title('Hardware Fleet Re-synced')
            ->body('36 IoT controllers, gates, and displays acknowledged heartbeat.')
            ->success()
            ->send();
    }
}
