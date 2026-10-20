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

    /**
     * Opened from Finance Overview's "Owed the most" list (?partner=ID): choose that partner
     * and run the select's own side-effects, so the gross, the tax rates and the payout
     * method fill exactly as if the partner had been picked by hand.
     */
    protected function afterFill(): void
    {
        $partnerId = (int) request()->query('partner', 0);
        if ($partnerId <= 0) {
            return;
        }

        $this->data['partner_id'] = (string) $partnerId;

        foreach ($this->form->getFlatComponents() as $component) {
            if ($component instanceof \Filament\Forms\Components\Select && $component->getName() === 'partner_id') {
                $component->callAfterStateUpdated();
                break;
            }
        }
    }

    /** A batch created straight into "paid" still needs its timestamp. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Never trust a browser-supplied payout: every deduction and the net are
        // re-derived on the server from the gross and the rates.
        $data = array_merge($data, PayoutBatchResource::figuresFrom($data));

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
            'gross' => $record->gross_amount,
            'amount' => $record->amount,
            'deductions' => $record->totalDeductions(),
            'invoice_number' => $record->invoice_number,
            'status' => $record->status,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
