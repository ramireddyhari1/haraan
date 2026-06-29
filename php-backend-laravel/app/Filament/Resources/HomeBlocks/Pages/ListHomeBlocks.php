<?php

declare(strict_types=1);

namespace App\Filament\Resources\HomeBlocks\Pages;

use App\Filament\Resources\HomeBlocks\HomeBlockResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHomeBlocks extends ListRecords
{
    protected static string $resource = HomeBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
