<?php

declare(strict_types=1);

namespace App\Filament\Resources\Hrms\Pages;

use App\Filament\Resources\Hrms\EmployeeShiftResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployeeShift extends CreateRecord
{
    protected static string $resource = EmployeeShiftResource::class;
}
