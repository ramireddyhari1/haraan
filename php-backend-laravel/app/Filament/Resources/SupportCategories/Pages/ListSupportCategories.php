<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupportCategories\Pages;

use App\Filament\Resources\SupportCategories\SupportCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSupportCategories extends ListRecords
{
    protected static string $resource = SupportCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
