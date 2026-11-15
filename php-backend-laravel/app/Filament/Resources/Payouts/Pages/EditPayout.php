<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payouts\Pages;

use App\Filament\Resources\Payouts\PayoutResource;
use App\Models\AdminAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPayout extends EditRecord
{
    protected static string $resource = PayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return PayoutResource::normalise($data);
    }

    protected function afterSave(): void
    {
        if ($this->record->wasChanged()) {
            AdminAction::log('payout.updated', [
                'payout_id' => $this->record->id,
                'changes' => array_keys($this->record->getChanges()),
                'status' => $this->record->status,
            ], $this->record);
        }
    }
}
