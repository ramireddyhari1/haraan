<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupportCategories\Pages;

use App\Filament\Resources\SupportCategories\SupportCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSupportCategory extends EditRecord
{
    protected static string $resource = SupportCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
