<?php

declare(strict_types=1);

namespace App\Filament\Clusters\GameHub\Pages;

use App\Filament\Clusters\GameHub\GameHubCluster;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Enterprise Staff Operations Command:
 * Court marshals, on-duty venue managers, match referees,
 * shift rosters, and field safety compliance monitoring.
 */
class GameHubStaff extends Page
{
    protected static ?string $cluster = GameHubCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static ?string $title = 'Staff Operations';

    protected static ?string $navigationLabel = 'Staff operations';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.clusters.game-hub.staff';

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('gamehub') ?? false;
    }

    public function getTelemetry(): array
    {
        return [
            'on_duty_count' => 14,
            'total_staff' => 22,
            'attendance_rate' => '100%',
            'shifts_today' => 18,
            'safety_score' => '100% Incident Free',
            'roles' => [
                ['name' => 'Court Marshals', 'count' => 6, 'duty' => 4],
                ['name' => 'Certified Referees', 'count' => 8, 'duty' => 5],
                ['name' => 'Front-Desk Cashiers', 'count' => 5, 'duty' => 3],
                ['name' => 'Facility Technicians', 'count' => 3, 'duty' => 2],
            ],
            'staff_roster' => [
                ['name' => 'Vikram Singh', 'role' => 'Lead Court Marshal', 'venue' => 'Main Arena Turf A', 'shift' => '06:00 - 14:00', 'status' => 'On Duty', 'score' => '99.4%'],
                ['name' => 'Kavita Rao', 'role' => 'National Referee', 'venue' => 'Badminton Court 3', 'shift' => '14:00 - 22:00', 'status' => 'On Duty', 'score' => '98.8%'],
                ['name' => 'Rajesh Sharma', 'role' => 'Front-Desk Cashier', 'venue' => 'Main Reception', 'shift' => '08:00 - 16:00', 'status' => 'On Duty', 'score' => '100%'],
                ['name' => 'Deepak Verma', 'role' => 'Turf Maintenance Tech', 'venue' => 'Box Cricket 1-2', 'shift' => '06:00 - 14:00', 'status' => 'Break', 'score' => '97.5%'],
                ['name' => 'Pooja Nair', 'role' => 'Match Scorer & Ops', 'venue' => 'Football Turf B', 'shift' => '16:00 - 00:00', 'status' => 'Upcoming', 'score' => '99.0%'],
            ],
        ];
    }

    public function pingStaff(): void
    {
        Notification::make()
            ->title('Ops Alert Broadcasted')
            ->body('Shift handover alert sent to all 14 on-duty staff devices.')
            ->success()
            ->send();
    }
}
