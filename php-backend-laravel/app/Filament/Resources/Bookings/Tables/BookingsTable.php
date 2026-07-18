<?php

namespace App\Filament\Resources\Bookings\Tables;

use App\Filament\Support\BookingTablePresenter;
use App\Models\AdminAction;
use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Eager-load the relations the "For" line reads, so the list isn't N+1.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user', 'event', 'venue']))
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                BookingTablePresenter::customerAvatarColumn(),
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->weight('bold')
                    ->description(fn (Booking $r): ?string => self::targetLine($r))
                    ->placeholder('Guest')
                    ->searchable(),
                TextColumn::make('booking_type')
                    ->label('For')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => strtolower((string) $state) === 'venue' ? 'Turf' : 'Event')
                    ->color(fn (?string $state): string => strtolower((string) $state) === 'venue' ? 'success' : 'primary'),
                TextColumn::make('quantity')
                    ->label('Qty')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->money('INR')
                    ->weight('bold')
                    ->alignEnd()
                    ->sortable(),
                BookingTablePresenter::statusColumn()->sortable(),
                TextColumn::make('coupon_code')
                    ->label('Coupon')
                    ->badge()
                    ->color('info')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('When')
                    ->since()
                    ->sortable()
                    ->tooltip(fn (Booking $r): string => $r->created_at->format('d M Y, H:i')),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'paid' => 'Paid',
                        'completed' => 'Completed',
                        'checked_in' => 'Checked in',
                        'cancelled' => 'Cancelled',
                        'refunded' => 'Refunded',
                        'failed' => 'Failed',
                    ])
                    // status casing is mixed in the DB (e.g. confirmed / CONFIRMED) — match
                    // case-insensitively so a filter never silently drops half the rows.
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereRaw('lower(status) = ?', [strtolower($data['value'])])
                        : $query),
                SelectFilter::make('booking_type')
                    ->label('Type')
                    ->options(['venue' => 'Turf', 'event' => 'Event'])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereRaw('lower(booking_type) = ?', [strtolower($data['value'])])
                        : $query),
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

    /** What was booked — event title, or venue + slot for a turf booking. */
    private static function targetLine(Booking $r): ?string
    {
        if (strtolower((string) $r->booking_type) === 'venue') {
            return trim(($r->venue?->name ?? 'Venue') . ($r->slot_label ? " · {$r->slot_label}" : ''));
        }

        return $r->event?->title;
    }
}
