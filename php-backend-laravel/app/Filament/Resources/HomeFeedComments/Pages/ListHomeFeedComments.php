<?php

declare(strict_types=1);

namespace App\Filament\Resources\HomeFeedComments\Pages;

use App\Filament\Resources\HomeFeedComments\HomeFeedCommentResource;
use Filament\Resources\Pages\ListRecords;

class ListHomeFeedComments extends ListRecords
{
    protected static string $resource = HomeFeedCommentResource::class;
}
