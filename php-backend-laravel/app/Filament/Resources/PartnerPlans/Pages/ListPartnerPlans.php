<?php

namespace App\Filament\Resources\PartnerPlans\Pages;

use App\Filament\Resources\PartnerPlans\PartnerPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPartnerPlans extends ListRecords
{
    protected static string $resource = PartnerPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
