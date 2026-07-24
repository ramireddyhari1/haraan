<?php

namespace App\Filament\Resources\ChannelConnections\Pages;

use App\Filament\Resources\ChannelConnections\ChannelConnectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListChannelConnections extends ListRecords
{
    protected static string $resource = ChannelConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Link an account')];
    }
}
