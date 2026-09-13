<?php

declare(strict_types=1);

namespace App\Filament\Resources\Hrms\Pages;

use App\Filament\Resources\Hrms\EmployeePerformanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmployeePerformances extends ListRecords
{
    protected static string $resource = EmployeePerformanceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
