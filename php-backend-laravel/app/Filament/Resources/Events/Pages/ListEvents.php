<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Events\Widgets\EventsListStatsWidget;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEvents extends ListRecords
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /** KPI tiles above the table (total / published / tickets sold). */
    protected function getHeaderWidgets(): array
    {
        return [
            EventsListStatsWidget::class,
        ];
    }
}
