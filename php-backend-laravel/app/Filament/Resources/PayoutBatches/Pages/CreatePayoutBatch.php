<?php

declare(strict_types=1);

namespace App\Filament\Resources\PayoutBatches\Pages;

use App\Filament\Resources\PayoutBatches\PayoutBatchResource;
use App\Models\AdminAction;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePayoutBatch extends CreateRecord
{
    protected static string $resource = PayoutBatchResource::class;

    /** A batch created straight into "paid" still needs its timestamp. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (strtolower((string) ($data['status'] ?? '')) === 'paid' && blank($data['processed_at'] ?? null)) {
            $data['processed_at'] = now();
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        AdminAction::log('payout_batch.created', [
            'batch_id' => $record->id,
            'partner_id' => $record->partner_id,
            'amount' => $record->amount,
            'status' => $record->status,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
