<?php

declare(strict_types=1);

namespace App\Filament\Resources\HostProfiles\Pages;

use App\Filament\Resources\HostProfiles\HostProfileResource;
use Filament\Resources\Pages\ListRecords;

class ListHostProfiles extends ListRecords
{
    protected static string $resource = HostProfileResource::class;
}
