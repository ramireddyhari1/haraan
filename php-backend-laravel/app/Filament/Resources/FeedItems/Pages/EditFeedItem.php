<?php

namespace App\Filament\Resources\FeedItems\Pages;

use App\Filament\Resources\FeedItems\FeedItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFeedItem extends EditRecord
{
    protected static string $resource = FeedItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
