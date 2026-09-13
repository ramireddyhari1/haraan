<?php

namespace App\Filament\Resources\SectionThemes\Pages;

use App\Filament\Resources\SectionThemes\SectionThemeResource;
use App\Filament\Resources\SectionThemes\Tables\SectionThemesTable;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSectionTheme extends EditRecord
{
    protected static string $resource = SectionThemeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SectionThemesTable::exportAction(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return SectionThemeResource::splitDecorationForEdit($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return SectionThemeResource::foldDecoration($data);
    }
}
