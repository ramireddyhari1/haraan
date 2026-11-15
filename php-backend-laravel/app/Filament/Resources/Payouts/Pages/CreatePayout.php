<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payouts\Pages;

use App\Filament\Resources\Payouts\PayoutResource;
use App\Models\AdminAction;
use Filament\Resources\Pages\CreateRecord;

class CreatePayout extends CreateRecord
{
    protected static string $resource = PayoutResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return PayoutResource::normalise($data);
    }

    protected function afterCreate(): void
    {
        AdminAction::log('payout.created', [
            'payout_id' => $this->record->id,
            'booking_id' => $this->record->booking_id,
            'amount' => $this->record->amount,
            'status' => $this->record->status,
        ], $this->record);
    }
}
