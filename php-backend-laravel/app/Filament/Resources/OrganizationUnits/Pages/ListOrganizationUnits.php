<?php

namespace App\Filament\Resources\OrganizationUnits\Pages;

use App\Filament\Resources\OrganizationUnits\OrganizationUnitResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrganizationUnits extends ListRecords
{
    protected static string $resource = OrganizationUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
