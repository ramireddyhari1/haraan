<?php

declare(strict_types=1);

namespace App\Filament\Resources\Venues\Widgets;

use App\Models\Booking;
use App\Models\Venue;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * Per-venue owner analytics — the GameHub twin of EventAnalyticsStatsWidget.
 * All figures come from real venue-slot bookings; statuses are matched
 * case-insensitively (CONFIRMED / confirmed / PAID …).
 */
class VenueAnalyticsStatsWidget extends StatsOverviewWidget
{
    /** Injected by the page via InteractsWithRecord::getWidgetData(). */
    public ?Venue $record = null;

    protected int | string | array $columnSpan = 'full';

    private const PAID = ['confirmed', 'paid', 'completed'];

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $venue = $this->record;

        if (! $venue) {
            return [];
        }

        $paid = Booking::where('booking_type', 'venue')
            ->where('venue_id', $venue->id)
            ->whereIn(DB::raw('lower(status)'), self::PAID);

        $revenue   = (float) (clone $paid)->sum('total_amount');
        $bookings  = (int) (clone $paid)->count();
        $checkedIn = (int) (clone $paid)->sum('checked_in_count');
        $avg       = $bookings > 0 ? $revenue / $bookings : 0.0;

        // Utilization (est.): booked slots in the last 30 days vs the weekly slot
        // templates offered (each recurs ~4.3×/month). A rough occupancy signal
        // until date-level slot capacity exists.
        $slotsOffered   = (int) $venue->slots()->count();
        $weeklyCapacity = (int) $venue->slots()->sum('capacity');
        $bookings30d  = (int) Booking::where('booking_type', 'venue')
            ->where('venue_id', $venue->id)
            ->whereIn(DB::raw('lower(status)'), self::PAID)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        // Each weekly slot occurs ~4.3×/month; capacity is total spots across slots.
        $capacity30d  = (int) round($weeklyCapacity * 4.3);
        $utilization  = $capacity30d > 0 ? min(100, (int) round($bookings30d / $capacity30d * 100)) : 0;

        // Upcoming confirmed bookings (from today).
        $upcoming = (int) Booking::where('booking_type', 'venue')
            ->where('venue_id', $venue->id)
            ->whereIn(DB::raw('lower(status)'), self::PAID)
            ->whereDate('slot_date', '>=', now()->toDateString())
            ->count();

        // Repeat-customer rate.
        $distinctUsers = (int) (clone $paid)->distinct('user_id')->count('user_id');
        $repeatUsers = (int) Booking::where('booking_type', 'venue')
            ->where('venue_id', $venue->id)
            ->whereIn(DB::raw('lower(status)'), self::PAID)
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();
        $repeatRate = $distinctUsers > 0 ? (int) round($repeatUsers / $distinctUsers * 100) : 0;

        $showUp = $bookings > 0 ? (int) round($checkedIn / $bookings * 100) : 0;

        return [
            Stat::make('Total Revenue', '₹' . number_format($revenue))
                ->description("$bookings paid " . str('booking')->plural($bookings))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Avg Booking Value', '₹' . number_format($avg))
                ->description('Revenue ÷ bookings')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('primary'),

            Stat::make('Utilization', $utilization . '%')
                ->description("$bookings30d booked / ~$capacity30d slots (30d)")
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color($utilization >= 70 ? 'success' : ($utilization >= 40 ? 'warning' : 'gray')),

            Stat::make('Upcoming', number_format($upcoming))
                ->description('Confirmed, from today')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('info'),

            Stat::make('Checked In', number_format($checkedIn))
                ->description($showUp . '% show-up rate')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color($showUp >= 80 ? 'success' : ($showUp > 0 ? 'warning' : 'gray')),

            Stat::make('Repeat Customers', $repeatRate . '%')
                ->description("$repeatUsers of $distinctUsers came back")
                ->descriptionIcon('heroicon-m-arrow-path-rounded-square')
                ->color($repeatRate >= 30 ? 'success' : 'gray'),

            Stat::make('Rating', $venue->rating ? number_format((float) $venue->rating, 1) : '—')
                ->description(((int) $venue->reviews_count) . ' ' . str('review')->plural((int) $venue->reviews_count))
                ->descriptionIcon('heroicon-m-star')
                ->color('warning'),

            Stat::make('Slots Offered', number_format($slotsOffered))
                ->description($venue->is_bookable ? 'Open for booking' : 'Not bookable')
                ->descriptionIcon('heroicon-m-clock')
                ->color($venue->is_bookable ? 'primary' : 'gray'),
        ];
    }
}
