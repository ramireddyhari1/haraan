<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Events\Schemas\EventForm;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    /** Split stored images into uploads vs pasted URLs for the two form fields. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return EventForm::splitImageSources($data);
    }

    /** Fold the pasted URLs back into the images column on save. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = EventForm::mergeImageSources($data);

        // Keep available seats in step when capacity changes: shift available_slots by the
        // same delta as total_slots, so bumping capacity frees seats (and shrinking it
        // removes them) without wiping out seats already sold. Never goes below 0.
        if (isset($data['total_slots'])) {
            $delta = (int) $data['total_slots'] - (int) $this->record->total_slots;
            if ($delta !== 0) {
                $data['available_slots'] = max(0, (int) $this->record->available_slots + $delta);
            }
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
