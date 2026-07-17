<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The first thing an operator sees when they open /control — deliberately money-first,
 * the way Razorpay leads with settlements and collected amount rather than a list of
 * records. One hero number per card, a 7-day sparkline, a same-window trend, and copy
 * that reads calmly when the number is zero instead of looking broken.
 */
class HaraanStatsWidget extends StatsOverviewWidget
{
    use \App\Filament\Concerns\RefreshesOnContentUpdate;

    // Dashboard order is deliberate and spaced by 10 (KPIs → money → growth → live →
    // recent) so widgets read as one narrative and new ones slot in without collisions.
    protected static ?int $sort = -50;

    /** Booking statuses that represent real, collected money (status casing is mixed in the DB). */
    private const PAID_STATUSES = ['confirmed', 'paid', 'completed', 'checked_in'];

    protected function getStats(): array
    {
        $now = Carbon::now();
        $windowStart = $now->copy()->subDays(6)->startOfDay(); // last 7 days inclusive
        $prevStart = $now->copy()->subDays(13)->startOfDay();
        $prevEnd = $now->copy()->subDays(7)->endOfDay();

        // Pull the paid bookings once, split the work in PHP so the same query serves
        // revenue, count and the sparkline without three round-trips or DB-specific SQL.
        $paid = Booking::query()
            ->whereIn('status', $this->paidStatusMatrix())
            ->where('created_at', '>=', $prevStart)
            ->get(['total_amount', 'created_at']);

        $thisWindow = $paid->filter(fn ($b) => $b->created_at >= $windowStart);
        $prevWindow = $paid->filter(fn ($b) => $b->created_at >= $prevStart && $b->created_at <= $prevEnd);

        $revenue = (float) $thisWindow->sum('total_amount');
        $prevRevenue = (float) $prevWindow->sum('total_amount');
        $bookingsCount = $thisWindow->count();
        $prevBookings = $prevWindow->count();

        $revenueSpark = $this->dailySeries($thisWindow, $windowStart, $now, fn ($rows) => (float) $rows->sum('total_amount'));
        $bookingsSpark = $this->dailySeries($thisWindow, $windowStart, $now, fn ($rows) => $rows->count());

        // Catalog health — not money, but the levers an operator pulls.
        $venues = Venue::count();
        $bookable = Venue::where('is_bookable', true)->count();
        $upcomingEvents = Event::query()
            ->whereDate('date', '>=', $now->toDateString())
            ->count();
        $liveEvents = Event::query()
            ->whereIn('status', ['published', 'PUBLISHED', 'Published'])
            ->count();

        // Active users — one heartbeat column (last_seen_at), so we report the
        // three windows it can answer honestly rather than a fabricated daily line.
        $onlineNow = User::where('last_seen_at', '>=', $now->copy()->subMinutes(5))->count();
        $dau = User::where('last_seen_at', '>=', $now->copy()->subDay())->count();
        $wau = User::where('last_seen_at', '>=', $now->copy()->subDays(7))->count();
        $mau = User::where('last_seen_at', '>=', $now->copy()->subDays(30))->count();

        return [
            $this->revenueStat($revenue, $prevRevenue, $revenueSpark),
            $this->bookingsStat($bookingsCount, $prevBookings, $bookingsSpark),
            $this->activeUsersStat($onlineNow, $dau, $wau, $mau),
            $this->venuesStat($venues, $bookable),
            $this->eventsStat($upcomingEvents, $liveEvents),
        ];
    }

    private function activeUsersStat(int $onlineNow, int $dau, int $wau, int $mau): Stat
    {
        if ($mau === 0) {
            return Stat::make('Active users · 24h', '0')
                ->description('No app activity recorded yet')
                ->descriptionIcon('heroicon-m-users')
                ->color('gray');
        }

        $stat = Stat::make('Active users · 24h', (string) $dau)
            ->description("{$wau} this week · {$mau} this month")
            ->descriptionIcon('heroicon-m-users')
            ->color($dau > 0 ? 'success' : 'gray');

        return $onlineNow > 0
            ? $stat->description("{$onlineNow} online now · {$wau} this week · {$mau} this month")
            : $stat;
    }

