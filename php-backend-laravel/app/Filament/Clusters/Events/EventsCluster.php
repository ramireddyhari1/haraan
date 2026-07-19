<?php

namespace App\Filament\Clusters\Events;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class EventsCluster extends Cluster
{
    use \App\Filament\Concerns\NestsClusterItemsInSidebar;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static ?int $navigationSort = 13;

    public static function getClusterBreadcrumb(): ?string
    {
        return 'Events';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('events') ?? false;
    }
}
