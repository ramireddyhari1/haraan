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

        // Seed available seats from the total capacity. The form only asks for
        // total_slots; without this available_slots keeps its DB default of 0, so a
        // brand-new event would show "sold out" immediately.
        if (! isset($data['available_slots']) || (int) $data['available_slots'] <= 0) {
            $data['available_slots'] = (int) ($data['total_slots'] ?? 0);
        }

        // In the partner console, stamp ownership so the new event is scoped to
        // (and visible to) its creating partner. See ScopesToOrganization.
        if (Filament::getCurrentPanel()?->getId() === 'partner') {
            $data['partner_id'] = auth()->user()?->effectivePartnerId();
        }

        return $data;
    }
}
