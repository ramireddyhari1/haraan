<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Ad;
use App\Models\Booking;
use App\Models\Event;
use App\Models\FeedItem;
use App\Models\Venue;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class HaraanStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -2;

    protected function getStats(): array
    {
        $venues = Venue::count();
        $bookable = Venue::where('is_bookable', true)->count();
        $activeAds = Ad::where('is_active', true)->count();
        $feed = FeedItem::where('is_active', true)->count();
        $events = Event::count();
        $bookings = class_exists(Booking::class) ? Booking::count() : 0;

        return [
            Stat::make('Venues', (string) $venues)
                ->description("$bookable bookable")
                ->descriptionIcon('heroicon-m-map-pin')
                ->color('success'),
            Stat::make('Active Ads', (string) $activeAds)
                ->description('In the home feed')
                ->descriptionIcon('heroicon-m-megaphone')
                ->color('warning'),
            Stat::make('Feed Cards', (string) $feed)
                ->description('For You + Trending')
                ->descriptionIcon('heroicon-m-squares-2x2')
                ->color('info'),
            Stat::make('Events', (string) $events)
                ->description('Published catalog')
                ->descriptionIcon('heroicon-m-ticket')
                ->color('primary'),
            Stat::make('Bookings', (string) $bookings)
                ->description('All time')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('gray'),
        ];
    }
}
