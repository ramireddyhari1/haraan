<?php

namespace App\Filament\Resources\Venues\Pages;

use App\Filament\Resources\Venues\Schemas\VenueForm;
use App\Filament\Resources\Venues\VenueResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateVenue extends CreateRecord
{
    protected static string $resource = VenueResource::class;

    /**
     * In the partner console, stamp ownership so the new venue is scoped to (and
     * visible to) its creating partner. See ScopesToOrganization. Also folds the
     * pasted image URLs into the `images` column alongside any uploads.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (Filament::getCurrentPanel()?->getId() === 'partner') {
            $data['partner_id'] = auth()->user()?->effectivePartnerId();
        }

        $data = VenueForm::mergeImageSources($data);
        $data = VenueForm::mergeAmenities($data);
        $data = VenueForm::mergeRules($data);

        return VenueForm::mergeHours($data);
    }

    /** Derive the display hours string and (re)generate bookable slots from the structured hours. */
    protected function afterCreate(): void
    {
        $this->record->update(['hours' => $this->record->displayHours()]);
        $this->record->regenerateSlotsFromHours();
    }
}
