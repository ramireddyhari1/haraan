<?php

declare(strict_types=1);

namespace App\Filament\Resources\Hrms\Pages;

use App\Filament\Resources\Hrms\EmployeePerformanceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployeePerformance extends CreateRecord
{
    protected static string $resource = EmployeePerformanceResource::class;
}
