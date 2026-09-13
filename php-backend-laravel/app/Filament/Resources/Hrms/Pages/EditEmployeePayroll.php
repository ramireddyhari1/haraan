<?php

declare(strict_types=1);

namespace App\Filament\Resources\Hrms\Pages;

use App\Filament\Resources\Hrms\EmployeePayrollResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEmployeePayroll extends EditRecord
{
    protected static string $resource = EmployeePayrollResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
