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
        return EventForm::mergeImageSources($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
