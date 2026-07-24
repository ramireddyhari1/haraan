<?php

namespace App\Filament\Resources\PartnerSubscriptions\Pages;

use App\Filament\Resources\PartnerSubscriptions\PartnerSubscriptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPartnerSubscriptions extends ListRecords
{
    protected static string $resource = PartnerSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Assign a plan')];
    }
}
