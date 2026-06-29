<?php

declare(strict_types=1);

namespace App\Filament\Resources\Translations\Pages;

use App\Filament\Resources\Translations\TranslationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTranslations extends ListRecords
{
    protected static string $resource = TranslationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
