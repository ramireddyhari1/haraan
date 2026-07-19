<?php

namespace App\Filament\Clusters\Finance;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class FinanceCluster extends Cluster
{
    use \App\Filament\Concerns\NestsClusterItemsInSidebar;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $clusterBreadcrumb = 'Finance';

    protected static ?int $navigationSort = 10;

    public static function getClusterBreadcrumb(): ?string
    {
        return 'Finance';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('finance') ?? false;
    }
}
