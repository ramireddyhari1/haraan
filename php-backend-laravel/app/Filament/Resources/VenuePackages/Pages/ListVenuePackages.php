<?php

declare(strict_types=1);

namespace App\Filament\Resources\VenuePackages\Pages;

use App\Filament\Resources\VenuePackages\VenuePackageResource;
use Filament\Resources\Pages\ListRecords;

class ListVenuePackages extends ListRecords
{
    protected static string $resource = VenuePackageResource::class;

    public function getSubheading(): ?string
    {
        return 'Prepaid passes partners sell at the desk, and who holds one.';
    }
}
