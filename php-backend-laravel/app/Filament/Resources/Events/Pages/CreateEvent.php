<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Events\Schemas\EventForm;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;

    /** Fold pasted image URLs into the images column before the record is created. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = EventForm::mergeImageSources($data);

        // In the partner console, stamp ownership so the new event is scoped to
        // (and visible to) its creating partner. See ScopesToOrganization.
        if (Filament::getCurrentPanel()?->getId() === 'partner') {
            $data['partner_id'] = auth()->user()?->effectivePartnerId();
        }

        return $data;
    }
}
