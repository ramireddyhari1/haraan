<?php

namespace App\Filament\Resources\SupportThreads\Pages;

use App\Filament\Resources\SupportThreads\SupportThreadResource;
use Filament\Resources\Pages\ListRecords;

class ListSupportThreads extends ListRecords
{
    protected static string $resource = SupportThreadResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
