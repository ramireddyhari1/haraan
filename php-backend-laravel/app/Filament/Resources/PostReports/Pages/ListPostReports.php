<?php

declare(strict_types=1);

namespace App\Filament\Resources\PostReports\Pages;

use App\Filament\Resources\PostReports\PostReportResource;
use Filament\Resources\Pages\ListRecords;

class ListPostReports extends ListRecords
{
    protected static string $resource = PostReportResource::class;
}
