<?php

declare(strict_types=1);

namespace App\Filament\Resources\VenueBlocks\Pages;

use App\Filament\Resources\VenueBlocks\VenueBlockResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVenueBlock extends CreateRecord
{
    protected static string $resource = VenueBlockResource::class;

    /** Stamp who took the time off sale — blocks cost money, so they need an author. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }

    /** Back to the list: the point of creating a block is seeing it in force. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
