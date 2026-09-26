<?php

namespace App\Filament\Resources\WhatsAppQuickReplies\Pages;

use App\Filament\Resources\WhatsAppQuickReplies\WhatsAppQuickReplyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWhatsAppQuickReply extends EditRecord
{
    protected static string $resource = WhatsAppQuickReplyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
