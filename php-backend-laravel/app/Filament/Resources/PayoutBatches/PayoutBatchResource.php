<?php

declare(strict_types=1);

namespace App\Filament\Resources\PayoutBatches;

use App\Filament\Resources\PayoutBatches\Pages\CreatePayoutBatch;
use App\Filament\Resources\PayoutBatches\Pages\EditPayoutBatch;
use App\Filament\Resources\PayoutBatches\Pages\ListPayoutBatches;
use App\Filament\Resources\PayoutBatches\Schemas\PayoutBatchForm;
use App\Filament\Resources\PayoutBatches\Tables\PayoutBatchesTable;
use App\Models\PayoutBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * Settlements to partners — the admin half of the Payouts feature.
 *
 * A batch is one transfer covering a period's takings. Admin picks the partner
 * (the form shows what they're owed and where the money should land), sends the
 * money out-of-band, then marks the batch paid with its UTR / reference. The
 * partner reads the result in /partner → Payouts.
 *
 * The per-booking {@see \App\Models\Payout} ledger stays as-is; batches are the
 * partner-facing rollup, and both are netted off the balance by
 * {@see \App\Services\PartnerSettlement}.
 */
class PayoutBatchResource extends Resource
{
    protected static ?string $model = PayoutBatch::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $cluster = \App\Filament\Clusters\Finance\FinanceCluster::class;

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'payout batch';

    protected static ?string $pluralModelLabel = 'partner settlements';

    protected static ?string $navigationLabel = 'Partner settlements';

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('finance') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return PayoutBatchForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PayoutBatchesTable::configure($table);
    }

    /** Anything still processing is work the finance desk owes someone. */
    public static function getNavigationBadge(): ?string
    {
        $open = PayoutBatch::query()
            ->whereIn(\Illuminate\Support\Facades\DB::raw('lower(status)'), ['processing', 'pending'])
            ->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayoutBatches::route('/'),
            'create' => CreatePayoutBatch::route('/create'),
            'edit' => EditPayoutBatch::route('/{record}/edit'),
        ];
    }
}
