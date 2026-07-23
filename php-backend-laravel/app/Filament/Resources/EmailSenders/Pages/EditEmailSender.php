<?php

namespace App\Filament\Resources\EmailSenders\Pages;

use App\Filament\Resources\EmailSenders\EmailSenderResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEmailSender extends EditRecord
{
    protected static string $resource = EmailSenderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
