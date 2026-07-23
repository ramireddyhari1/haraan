<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Partner;

use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Venues\VenueResource;
use App\Models\Booking;
use App\Models\Payout;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Money at a glance for a partner: what they've collected, what's already been
 * settled to them, and what's still pending. Collected is derived from the
 * partner's own PAID bookings (always accurate); settled comes from the payouts
 * ledger; pending is the difference.
 */
class PartnerEarningsStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -2;

    protected static bool $isLazy = false;

    /** Statuses that represent money actually collected. */
    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    /** Payout statuses that mean the partner has been paid. */
    private const SETTLED = ['paid', 'processed', 'completed'];

    /** Bookings belonging to this partner — their events OR their venues. */
    private function partnerBookings(): Builder
    {
        $query = Booking::query();

        if (Filament::getCurrentPanel()?->getId() === 'partner' && ! auth()->user()?->isSuperAdmin()) {
            $eventIds = EventResource::getEloquentQuery()->select('events.id');
            $venueIds = VenueResource::getEloquentQuery()->select('venues.id');

            $query->where(fn (Builder $q) => $q
                ->whereIn('event_id', $eventIds)
                ->orWhereIn('venue_id', $venueIds));
        }

        return $query;
    }

    protected function getStats(): array
    {
        $paid = fn (): Builder => $this->partnerBookings()->whereIn(DB::raw('lower(status)'), self::PAID);

        $collected = (float) $paid()->sum('total_amount');
        $collectedMonth = (float) (clone $paid())->where('created_at', '>=', now()->startOfMonth())->sum('total_amount');

        // Settled = payouts against this partner's bookings that have been paid out.
        $settled = (float) Payout::query()
            ->whereIn('booking_id', $this->partnerBookings()->select('bookings.id'))
            ->whereIn(DB::raw('lower(status)'), self::SETTLED)
            ->sum('amount');

        $pending = max($collected - $settled, 0);

        return [
            Stat::make('Collected', '₹' . number_format($collected))
                ->description('₹' . number_format($collectedMonth) . ' this month')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
            Stat::make('Settled to you', '₹' . number_format($settled))
                ->description('Paid out so far')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('info'),
            Stat::make('Pending', '₹' . number_format($pending))
                ->description('Awaiting settlement')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pending > 0 ? 'warning' : 'gray'),
        ];
    }
}
