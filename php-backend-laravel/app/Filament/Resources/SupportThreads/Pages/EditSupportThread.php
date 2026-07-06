<?php

namespace App\Filament\Resources\SupportThreads\Pages;

use App\Filament\Resources\SupportThreads\SupportThreadResource;
use Filament\Resources\Pages\EditRecord;

class EditSupportThread extends EditRecord
{
    protected static string $resource = SupportThreadResource::class;

    /** Opening the conversation clears the "needs reply" badge. */
    protected function afterFill(): void
    {
        if ($this->record->admin_unread_count !== 0) {
            $this->record->forceFill(['admin_unread_count' => 0])->save();
        }
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
