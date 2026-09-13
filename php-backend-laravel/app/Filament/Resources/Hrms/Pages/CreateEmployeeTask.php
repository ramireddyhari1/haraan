<?php

declare(strict_types=1);

namespace App\Filament\Resources\Hrms\Pages;

use App\Filament\Resources\Hrms\EmployeeTaskResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployeeTask extends CreateRecord
{
    protected static string $resource = EmployeeTaskResource::class;
}
