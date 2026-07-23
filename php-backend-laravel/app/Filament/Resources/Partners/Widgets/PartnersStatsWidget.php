<?php

declare(strict_types=1);

namespace App\Filament\Resources\Partners\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * KPI header for the Partners list — total partners and the venue/event split,
 * plus how many are active. Same "frame the table with tiles" treatment as the
 * Users list. Partners are users with the PARTNER role.
 */
class PartnersStatsWidget extends StatsOverviewWidget
{
    use \App\Filament\Concerns\RefreshesOnContentUpdate;

    protected function getStats(): array
    {
        $partner = fn () => User::query()->whereRaw("upper(role) = 'PARTNER'");

        $total = $partner()->count();
        $venue = (clone $partner())->whereRaw("lower(partner_type) = 'venue'")->count();
        $event = (clone $partner())->whereRaw("lower(partner_type) = 'event'")->count();
        $active = (clone $partner())->whereRaw("lower(status) = 'active'")->count();

        return [
            Stat::make('Total partners', number_format($total))
                ->description($total > 0 ? "{$active} active" : 'No partners yet')
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('primary'),

            Stat::make('Venue owners', number_format($venue))
                ->description('turf & ground partners')
                ->descriptionIcon('heroicon-m-map-pin')
                ->color('info'),

            Stat::make('Event organisers', number_format($event))
                ->description('hosts & promoters')
                ->descriptionIcon('heroicon-m-ticket')
                ->color('info'),
        ];
    }
}
