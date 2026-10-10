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

    /** Finance only. The cluster also admits partner managers, for Settlement accounts. */
    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('finance') ?? false;
    }

    protected static ?int $navigationSort = -10;

    protected string $view = 'filament.clusters.finance.finance-overview';

    protected function getHeaderWidgets(): array
    {
        return [
            FinanceStatsWidget::class,
        ];
    }
}
