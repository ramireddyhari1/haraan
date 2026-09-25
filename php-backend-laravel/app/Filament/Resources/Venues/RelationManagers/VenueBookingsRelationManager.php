<?php

declare(strict_types=1);

namespace App\Filament\Resources\Venues\RelationManagers;

use App\Models\Booking;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Court-hour bookings taken here, read-only.
 *
 * Read-only on purpose: a booking is created through
 * {@see \App\Services\BookingService}, which runs the court-hour conflict engine,
 * and money against it moves only through {@see \App\Services\BookingLedger}.
 * A row typed in here would bypass both — it would double-book a court and
 * report a payment that no ledger entry backs. The Day bookings grid is the
 * place to take a booking; this is the place to answer questions about one.
 */
class VenueBookingsRelationManager extends RelationManager
{
    protected static string $relationship = 'bookings';

    protected static ?string $title = 'Bookings & payments';

    protected static string | \BackedEnum | null $icon = 'heroicon-o-banknotes';

    /** Statuses that represent money taken. Prod casing is mixed. */
    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $upcoming = $ownerRecord->bookings()
            ->whereIn(DB::raw('lower(status)'), self::PAID)
            ->whereDate('slot_date', '>=', now()->toDateString())
            ->count();

        return $upcoming > 0 ? (string) $upcoming : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ticket_code')
            ->defaultSort('slot_date', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user']))
            ->emptyStateHeading('No bookings yet')
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->columns([
                TextColumn::make('slot_date')
                    ->label('Date')
                    ->date('D d M Y')
                    ->description(fn (Booking $record): string => $record->slot_label
                        ?: implode('–', array_filter([$record->start_time, $record->end_time])))
                    ->sortable(),

                TextColumn::make('ticket_code')
                    ->label('Reference')
                    ->copyable()
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('customer')
                    ->label('Customer')
                    ->state(fn (Booking $record): string => $record->user?->name
                        ?: ($record->guest_name ?: 'Walk-in'))
                    ->description(fn (Booking $record): ?string => $record->guest_phone
                        ?: $record->user?->phone)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('guest_name', 'like', "%{$search}%")
                        ->orWhereHas('user', fn (Builder $q) => $q->where('name', 'like', "%{$search}%"))),

                TextColumn::make('channel')
                    ->label('Channel')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? ucfirst($state) : 'App')
                    ->color(fn (?string $state): string => $state === 'walk_in' ? 'warning' : 'gray'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ucfirst(strtolower((string) $state)))
                    ->color(fn (?string $state): string => match (strtolower((string) $state)) {
                        'confirmed', 'paid', 'completed', 'checked_in' => 'success',
                        'pending' => 'warning',
                        default => 'danger',
                    })
                    ->sortable(),

                TextColumn::make('total_amount')
                    ->label('Order total')
                    ->money('INR')
                    ->sortable(),

                TextColumn::make('amount_paid')
                    ->label('Collected')
                    ->money('INR')
                    ->description(fn (Booking $record): string => $record->payments()->count()
                        . ' ledger ' . str('entry')->plural($record->payments()->count()))
                    ->color('success'),

                TextColumn::make('balance_due')
                    ->label('Balance due')
                    ->state(fn (Booking $record): float => $record->balanceDue())
                    ->money('INR')
                    ->badge()
                    ->color(fn (Booking $record): string => $record->balanceDue() > 0.009 ? 'danger' : 'gray'),

                TextColumn::make('checked_in_count')
                    ->label('Checked in')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('razorpay_payment_id')
                    ->label('Gateway payment')
                    ->placeholder('Desk / offline')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'CONFIRMED' => 'Confirmed',
                        'PENDING'   => 'Pending',
                        'CANCELLED' => 'Cancelled',
                        'REFUNDED'  => 'Refunded',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereRaw('lower(status) = ?', [strtolower((string) $data['value'])])
                        : $query),

                Filter::make('upcoming')
                    ->label('Upcoming only')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereDate('slot_date', '>=', now()->toDateString())),

                Filter::make('owing')
                    ->label('Money still owed')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereColumn('amount_paid', '<', 'total_amount')),
            ], layout: FiltersLayout::AboveContent);
    }
}
