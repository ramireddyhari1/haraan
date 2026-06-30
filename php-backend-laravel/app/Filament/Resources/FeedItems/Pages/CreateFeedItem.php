<?php

namespace App\Filament\Resources\FeedItems\Pages;

use App\Filament\Resources\FeedItems\FeedItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFeedItem extends CreateRecord
{
    protected static string $resource = FeedItemResource::class;
}
