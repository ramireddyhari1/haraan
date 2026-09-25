<?php

namespace App\Filament\Resources\Venues\Pages;

use App\Filament\Resources\Venues\Schemas\VenueForm;
use App\Filament\Resources\Venues\VenueResource;
use App\Models\Venue;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditVenue extends EditRecord
{
    protected static string $resource = VenueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // A venue with bookings is never deleted — its bookings would be orphaned
            // (they reference the venue by id only). Unpublish it instead.
            DeleteAction::make()
                ->modalDescription(fn (Venue $record): string => $record->hasBookings()
                    ? 'This venue has bookings, so it can’t be deleted. Unpublish it instead.'
                    : 'Deletes the venue with its courts, slots and blocks. This can’t be undone.')
                ->before(function (DeleteAction $action, Venue $record): void {
                    if ($record->hasBookings()) {
                        Notification::make()
                            ->title('Can’t delete a venue with bookings')
                            ->body('Its bookings would lose their venue — and drop out of the partner’s sheet and payouts. Unpublish it instead.')
                            ->danger()
                            ->persistent()
                            ->send();
                        $action->cancel();
                    }
                }),
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
        // Read before the update() below, which starts a new change set. Saving a photo or
        // a price must not touch the slot template; only a change to the hours does.
        $hoursChanged = $this->record->wasChanged(['hours_json', 'slot_minutes']);

        $this->record->update(['hours' => $this->record->displayHours()]);

        if ($hoursChanged) {
            $this->record->regenerateSlotsFromHours();
        }

        // The Status select (not a column). Applied last, so slots made from new hours and
        // photos uploaded in this same save count towards readiness.
        $wanted = (string) ($this->data['visibility_state'] ?? '');
        if ($wanted !== '' && $wanted !== $this->record->visibilityState()) {
            $errors = $this->record->applyVisibilityState($wanted);

            if ($errors !== []) {
                Notification::make()
                    ->title('Saved — but the venue can’t go live yet')
                    ->body("It stays as " . strtolower(Venue::STATES[$this->record->visibilityState()]) . ". Still missing:\n• " . implode("\n• ", $errors))
                    ->warning()
                    ->persistent()
                    ->send();
            }
        }

        // Keep the select showing what is actually true now.
        $this->data['visibility_state'] = $this->record->fresh()->visibilityState();
    }

    /**
     * After saving, return to the venues list. Filament's default keeps you on the
     * edit page, so "Save changes" only flashed a toast and looked like it did
     * nothing — the user expects to be taken back to the venues page.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
