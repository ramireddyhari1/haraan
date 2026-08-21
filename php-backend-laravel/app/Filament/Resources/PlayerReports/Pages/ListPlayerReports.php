<?php

namespace App\Filament\Resources\PlayerReports\Pages;

use App\Filament\Resources\PlayerReports\PlayerReportResource;
use Filament\Resources\Pages\ListRecords;

class ListPlayerReports extends ListRecords
{
    protected static string $resource = PlayerReportResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
