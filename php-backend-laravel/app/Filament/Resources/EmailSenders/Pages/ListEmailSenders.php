<?php

namespace App\Filament\Resources\EmailSenders\Pages;

use App\Filament\Resources\EmailSenders\EmailSenderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmailSenders extends ListRecords
{
    protected static string $resource = EmailSenderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
