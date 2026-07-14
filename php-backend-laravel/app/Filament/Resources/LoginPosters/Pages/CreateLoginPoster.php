<?php

namespace App\Filament\Resources\LoginPosters\Pages;

use App\Filament\Resources\LoginPosters\LoginPosterResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLoginPoster extends CreateRecord
{
    protected static string $resource = LoginPosterResource::class;

    /** Belt-and-braces: force the placement even if the hidden form field is ever tampered with. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['placement'] = 'login_poster';

        return LoginPosterResource::foldImageUrl($data);
    }
}
