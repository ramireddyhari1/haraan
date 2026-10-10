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
                    ->searchable()
                    ->sortable(),
                // Where to send it, in full: finance copies this into the bank or UPI app.
                TextColumn::make('send_to')
                    ->label('Send to')
                    ->state(fn (PayoutBatch $r): string => static::account($r)?->fullLine() ?? 'No settlement account on file')
                    ->description(fn (PayoutBatch $r): ?string => ($a = static::account($r))
                        ? strtoupper((string) $a->method) . ' · ' . ($a->account_holder ?: 'no name on file') . ' · ' . ($a->isVerified() ? 'verified' : 'not verified yet')
                        : null)
                    ->color(fn (PayoutBatch $r): ?string => static::account($r)?->isVerified() ? null : 'warning')
                    ->icon(fn (PayoutBatch $r): ?string => static::account($r)?->isVerified() ? 'heroicon-m-check-badge' : 'heroicon-m-exclamation-triangle')
                    ->iconColor(fn (PayoutBatch $r): string => static::account($r)?->isVerified() ? 'success' : 'warning')
                    ->copyable(fn (PayoutBatch $r): bool => static::account($r) !== null)
                    ->copyableState(fn (PayoutBatch $r): ?string => static::account($r)?->method === 'upi' ? static::account($r)?->upi_vpa : static::account($r)?->account_number)
                    ->copyMessage('Copied')
                    ->wrap(),
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
                    ->modalDescription(fn (PayoutBatch $r): string => 'Confirms ₹' . number_format((float) $r->amount, 2)
                        . ' has left our account for ' . ($r->partner?->name ?: 'this partner') . '. Sent to: '
                        . (static::account($r) ? static::account($r)->fullLine() . ' (' . (static::account($r)->account_holder ?: 'no name') . ')' : 'no settlement account on file') . '.')
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

    /** @var array<int, PartnerPayoutAccount|null> one lookup per partner per request */
    private static array $accounts = [];

    private static function account(PayoutBatch $record): ?PartnerPayoutAccount
    {
        $id = (int) $record->partner_id;

        return array_key_exists($id, static::$accounts)
            ? static::$accounts[$id]
            : (static::$accounts[$id] = PartnerPayoutAccount::query()->where('partner_id', $id)->first());
    }
}
