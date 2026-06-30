<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Events\Widgets;

use App\Models\Booking;
use App\Models\Event;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EventsStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -2;

    protected function getStats(): array
    {
        $events = Event::count();
        $totalSeats = (int) Event::sum('total_slots');
        $available = (int) Event::sum('available_slots');
        $sold = max($totalSeats - $available, 0);
        $sellThrough = $totalSeats > 0 ? round($sold / $totalSeats * 100) : 0;
        $soldOut = Event::whereColumn('available_slots', '<=', 'total_slots')
            ->where('available_slots', '<=', 0)->count();
        $eventBookings = Booking::whereNotNull('event_id')->count();

        return [
            Stat::make('Events', (string) $events)
                ->description('In the catalog')
                ->descriptionIcon('heroicon-m-ticket')
                ->color('primary'),
            Stat::make('Tickets Sold', (string) $sold)
                ->description("of $totalSeats seats")
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success'),
            Stat::make('Sell-through', $sellThrough . '%')
                ->description('Seats sold vs capacity')
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color($sellThrough >= 80 ? 'danger' : ($sellThrough >= 50 ? 'warning' : 'info')),
            Stat::make('Sold Out', (string) $soldOut)
                ->description("$eventBookings bookings placed")
                ->descriptionIcon('heroicon-m-fire')
                ->color($soldOut > 0 ? 'warning' : 'gray'),
        ];
    }
}
