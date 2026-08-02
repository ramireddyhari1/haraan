<?php

declare(strict_types=1);

namespace App\Filament\Resources\VenueBookings\Pages;

use App\Filament\Resources\VenueBookings\VenueBookingResource;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Tabs are date/lifecycle based — never payment status. Payment lives in its own
 * column and filter, because "is this booking happening" and "have we been paid"
 * are two different questions and conflating them is the habit this whole phase
 * exists to break.
 *
 * The one exception is "Balance due", which is not a date bucket but the working
 * list a front desk actually opens in the morning.
 */
class ListVenueBookings extends ListRecords
{
    protected static string $resource = VenueBookingResource::class;

    private const LIVE = ['confirmed', 'paid', 'completed', 'checked_in'];

    public function getTabs(): array
    {
        return [
            'due' => Tab::make('Balance due')
                ->icon('heroicon-m-banknotes')
                ->badge(VenueBookingResource::outstanding()->count() ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereIn(DB::raw('lower(status)'), self::LIVE)
                    ->whereNotIn('payment_status', ['refunded', 'part_refunded'])
                    ->whereRaw('coalesce(amount_paid, 0) < total_amount')),

            'today' => Tab::make('Today')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereDate('slot_date', today())),

            'upcoming' => Tab::make('Upcoming')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereDate('slot_date', '>', today())),

            'past' => Tab::make('Past')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereDate('slot_date', '<', today())),

            'all' => Tab::make('All'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        // Open on the money when there is money to chase; otherwise on the day.
        return VenueBookingResource::outstanding()->exists() ? 'due' : 'today';
    }
}
