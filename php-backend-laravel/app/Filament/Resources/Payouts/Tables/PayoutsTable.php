<?php

namespace App\Filament\Resources\Payouts\Tables;

use App\Models\AdminAction;
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
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('booking.id')
                    ->label('Booking')
                    ->searchable(),
                TextColumn::make('amount')
                    ->money('INR')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match (strtolower($state)) {
                        'processed', 'paid' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('processed_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('status')
                    ->options(['pending' => 'Pending', 'processed' => 'Processed', 'failed' => 'Failed']),
            ])
            ->recordActions([
                Action::make('process')
                    ->label('Process')
                    ->icon('heroicon-m-banknotes')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Mark this payout as processed and stamp the time?')
                    ->visible(fn ($record): bool => strtolower((string) $record->status) !== 'processed')
                    ->action(function ($record): void {
                        $record->update(['status' => 'processed', 'processed_at' => now()]);
                        AdminAction::log('payout.processed', ['payout_id' => $record->id, 'amount' => $record->amount]);
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
