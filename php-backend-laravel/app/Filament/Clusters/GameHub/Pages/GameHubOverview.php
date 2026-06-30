<?php

namespace App\Filament\Clusters\GameHub\Pages;

use App\Filament\Clusters\GameHub\GameHubCluster;
use App\Filament\Clusters\GameHub\Widgets\GameHubStatsWidget;
use BackedEnum;
use Filament\Pages\Page;

class GameHubOverview extends Page
{
    protected static ?string $cluster = GameHubCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $title = 'GameHub Overview';

    protected static ?string $navigationLabel = 'Overview';

    protected static ?int $navigationSort = -10;

    protected string $view = 'filament.clusters.game-hub.game-hub-overview';

    protected function getHeaderWidgets(): array
    {
        return [
            GameHubStatsWidget::class,
        ];
    }
}
