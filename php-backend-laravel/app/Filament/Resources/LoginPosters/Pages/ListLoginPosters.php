<?php

namespace App\Filament\Resources\LoginPosters\Pages;

use App\Filament\Resources\LoginPosters\LoginPosterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLoginPosters extends ListRecords
{
    protected static string $resource = LoginPosterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
