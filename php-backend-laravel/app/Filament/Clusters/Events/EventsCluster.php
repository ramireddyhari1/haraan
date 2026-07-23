<?php

namespace App\Filament\Clusters\Events;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class EventsCluster extends Cluster
{

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static ?int $navigationSort = 13;

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
        return 'Events';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('events') ?? false;
    }
}
