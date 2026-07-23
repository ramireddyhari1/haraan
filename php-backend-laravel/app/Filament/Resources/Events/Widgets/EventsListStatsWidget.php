<?php

declare(strict_types=1);

namespace App\Filament\Resources\Events\Widgets;

use App\Filament\Resources\Events\EventResource;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;

/**
 * KPI header for the Events list — the catalogue at a glance before the rows:
 * how many events exist, how many are live/upcoming, and how many tickets have
 * gone against total capacity. Rendered as one compact BookMyShow-style summary
 * strip (three columns + a capacity fill bar) rather than three stacked stat
 * cards, so it stays dense on phones. All cheap counts on real columns (status
 * casing is mixed in the DB, so match case-insensitively).
 *
 * NB: distinct from the per-event analytics widgets in this folder — this one
 * summarises the whole list, not a single record.
 */
class EventsListStatsWidget extends Widget
{
    use \App\Filament\Concerns\RefreshesOnContentUpdate;

    protected string $view = 'filament.resources.events.widgets.events-list-stats';

    protected int | string | array $columnSpan = 'full';

    // Render eagerly: the compact summary strip has no skeleton, so a lazy
    // (x-intersect) placeholder would be zero-height and never trigger its own load.
    protected static bool $isLazy = false;

    /**
     * @return array{total:int, published:int, draft:int, upcoming:int, sold:int, capacity:int, fillPct:int}
     */
    public function getSummary(): array
    {
        // Base every count on the resource's SCOPED query so the partner console
        // only ever sums the partner's own events — matching the table below —
        // while /control (super-admin) still sees the whole catalogue.
        $base = fn (): Builder => EventResource::getEloquentQuery();

        $total = $base()->count();
        $published = $base()->whereRaw("lower(status) = 'published'")->count();
        $draft = $base()->whereRaw("lower(status) = 'draft'")->count();
        $upcoming = $base()
            ->whereRaw("lower(status) = 'published'")
            ->whereDate('date', '>=', now()->toDateString())
            ->count();

        $sold = (int) $base()
            ->selectRaw('coalesce(sum(total_slots - available_slots), 0) as s')
            ->value('s');
        $capacity = (int) $base()->sum('total_slots');
        $fillPct = $capacity > 0 ? (int) round($sold / $capacity * 100) : 0;

        return compact('total', 'published', 'draft', 'upcoming', 'sold', 'capacity', 'fillPct');
    }
}
