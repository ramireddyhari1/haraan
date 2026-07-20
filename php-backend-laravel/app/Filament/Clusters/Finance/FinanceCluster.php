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

    /**
     * The cluster's sections live in the main sidebar (see ClusterSidebarNavigation),
     * so the cluster itself no longer needs a nav item, and its in-content
     * sub-navigation — a left column on desktop, an "Overview" dropdown on mobile —
     * is switched off.
     */
    protected static bool $shouldRegisterNavigation = false;

    protected static bool $shouldRegisterSubNavigation = false;

    public static function getClusterBreadcrumb(): ?string
    {
        return 'Finance';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('finance') ?? false;
    }
}
