<?php

namespace App\Filament\Resources\LiveMatches\Pages;

use App\Filament\Resources\LiveMatches\LiveMatchResource;
use Filament\Resources\Pages\ListRecords;

class ListLiveMatches extends ListRecords
{
    protected static string $resource = LiveMatchResource::class;
}
