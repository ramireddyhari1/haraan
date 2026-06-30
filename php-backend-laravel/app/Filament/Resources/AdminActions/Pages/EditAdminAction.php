<?php

namespace App\Filament\Resources\AdminActions\Pages;

use App\Filament\Resources\AdminActions\AdminActionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAdminAction extends EditRecord
{
    protected static string $resource = AdminActionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
