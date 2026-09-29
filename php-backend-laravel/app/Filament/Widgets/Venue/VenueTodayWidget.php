<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Venue;

use App\Filament\Widgets\Venue\Concerns\ScopesToVenueLane;
use App\Models\Booking;
use Illuminate\Support\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * The four numbers a venue owner checks before they've finished their chai:
 * today's revenue, today's bookings, how full the day is, and how many people
 * are actually still active.
 *
 * Revenue here is money **collected** (`amount_paid`), not money invoiced — a
 * ₹4,400 booking with a ₹500 advance contributes ₹500. That is the whole point
 * of the payment ledger; showing `total_amount` would overstate the day by
 * whatever hasn't been paid yet.
 */
class VenueTodayWidget extends StatsOverviewWidget
{
    use ScopesToVenueLane;

    protected static ?int $sort = -3;

    protected static bool $isLazy = false;

    // A desk glances at this; a minute is fresh enough and spares the server a
    // query burst every five seconds per open tab.
    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        // Today's money is the dashboard hero's headline (same ledger, same
        // day), so this row starts at the sheet rather than repeating it.
        $today = Carbon::today();

        $bookingsToday = (clone $this->liveBookings())->whereDate('slot_date', $today)->count();
        $started = (clone $this->liveBookings())
            ->whereDate('slot_date', $today)
            ->whereNotNull('checked_in_at')
            ->count();

        [$occupancy, $sold, $sellable] = $this->occupancyToday($today);

        $activeCustomers = (clone $this->liveBookings())
            ->where('slot_date', '>=', $today->copy()->subDays(30))
            ->distinct()
            ->count(DB::raw('coalesce(guest_phone, user_id)'));

        return [
            Stat::make("Today's bookings", (string) $bookingsToday)
                ->description($bookingsToday === 0
                    ? 'Nothing on the sheet yet'
                    : sprintf('%d checked in · %d to come', $started, max(0, $bookingsToday - $started)))
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary'),

            Stat::make('Occupancy', $sellable > 0 ? $occupancy . '%' : '—')
                ->description($sellable > 0
                    // "court-hours" for a turf, "table-hours" for a café.
                    ? sprintf('%d of %d %s sold', $sold, $sellable, $this->resourceHours())
                    : 'Set opening hours to measure this')
                ->descriptionIcon('heroicon-m-squares-2x2')
                ->color(match (true) {
                    $sellable === 0 => 'gray',
                    $occupancy >= 75 => 'success',
                    $occupancy >= 50 => 'warning',
                    default => 'danger',
                }),

            Stat::make('Active customers', (string) $activeCustomers)
                ->description('Booked in the last 30 days')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),
        ];
    }

    /**
     * Resource-hours sold today over resource-hours sellable today — court-hours
     * on a turf, table-hours in a café.
     *
     * Sellable = active courts × the venue's open hours. It is deliberately a
     * rough denominator: a precise one needs the pricing/hours rework, and a
     * roughly-right occupancy number today beats a perfect one next quarter.
     *
     * @return array{0:int,1:int,2:int}  [percent, sold, sellable]
     */
    private function occupancyToday(Carbon $day): array
    {
        $venues = $this->venues();

        if ($venues->isEmpty()) {
            return [0, 0, 0];
        }

        $sellable = 0;

        foreach ($venues as $venue) {
            $courts = max(1, $venue->courts()->where('is_active', true)->count());
            $sellable += $courts * $this->openHoursFor($venue, $day);
        }

        $sold = (int) (clone $this->liveBookings())
            ->whereDate('slot_date', $day)
            ->get(['start_time', 'end_time'])
            ->sum(fn (Booking $b): int => $this->hoursBetween($b->start_time, $b->end_time));

        $pct = $sellable > 0 ? (int) round(min(100, $sold / $sellable * 100)) : 0;

        return [$pct, $sold, $sellable];
    }

    /** Open hours for a venue on a day; falls back to a 14-hour day. */
    private function openHoursFor($venue, Carbon $day): int
    {
        if (method_exists($venue, 'isOpenOn') && ! $venue->isOpenOn($day)) {
            return 0;
        }

        return 14;
    }

    /** Duration of a booking in whole hours, defaulting to 1 when unknown. */
    private function hoursBetween(?string $start, ?string $end): int
    {
        if ($start === null || $end === null) {
            return 1;
        }

        $s = strtotime($start);
        $e = strtotime($end);

        if ($s === false || $e === false || $e <= $s) {
            return 1;
        }

        return max(1, (int) round(($e - $s) / 3600));
    }
}
