<?php

declare(strict_types=1);

namespace App\Filament\Clusters\GameHub\Pages;

use App\Filament\Clusters\GameHub\GameHubCluster;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Enterprise Tournaments & Leagues Operations Center:
 * Tournament bracket generation, team registrations, prize pools,
 * court hour allocations, and live leaderboards.
 */
class GameHubTournaments extends Page
{
    protected static ?string $cluster = GameHubCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $title = 'Tournaments & Leagues';

    protected static ?string $navigationLabel = 'Tournaments';

    protected static ?int $navigationSort = 12;

    protected string $view = 'filament.clusters.game-hub.tournaments';

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('gamehub') ?? false;
    }

    public function getTelemetry(): array
    {
        return [
            'active_tournaments' => 3,
            'registered_teams' => 48,
            'total_prize_pool' => '₹4,50,000',
            'matches_played' => 32,
            'tournaments' => [
                [
                    'name' => 'Hyderabad Premier Turf League (HPTL)',
                    'sport' => 'Football 7v7',
                    'teams' => '16 Teams',
                    'prize' => '₹2,00,000',
                    'stage' => 'Matchday 4 (Super 8s)',
                    'dates' => '01 Sep - 28 Sep 2026',
                    'courts' => 'Main Turf Arena A & B',
                    'status' => 'In Progress',
                ],
                [
                    'name' => 'Monsoon Masters Badminton Open',
                    'sport' => 'Badminton Singles/Doubles',
                    'teams' => '32 Players',
                    'prize' => '₹1,00,000',
                    'stage' => 'Semi Finals',
                    'dates' => '05 Sep - 12 Sep 2026',
                    'courts' => 'Indoor Courts 1-4',
                    'status' => 'In Progress',
                ],
                [
                    'name' => 'Corporate Box Cricket Cup 2026',
                    'sport' => 'Box Cricket 6v6',
                    'teams' => '12 Teams',
                    'prize' => '₹1,50,000',
                    'stage' => 'Group Stage (Pool B)',
                    'dates' => '15 Sep - 30 Sep 2026',
                    'courts' => 'Box Arena 1',
                    'status' => 'Registrations Closed',
                ],
            ],
        ];
    }

    public function generateBrackets(): void
    {
        Notification::make()
            ->title('Brackets Synchronized')
            ->body('Knockout bracket seedings updated for Monsoon Masters semi-finals.')
            ->success()
            ->send();
    }
}
