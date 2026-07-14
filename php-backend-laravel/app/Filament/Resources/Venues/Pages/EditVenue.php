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

    /** Split stored images into the upload + URL fields so the FileUpload doesn't choke on URLs. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return VenueForm::splitImageSources($data);
    }

    /** Re-merge uploads + pasted URLs back into the `images` column on save. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return VenueForm::mergeImageSources($data);
    }
}
