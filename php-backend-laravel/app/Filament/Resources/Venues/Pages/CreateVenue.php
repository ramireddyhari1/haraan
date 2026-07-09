<?php

namespace App\Filament\Resources\Venues\Pages;

use App\Filament\Resources\Venues\VenueResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateVenue extends CreateRecord
{
    protected static string $resource = VenueResource::class;

    /**
     * In the partner console, stamp ownership so the new venue is scoped to (and
     * visible to) its creating partner. See ScopesToOrganization.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (Filament::getCurrentPanel()?->getId() === 'partner') {
            $data['partner_id'] = auth()->user()?->effectivePartnerId();
        }

        return $data;
    }
}
