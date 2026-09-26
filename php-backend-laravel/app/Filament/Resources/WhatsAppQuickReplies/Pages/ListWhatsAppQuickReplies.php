<?php

namespace App\Filament\Resources\WhatsAppQuickReplies\Pages;

use App\Filament\Resources\WhatsAppQuickReplies\WhatsAppQuickReplyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWhatsAppQuickReplies extends ListRecords
{
    protected static string $resource = WhatsAppQuickReplyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
