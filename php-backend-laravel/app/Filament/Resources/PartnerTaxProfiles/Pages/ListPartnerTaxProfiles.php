<?php

declare(strict_types=1);

namespace App\Filament\Resources\PartnerTaxProfiles\Pages;

use App\Filament\Resources\PartnerTaxProfiles\PartnerTaxProfileResource;
use Filament\Resources\Pages\ListRecords;

class ListPartnerTaxProfiles extends ListRecords
{
    protected static string $resource = PartnerTaxProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [PartnerTaxProfileResource::addAction(), PartnerTaxProfileResource::uploadAction()];
    }
}
