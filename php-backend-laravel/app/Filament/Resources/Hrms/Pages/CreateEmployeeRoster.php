<?php

declare(strict_types=1);

namespace App\Filament\Resources\Hrms\Pages;

use App\Filament\Resources\Hrms\EmployeeRosterResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployeeRoster extends CreateRecord
{
    protected static string $resource = EmployeeRosterResource::class;
}
