<?php

namespace App\Filament\Resources\Venues\Pages;

use App\Filament\Resources\Venues\Schemas\VenueForm;
use App\Filament\Resources\Venues\VenueResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVenue extends CreateRecord
{
    protected static string $resource = VenueResource::class;

    /**
     * Admin-only page — venues are created in /control and assigned to a partner
     * there, so there is no partner-panel ownership stamp to apply here; the
     * "Owner / partner" select on the form is the single place ownership is set.
     * See VenueResource::canCreate(), which closes this page in /partner.
     *
     * Folds the pasted image URLs into the `images` column alongside any uploads.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'draft';
        $data['is_active'] = false;
        $data['is_bookable'] = false;

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

    /**
     * After creating, redirect to the edit page so the admin can complete configuration
     * (Courts, Pricing, Slots, Photos) before publishing.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }

    protected function getCreatedNotification(): ?\Filament\Notifications\Notification
    {
        return \Filament\Notifications\Notification::make()
            ->title('Venue draft created')
            ->body('Venue created in Draft mode. Please configure courts, slots, pricing, and photos before publishing.')
            ->info();
    }
}
