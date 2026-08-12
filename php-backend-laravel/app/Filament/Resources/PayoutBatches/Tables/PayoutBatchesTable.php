<?php

declare(strict_types=1);

namespace App\Filament\Resources\PayoutBatches\Tables;

use App\Models\AdminAction;
use App\Models\PartnerPayoutAccount;
use App\Models\PayoutBatch;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PayoutBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('partner.name')
                    ->label('Partner')
                    ->weight('bold')
                    ->description(fn (PayoutBatch $r): string => static::destination($r))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('amount')
                    ->money('INR')
                    ->weight('bold')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match (strtolower($state)) {
                        'paid', 'processed', 'completed' => 'success',
                        'failed' => 'danger',
                        default => 'warning',
                    })
                    ->sortable(),
                TextColumn::make('period_start')
                    ->label('Period')
                    ->formatStateUsing(fn (PayoutBatch $r): string => $r->period_start && $r->period_end
                        ? $r->period_start->format('d M') . ' – ' . $r->period_end->format('d M')
                        : '—')
                    ->placeholder('—'),
                TextColumn::make('reference')
                    ->label('Reference')
                    ->placeholder('—')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('processed_at')
                    ->label('Paid on')
                    ->dateTime('d M Y H:i')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'processing' => 'Processing',
                        'paid' => 'Paid',
                        'failed' => 'Failed',
                    ]),
                SelectFilter::make('partner_id')
                    ->label('Partner')
                    ->relationship('partner', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('markPaid')
                    ->label('Mark paid')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->visible(fn (PayoutBatch $r): bool => ! $r->isPaid())
                    ->schema([
                        TextInput::make('reference')
                            ->label('Reference / UTR')
                            ->required()
                            ->maxLength(120)
                            ->belowContent('Shown to the partner so they can trace the transfer.'),
                    ])
                    ->fillForm(fn (PayoutBatch $r): array => ['reference' => $r->reference])
                    ->modalHeading('Mark settlement as paid')
                    ->modalDescription(fn (PayoutBatch $r): string => 'Confirms ' . number_format((float) $r->amount, 2)
                        . ' has left our account for ' . ($r->partner?->name ?: 'this partner') . '.')
                    ->action(function (PayoutBatch $r, array $data): void {
                        $r->update([
                            'status' => 'paid',
                            'reference' => $data['reference'],
                            'processed_at' => now(),
                        ]);

                        AdminAction::log('payout_batch.paid', [
                            'batch_id' => $r->id,
                            'partner_id' => $r->partner_id,
                            'amount' => $r->amount,
                            'reference' => $data['reference'],
                        ]);

                        Notification::make()->title('Settlement marked paid')->success()->send();
                    }),

                Action::make('markFailed')
                    ->label('Mark failed')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->visible(fn (PayoutBatch $r): bool => ! $r->isPaid() && strtolower((string) $r->status) !== 'failed')
                    ->schema([
                        Textarea::make('note')
                            ->label('What went wrong?')
                            ->rows(2)
                            ->maxLength(500),
                    ])
                    ->modalHeading('Mark settlement as failed')
                    ->modalDescription('The amount returns to the partner’s available balance.')
                    ->action(function (PayoutBatch $r, array $data): void {
                        $r->update([
                            'status' => 'failed',
                            'note' => $data['note'] ?? $r->note,
                        ]);

                        AdminAction::log('payout_batch.failed', [
                            'batch_id' => $r->id,
                            'partner_id' => $r->partner_id,
                            'amount' => $r->amount,
                        ]);

                        Notification::make()->title('Settlement marked failed')->warning()->send();
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No settlements yet')
            ->emptyStateDescription('Create one to pay a partner what they have collected.');
    }

    /** Masked destination for the row description, so the desk can eyeball it. */
    private static function destination(PayoutBatch $record): string
    {
        $account = PartnerPayoutAccount::query()->where('partner_id', $record->partner_id)->first();

        return $account?->summaryLine() ?: 'no settlement account on file';
    }
}
