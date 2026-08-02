<?php

declare(strict_types=1);

namespace App\Filament\Resources\VenueBlocks\Pages;

use App\Filament\Resources\VenueBlocks\VenueBlockResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVenueBlocks extends ListRecords
{
    protected static string $resource = VenueBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Block time'),
        ];
    }
}
