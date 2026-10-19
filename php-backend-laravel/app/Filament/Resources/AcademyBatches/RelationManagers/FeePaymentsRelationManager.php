<?php

declare(strict_types=1);

namespace App\Filament\Resources\AcademyBatches\RelationManagers;

use App\Models\AcademyFeePayment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every fee collected for this batch, newest first: the ledger a disputed fee
 * is settled from. Read-only on purpose — a wrong fee is answered with a new
 * row at the desk, never by editing history.
 */
class FeePaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'feePayments';

    protected static ?string $title = 'Fees collected';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['enrollment:id,student_name', 'collector:id,name']))
            ->defaultSort('collected_at', 'desc')
            ->columns([
                TextColumn::make('collected_at')->label('When')->dateTime('d M Y, h:i A', 'Asia/Kolkata')->sortable(),
                TextColumn::make('enrollment.student_name')->label('Student'),
                TextColumn::make('amount')->money('INR')->weight('medium'),
                TextColumn::make('method')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'opening' => 'Before records',
                        default => strtoupper($state) === 'UPI' ? 'UPI' : ucfirst($state),
                    })
                    ->color(fn (string $state): string => $state === 'cash' ? 'warning' : ($state === 'opening' ? 'gray' : 'info')),
                TextColumn::make('months')->formatStateUsing(fn (int $state): string => $state === 0 ? '—' : ($state === 1 ? '1 month' : "{$state} months")),
                TextColumn::make('paid_until')->label('Covers till')->date('d M Y')->placeholder('—'),
                TextColumn::make('collector.name')->label('Taken by')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('method')->options([
                    'cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'opening' => 'Before records',
                ]),
            ]);
    }

    public static function getBadge(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): ?string
    {
        $sum = (int) AcademyFeePayment::query()
            ->whereIn('batch_enrollment_id', $ownerRecord->enrollments()->select('id'))
            ->sum('amount');

        return $sum > 0 ? '₹' . number_format($sum) : null;
    }
}
