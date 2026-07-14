<?php

namespace App\Filament\Resources\LoginPosters\Pages;

use App\Filament\Resources\LoginPosters\LoginPosterResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLoginPoster extends EditRecord
{
    protected static string $resource = LoginPosterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /** Show a URL-based poster's link in the image_url field (FileUpload can't load remote URLs). */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return LoginPosterResource::splitImageUrlForEdit($data);
    }

    /** Fold image_url back into image on save. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return LoginPosterResource::foldImageUrl($data);
    }
}
