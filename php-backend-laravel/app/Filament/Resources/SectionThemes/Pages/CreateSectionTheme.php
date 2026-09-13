<?php

namespace App\Filament\Resources\SectionThemes\Pages;

use App\Filament\Resources\SectionThemes\SectionThemeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSectionTheme extends CreateRecord
{
    protected static string $resource = SectionThemeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return SectionThemeResource::foldDecoration($data);
    }
}
