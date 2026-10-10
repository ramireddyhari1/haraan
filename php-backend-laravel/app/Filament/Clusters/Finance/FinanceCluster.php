<?php

namespace App\Filament\Clusters\Finance;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class FinanceCluster extends Cluster
{

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

    /**
     * Finance staff, plus any Haraan employee assigned as a partner's manager — they
     * reach Settlement accounts (scoped to their own partners) and nothing else here:
     * every other page and resource in this cluster carries its own finance/admin gate.
     */
    public static function canAccess(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->canManage('finance')
            || \App\Models\PartnerManager::query()->where('manager_id', $u->id)->exists());
    }
}
