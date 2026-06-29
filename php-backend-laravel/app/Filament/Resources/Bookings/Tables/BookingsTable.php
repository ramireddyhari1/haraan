<?php

namespace App\Filament\Resources\Bookings\Tables;

use App\Models\AdminAction;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->weight('bold')
                    ->description(fn ($record) => $record->event?->title)
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label('Qty')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->money('INR')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match (strtolower($state)) {
                        'confirmed', 'paid', 'completed' => 'success',
                        'pending' => 'warning',
                        'cancelled', 'failed', 'refunded' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('coupon_code')
                    ->badge()
                    ->color('info')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->recordActions([
                Action::make('confirm')
                    ->label('Confirm')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => strtolower((string) $record->status) !== 'confirmed')
                    ->action(function ($record): void {
                        $record->update(['status' => 'confirmed']);
                        AdminAction::log('booking.confirmed', ['booking_id' => $record->id]);
                        Notification::make()->title('Booking confirmed')->success()->send();
                    }),
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => strtolower((string) $record->status) !== 'cancelled')
                    ->action(function ($record): void {
                        $record->update(['status' => 'cancelled']);
                        AdminAction::log('booking.cancelled', ['booking_id' => $record->id]);
                        Notification::make()->title('Booking cancelled')->warning()->send();
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
