<?php

namespace App\Filament\Resources\Venues\Pages;

use App\Filament\Resources\Venues\Schemas\VenueForm;
use App\Filament\Resources\Venues\VenueResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVenue extends EditRecord
{
    protected static string $resource = VenueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /** Split stored images/amenities/rules/hours into their form helper fields for editing. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = VenueForm::splitImageSources($data);
        $data = VenueForm::splitAmenities($data);
        $data = VenueForm::splitRules($data);

        return VenueForm::splitHours($data);
    }

    /** Re-merge the helper fields back into their columns on save. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = VenueForm::mergeImageSources($data);
        $data = VenueForm::mergeAmenities($data);
        $data = VenueForm::mergeRules($data);

        return VenueForm::mergeHours($data);
    }

    /** Derive the display hours string and regenerate bookable slots from structured hours. */
    protected function afterSave(): void
    {
        $this->record->update(['hours' => $this->record->displayHours()]);
        $this->record->regenerateSlotsFromHours();
    }
}
