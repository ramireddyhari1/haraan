<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupportCategories\Pages;

use App\Filament\Resources\SupportCategories\SupportCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSupportCategory extends CreateRecord
{
    protected static string $resource = SupportCategoryResource::class;
}
