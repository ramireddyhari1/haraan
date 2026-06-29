<?php

declare(strict_types=1);

namespace App\Filament\Resources\Translations\Pages;

use App\Filament\Resources\Translations\TranslationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTranslation extends CreateRecord
{
    protected static string $resource = TranslationResource::class;
}