    private function revenueStat(float $revenue, float $prev, array $spark): Stat
    {
        [$deltaLabel, $color, $icon] = $this->trend($revenue, $prev);

        $stat = Stat::make('Revenue · last 7 days', '₹' . $this->money($revenue))
            ->descriptionIcon($icon)
            ->chart($spark)
            ->color($color);

        return $revenue > 0
            ? $stat->description($deltaLabel . ' vs previous 7 days')
            : $stat->description('No payments collected yet')->color('gray')->descriptionIcon('heroicon-m-banknotes');
    }

    private function bookingsStat(int $count, int $prev, array $spark): Stat
    {
        [$deltaLabel, $color, $icon] = $this->trend($count, $prev);

        $stat = Stat::make('Bookings · last 7 days', (string) $count)
            ->descriptionIcon($icon)
            ->chart($spark)
            ->color($color);

        return $count > 0
            ? $stat->description($deltaLabel . ' vs previous 7 days')
            : $stat->description('No bookings in this window')->color('gray')->descriptionIcon('heroicon-m-calendar-days');
    }

    private function venuesStat(int $venues, int $bookable): Stat
    {
        if ($venues === 0) {
            return Stat::make('Venues', '0')
                ->description('Add your first venue to go live')
                ->descriptionIcon('heroicon-m-map-pin')
                ->color('gray');
        }

        return Stat::make('Venues', (string) $venues)
            ->description("{$bookable} open for booking")
            ->descriptionIcon('heroicon-m-map-pin')
            ->color('success');
    }

    private function eventsStat(int $upcoming, int $live): Stat
    {
        if ($upcoming === 0) {
            return Stat::make('Upcoming events', '0')
                ->description($live > 0 ? "{$live} published, none upcoming" : 'Nothing scheduled')
                ->descriptionIcon('heroicon-m-ticket')
                ->color('gray');
        }

        return Stat::make('Upcoming events', (string) $upcoming)
            ->description('Scheduled from today')
            ->descriptionIcon('heroicon-m-ticket')
            ->color('primary');
    }

    /**
     * Build a per-day series across the window so the sparkline always has one point per
     * day (zero-filled), rather than a jagged line that skips empty days.
     *
     * @param  Collection<int,\App\Models\Booking>  $rows
     * @return array<int,int|float>
     */
    private function dailySeries(Collection $rows, Carbon $start, Carbon $end, callable $reduce): array
    {
        $byDay = $rows->groupBy(fn ($b) => $b->created_at->toDateString());

        $series = [];
        $cursor = $start->copy();
        while ($cursor <= $end) {
            $key = $cursor->toDateString();
            $series[] = $reduce($byDay->get($key, collect()));
            $cursor->addDay();
        }

        return $series;
    }

    /**
     * @return array{0:string,1:string,2:string} [label, color, icon]
     */
    private function trend(float $current, float $previous): array
    {
        if ($previous <= 0) {
            return $current > 0
                ? ['New activity', 'success', 'heroicon-m-arrow-trending-up']
                : ['No change', 'gray', 'heroicon-m-minus-small'];
        }

        $pct = (int) round((($current - $previous) / $previous) * 100);

        if ($pct > 0) {
            return ["+{$pct}%", 'success', 'heroicon-m-arrow-trending-up'];
        }
        if ($pct < 0) {
            return ["{$pct}%", 'danger', 'heroicon-m-arrow-trending-down'];
        }

        return ['0%', 'gray', 'heroicon-m-minus-small'];
    }

    /** ₹ with grouping and no trailing .00 noise for whole rupees. */
    private function money(float $amount): string
    {
        return $amount == floor($amount)
            ? number_format($amount)
            : number_format($amount, 2);
    }

    /** Case-insensitive match list for the mixed-case status column. */
    private function paidStatusMatrix(): array
    {
        $matrix = [];
        foreach (self::PAID_STATUSES as $s) {
            $matrix[] = $s;
            $matrix[] = strtoupper($s);
            $matrix[] = ucfirst($s);
        }

        return array_values(array_unique($matrix));
    }
}
