<?php

declare(strict_types=1);

namespace App\Filament\Resources\AcademyBatches\Pages;

use App\Filament\Resources\AcademyBatches\AcademyBatchResource;
use Filament\Resources\Pages\ListRecords;

class ListAcademyBatches extends ListRecords
{
    protected static string $resource = AcademyBatchResource::class;

    public function getSubheading(): ?string
    {
        return 'Coaching batches partners run from the app. Fees are shown, never edited.';
    }
}
