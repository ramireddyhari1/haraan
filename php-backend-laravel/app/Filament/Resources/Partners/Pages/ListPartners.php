<?php

namespace App\Filament\Resources\Partners\Pages;

use App\Filament\Resources\Partners\PartnerResource;
use App\Filament\Resources\Partners\Widgets\PartnersStatsWidget;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPartners extends ListRecords
{
    protected static string $resource = PartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /** KPI tiles above the table (total / venue vs event split). */
    protected function getHeaderWidgets(): array
    {
        return [
            PartnersStatsWidget::class,
        ];
    }
}
