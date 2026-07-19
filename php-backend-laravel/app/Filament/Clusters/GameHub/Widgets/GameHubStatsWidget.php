<?php

declare(strict_types=1);

namespace App\Filament\Clusters\GameHub\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GameHubStatsWidget extends StatsOverviewWidget
{
    use \App\Filament\Concerns\ScopesToPartnerVenues;

    protected static ?int $sort = -2;

    // Eager: also used on the short partner dashboard where a lazy widget would
    // never intersect to load.
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $venues = $this->scopedVenueQuery()->count();
        $bookable = $this->scopedVenueQuery()->where('is_bookable', true)->where('is_active', true)->count();
        $totalSlots = $this->scopedSlotQuery()->count();
        $booked = $this->scopedSlotQuery()->where('is_available', false)->count();
        $fillingFast = $this->scopedSlotQuery()->where('filling_fast', true)->where('is_available', true)->count();
        $occupancy = $totalSlots > 0 ? round($booked / $totalSlots * 100) : 0;

        return [
            Stat::make('Active Venues', (string) $bookable)
                ->description("$venues total in catalog")
                ->descriptionIcon('heroicon-m-map-pin')
                ->color('success'),
            Stat::make('Occupancy', $occupancy . '%')
                ->description("$booked of $totalSlots slots booked")
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color($occupancy >= 70 ? 'danger' : ($occupancy >= 40 ? 'warning' : 'info')),
            Stat::make('Open Slots', (string) ($totalSlots - $booked))
                ->description('Available right now')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),
            Stat::make('Filling Fast', (string) $fillingFast)
                ->description('Slots flagged urgent')
                ->descriptionIcon('heroicon-m-fire')
                ->color($fillingFast > 0 ? 'warning' : 'gray'),
        ];
    }
}
