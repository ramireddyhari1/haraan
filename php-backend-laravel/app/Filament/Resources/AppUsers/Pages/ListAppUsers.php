<?php

declare(strict_types=1);

namespace App\Filament\Resources\AppUsers\Pages;

use App\Filament\Resources\AppUsers\AppUserResource;
use App\Filament\Resources\Users\Widgets\UsersStatsWidget;
use Filament\Resources\Pages\ListRecords;

class ListAppUsers extends ListRecords
{
    protected static string $resource = AppUserResource::class;

    /** KPI tiles above the table (total / active / new / elevated). */
    protected function getHeaderWidgets(): array
    {
        return [
            UsersStatsWidget::class,
        ];
    }
}
