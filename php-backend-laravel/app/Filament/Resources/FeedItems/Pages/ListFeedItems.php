<?php

namespace App\Filament\Resources\FeedItems\Pages;

use App\Filament\Resources\FeedItems\FeedItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFeedItems extends ListRecords
{
    protected static string $resource = FeedItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
