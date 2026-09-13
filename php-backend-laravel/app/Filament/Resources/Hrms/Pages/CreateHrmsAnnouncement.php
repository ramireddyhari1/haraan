<?php

declare(strict_types=1);

namespace App\Filament\Resources\Hrms\Pages;

use App\Filament\Resources\Hrms\HrmsAnnouncementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHrmsAnnouncement extends CreateRecord
{
    protected static string $resource = HrmsAnnouncementResource::class;
}
