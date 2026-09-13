<?php

declare(strict_types=1);

namespace App\Filament\Resources\Hrms\Pages;

use App\Filament\Resources\Hrms\HolidayCalendarResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHolidayCalendar extends CreateRecord
{
    protected static string $resource = HolidayCalendarResource::class;
}
