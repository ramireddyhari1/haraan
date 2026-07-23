<?php

namespace App\Filament\Resources\AdminActions\Pages;

use App\Filament\Resources\AdminActions\AdminActionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdminAction extends CreateRecord
{
    protected static string $resource = AdminActionResource::class;
}
