<?php

declare(strict_types=1);

namespace App\Filament\Resources\PayoutBatches\Pages;

use App\Filament\Resources\PayoutBatches\PayoutBatchResource;
use App\Models\PayoutBatch;
use App\Services\PartnerSettlement;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\DB;

class ListPayoutBatches extends ListRecords
{
    protected static string $resource = PayoutBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New settlement'),
        ];
    }

    /** A one-line ledger summary above the table: owed / in flight / paid out. */
    public function getSubheading(): ?string
    {
        $paid = (float) PayoutBatch::query()
            ->whereIn(DB::raw('lower(status)'), PayoutBatch::PAID)
            ->sum('amount');

        $open = (float) PayoutBatch::query()
            ->whereIn(DB::raw('lower(status)'), ['processing', 'pending'])
            ->sum('amount');

        return 'Paid out ' . PartnerSettlement::inr($paid)
            . ' · ' . PartnerSettlement::inr($open) . ' still processing';
    }
}
