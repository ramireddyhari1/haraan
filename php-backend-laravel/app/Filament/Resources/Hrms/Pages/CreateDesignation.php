<?php

declare(strict_types=1);

namespace App\Filament\Resources\Hrms\Pages;

use App\Filament\Resources\Hrms\DesignationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDesignation extends CreateRecord
{
    protected static string $resource = DesignationResource::class;
}
