<?php

declare(strict_types=1);

namespace App\Filament\Resources\Hrms\Pages;

use App\Filament\Resources\Hrms\EmployeeRosterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeRosters extends ListRecords
{
    protected static string $resource = EmployeeRosterResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
