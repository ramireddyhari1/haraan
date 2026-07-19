<?php

declare(strict_types=1);

namespace App\Filament\Resources\Events\Widgets;

use App\Models\Event;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * KPI header for the Events list — the catalogue at a glance before the rows:
 * how many events exist, how many are live, how many are still to come, and how
 * many tickets have gone against total capacity. Same "frame the table with
 * tiles" treatment as the Users/Partners lists. All cheap counts on real
 * columns (status casing is mixed in the DB, so match case-insensitively).
 *
 * NB: distinct from the per-event analytics widgets in this folder — this one
 * summarises the whole list, not a single record.
 */
class EventsListStatsWidget extends StatsOverviewWidget
{
    use \App\Filament\Concerns\RefreshesOnContentUpdate;

    protected function getStats(): array
    {
        $total = Event::count();
        $published = Event::whereRaw("lower(status) = 'published'")->count();
        $draft = Event::whereRaw("lower(status) = 'draft'")->count();
        $upcoming = Event::query()
            ->whereRaw("lower(status) = 'published'")
            ->whereDate('date', '>=', now()->toDateString())
            ->count();

        $sold = (int) Event::query()
            ->selectRaw('coalesce(sum(total_slots - available_slots), 0) as s')
            ->value('s');
        $capacity = (int) Event::sum('total_slots');
        $fillPct = $capacity > 0 ? (int) round($sold / $capacity * 100) : 0;

        return [
            Stat::make('Total events', number_format($total))
                ->description($total > 0 ? "{$published} published · {$draft} draft" : 'No events yet')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary'),

            Stat::make('Published', number_format($published))
                ->description($upcoming > 0 ? "{$upcoming} still upcoming" : 'none upcoming')
                ->descriptionIcon('heroicon-m-signal')
                ->color($published > 0 ? 'success' : 'gray'),

            Stat::make('Tickets sold', number_format($sold))
                ->description($capacity > 0 ? "{$fillPct}% of " . number_format($capacity) . ' capacity' : 'no capacity set')
                ->descriptionIcon('heroicon-m-ticket')
                ->color($sold > 0 ? 'success' : 'gray'),
        ];
    }
}
