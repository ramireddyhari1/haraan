<?php

namespace App\Filament\Clusters\Marketing\Pages;

use App\Filament\Clusters\Marketing\MarketingCluster;
use App\Filament\Clusters\Marketing\Widgets\MarketingStatsWidget;
use BackedEnum;
use Filament\Pages\Page;

class MarketingOverview extends Page
{
    protected static ?string $cluster = MarketingCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $title = 'Marketing Overview';

    protected static ?string $navigationLabel = 'Overview';

    protected static ?int $navigationSort = -10;

    protected string $view = 'filament.clusters.marketing.marketing-overview';

    protected function getHeaderWidgets(): array
    {
        return [
            MarketingStatsWidget::class,
        ];
    }
}
