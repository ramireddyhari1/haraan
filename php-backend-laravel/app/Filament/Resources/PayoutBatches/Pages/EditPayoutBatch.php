<?php

declare(strict_types=1);

namespace App\Filament\Resources\PayoutBatches\Pages;

use App\Filament\Resources\PayoutBatches\PayoutBatchResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPayoutBatch extends EditRecord
{
    protected static string $resource = PayoutBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /** Flipping the status to paid by hand stamps the time too. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Amounts are frozen once paid (the inputs are disabled and not sent); before
        // that, an edit re-derives every deduction exactly like a new settlement.
        if (! $this->getRecord()->isPaid() && array_key_exists('gross_amount', $data)) {
            $data['partner_id'] ??= $this->getRecord()->partner_id;
            $data = array_merge($data, PayoutBatchResource::figuresFrom($data));
        }

        if (strtolower((string) ($data['status'] ?? '')) === 'paid' && blank($data['processed_at'] ?? null)) {
            $data['processed_at'] = now();
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
