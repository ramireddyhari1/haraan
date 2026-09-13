<?php

declare(strict_types=1);

namespace App\Filament\Resources\Hrms\Pages;

use App\Filament\Resources\Hrms\EmployeeShiftSwapResource;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeShiftSwaps extends ListRecords
{
    protected static string $resource = EmployeeShiftSwapResource::class;
}
