<?php

declare(strict_types=1);

namespace App\Filament\Resources\Partners\Widgets;

use App\Models\Booking;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Headline numbers for a single partner's overview page — what they run and
 * what they earn. Revenue is the sum of paid bookings across BOTH their events
 * (event organisers) and their venues (venue owners), so the same tile grid
 * works for either partner type.
 *
 * Booking statuses are stored inconsistently (CONFIRMED / confirmed / PAID …)
 * so every filter matches case-insensitively — see [[status-casing-landmine]].
 */
class PartnerOverviewStatsWidget extends StatsOverviewWidget
{
    /** Injected by ViewPartner via getWidgetData(). */
    public ?User $record = null;

    protected int | string | array $columnSpan = 'full';

    /** Statuses that represent money actually earned. */
    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    /** Statuses that represent lost / reversed revenue. */
    private const LOST = ['cancelled', 'refunded', 'failed'];

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $partner = $this->record;

        if (! $partner) {
            return [];
        }

        $eventIds = $partner->events()->pluck('id')->all();
        $venueIds = $partner->venues()->pluck('id')->all();

        // Every booking that belongs to this partner — their events OR venues.
        $owned = fn (): Builder => Booking::query()->where(function (Builder $q) use ($eventIds, $venueIds): void {
            $q->when($eventIds !== [], fn (Builder $b) => $b->orWhereIn('event_id', $eventIds));
            $q->when($venueIds !== [], fn (Builder $b) => $b->orWhereIn('venue_id', $venueIds));
            if ($eventIds === [] && $venueIds === []) {
                $q->whereRaw('1 = 0'); // nothing owned → no revenue
            }
        });

        $paid = fn (): Builder => $owned()->whereIn(DB::raw('lower(status)'), self::PAID);

        $revenue  = (float) $paid()->sum('total_amount');
        $orders   = (int) $paid()->count();
        $tickets  = (int) $paid()->sum('quantity');
        $avg      = $orders > 0 ? $revenue / $orders : 0.0;

        $monthRevenue = (float) $paid()
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('total_amount');

        $lost      = $owned()->whereIn(DB::raw('lower(status)'), self::LOST);
        $lostCount = (int) (clone $lost)->count();
        $lostValue = (float) (clone $lost)->sum('total_amount');

        // Live listings: published events + active venues they currently run.
        $liveEvents = (int) $partner->events()
            ->whereRaw("lower(status) = 'published'")->count();
        $liveVenues = (int) $partner->venues()
            ->where('is_active', true)->count();
        $totalEvents = count($eventIds);
        $totalVenues = count($venueIds);

        // Distinct paying customers reached.
        $customers = (int) $paid()->distinct('user_id')->count('user_id');

        $listingsChip = trim(implode(' · ', array_filter([
            $totalEvents > 0 ? "{$liveEvents}/{$totalEvents} events live" : null,
            $totalVenues > 0 ? "{$liveVenues}/{$totalVenues} venues active" : null,
        ]))) ?: 'Nothing listed yet';

        return [
            Stat::make('Total revenue', $this->money($revenue))
                ->description($orders . ' paid ' . str('order')->plural($orders))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('This month', $this->money($monthRevenue))
                ->description(now()->format('F Y'))
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary'),

            Stat::make('Tickets / bookings sold', number_format($tickets))
                ->description('Avg ' . $this->money($avg) . ' per order')
                ->descriptionIcon('heroicon-m-ticket')
                ->color('info'),

            Stat::make('Live listings', number_format($liveEvents + $liveVenues))
                ->description($listingsChip)
                ->descriptionIcon('heroicon-m-signal')
                ->color('info'),

            Stat::make('Customers reached', number_format($customers))
                ->description('Distinct paying accounts')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary'),

            Stat::make('Refunds / cancelled', number_format($lostCount))
                ->description($this->money($lostValue) . ' reversed')
                ->descriptionIcon('heroicon-m-arrow-uturn-left')
                ->color($lostCount > 0 ? 'danger' : 'gray'),
        ];
    }

    private function money(float $amount): string
    {
        return '₹' . number_format($amount);
    }
}
