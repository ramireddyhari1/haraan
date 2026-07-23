<?php

declare(strict_types=1);

namespace App\Filament\Resources\LegalDocuments\Pages;

use App\Filament\Resources\LegalDocuments\LegalDocumentResource;
use Filament\Resources\Pages\ListRecords;

class ListLegalDocuments extends ListRecords
{
    protected static string $resource = LegalDocumentResource::class;
}
