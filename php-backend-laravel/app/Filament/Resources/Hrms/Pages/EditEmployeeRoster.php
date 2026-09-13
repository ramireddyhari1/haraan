<?php

declare(strict_types=1);

namespace App\Filament\Resources\Hrms\Pages;

use App\Filament\Resources\Hrms\EmployeeRosterResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeRoster extends EditRecord
{
    protected static string $resource = EmployeeRosterResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
