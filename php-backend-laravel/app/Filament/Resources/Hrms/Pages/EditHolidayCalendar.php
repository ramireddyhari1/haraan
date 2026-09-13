<?php

declare(strict_types=1);

namespace App\Filament\Resources\Hrms\Pages;

use App\Filament\Resources\Hrms\HolidayCalendarResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHolidayCalendar extends EditRecord
{
    protected static string $resource = HolidayCalendarResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
