<?php

namespace App\Filament\Clusters\Finance\Pages;

use App\Filament\Clusters\Finance\FinanceCluster;
use App\Filament\Clusters\Finance\Widgets\FinanceStatsWidget;
use BackedEnum;
use Filament\Pages\Page;

class FinanceOverview extends Page
{
    protected static ?string $cluster = FinanceCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $title = 'Finance Overview';

    protected static ?string $navigationLabel = 'Overview';

    protected static ?int $navigationSort = -10;

    protected string $view = 'filament.clusters.finance.finance-overview';

    protected function getHeaderWidgets(): array
    {
        return [
            FinanceStatsWidget::class,
        ];
    }
}
