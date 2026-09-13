<?php

declare(strict_types=1);

namespace App\Filament\Clusters\GameHub\Pages;

use App\Filament\Clusters\GameHub\GameHubCluster;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Enterprise Operations Support & Dispute Center:
 * Court double-booking disputes, weather reschedule requests,
 * refund claims, lost-and-found items, and player SLA tracking.
 */
class GameHubSupport extends Page
{
    protected static ?string $cluster = GameHubCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-lifebuoy';

    protected static ?string $title = 'Operations Support Center';

    protected static ?string $navigationLabel = 'Support center';

    protected static ?int $navigationSort = 14;

    protected string $view = 'filament.clusters.game-hub.support';

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('gamehub') ?? false;
    }

    public function getTelemetry(): array
    {
        return [
            'open_tickets' => 3,
            'resolved_today' => 18,
            'avg_response_time' => '4.2 mins',
            'csat_score' => '4.9 / 5.0',
            'tickets' => [
                ['id' => 'TIC-8821', 'user' => 'Karthik Raja', 'category' => 'Slot Swap Request', 'court' => 'Main Turf A', 'priority' => 'High', 'elapsed' => '12m ago', 'status' => 'In Review'],
                ['id' => 'TIC-8819', 'user' => 'Ananya Sharma', 'category' => 'Double Charge Refund', 'court' => 'Badminton Court 2', 'priority' => 'Critical', 'elapsed' => '28m ago', 'status' => 'Gateway Verified'],
                ['id' => 'TIC-8815', 'user' => 'Syed Farhan', 'category' => 'Lost Kit Bag', 'court' => 'Box Arena 1', 'priority' => 'Normal', 'elapsed' => '1h ago', 'status' => 'Located with Marshal'],
            ],
            'categories' => [
                ['name' => 'Weather / Rain Check Reschedule', 'count' => 12, 'resolved' => 12, 'pct' => 100],
                ['name' => 'Slot Time Modification', 'count' => 8, 'resolved' => 7, 'pct' => 88],
                ['name' => 'Payment & Refund Reconciliation', 'count' => 6, 'resolved' => 5, 'pct' => 83],
                ['name' => 'Facility & Equipment Assistance', 'count' => 4, 'resolved' => 4, 'pct' => 100],
            ],
        ];
    }

    public function resolveAll(): void
    {
        Notification::make()
            ->title('Dispute Auto-Resolved')
            ->body('Slot swap approved and refund processed via payment gateway.')
            ->success()
            ->send();
    }
}
