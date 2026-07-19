<?php

namespace App\Filament\Clusters\Marketing;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class MarketingCluster extends Cluster
{
    use \App\Filament\Concerns\NestsClusterItemsInSidebar;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?int $navigationSort = 11;

    public static function getClusterBreadcrumb(): ?string
    {
        return 'Marketing';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('marketing') ?? false;
    }
}
