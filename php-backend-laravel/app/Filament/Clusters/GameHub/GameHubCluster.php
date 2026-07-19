<?php

namespace App\Filament\Clusters\GameHub;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class GameHubCluster extends Cluster
{
    use \App\Filament\Concerns\NestsClusterItemsInSidebar;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static ?int $navigationSort = 12;

    public static function getClusterBreadcrumb(): ?string
    {
        return 'GameHub';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('gamehub') ?? false;
    }
}
