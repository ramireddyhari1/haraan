<?php

namespace App\Filament\Resources\Payouts\Tables;

use App\Filament\Resources\Payouts\Schemas\PayoutForm;
use App\Models\AdminAction;
use App\Models\Payout;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PayoutsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['booking.user:id,name', 'booking.event:id,title', 'booking.venue:id,name']))
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('booking.id')
                    ->label('Booking')
                    ->formatStateUsing(fn ($state): string => '#' . $state)
                    ->description(fn (Payout $record): ?string => $record->booking ? PayoutForm::forLabel($record->booking) . ' · ' . PayoutForm::customerName($record->booking) : null)
                    ->searchable(),
                TextColumn::make('amount')
                    ->money('INR')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ucfirst(strtolower((string) $state)))
                    ->color(fn (string $state): string => match (strtolower($state)) {
                        'processed', 'paid' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('method')
                    ->label('Via')
                    ->formatStateUsing(fn (?string $state): string => Payout::METHODS[strtolower((string) $state)] ?? (string) $state)
                    ->description(fn (Payout $record): ?string => $record->reference)
                    ->placeholder('—')
                    ->searchable(['reference']),
                TextColumn::make('processed_at')
                    ->label('Paid on')
                    ->dateTime('d M Y, h:i A', \App\Support\BusinessClock::zone())
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('status')
                    ->options(Payout::STATUSES)
                    // Older rows are stored upper-case; match either.
                    ->query(fn ($query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereRaw('lower(status) = ?', [$data['value']])
                        : $query),
                \Filament\Tables\Filters\SelectFilter::make('method')
                    ->label('Paid via')
                    ->options(Payout::METHODS),
            ])
            ->recordActions([
                Action::make('process')
                    ->label('Process')
                    ->icon('heroicon-m-banknotes')
                    ->color('success')
                    ->modalHeading('Mark payout as paid')
                    ->modalDescription(fn (Payout $record): string => 'Record that ' . \App\Services\PartnerSettlement::inr((float) $record->amount) . ' has been sent to the partner.')
                    ->schema([
                        ToggleButtons::make('method')->label('Paid via')->options(Payout::METHODS)->inline()->required()->live(),
                        TextInput::make('reference')->label('Reference / UTR')->maxLength(120)
                            ->required(fn (Get $get): bool => in_array($get('method'), ['upi', 'bank'], true)),
                    ])
                    ->visible(fn ($record): bool => strtolower((string) $record->status) !== 'processed')
                    ->action(function (Payout $record, array $data): void {
                        $record->update([
                            'status' => 'processed',
                            'processed_at' => now(),
                            'method' => $data['method'],
                            'reference' => filled($data['reference'] ?? null) ? trim($data['reference']) : null,
                        ]);
                        AdminAction::log('payout.processed', ['payout_id' => $record->id, 'amount' => $record->amount, 'method' => $data['method']], $record);
                        Notification::make()->title('Payout processed')->success()->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
