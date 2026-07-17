<?php

declare(strict_types=1);

namespace App\Filament\Resources\Events\Widgets;

use App\Models\Event;
use App\Models\EventView;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Filament\Widgets\Widget;

/**
 * Real Views analytics from the event_views table (recorded per detail open by
 * EventViewRecorder) — total, unique & returning visitors, recency (today/week/month),
 * peak hour, a 14-day trend, and traffic-source + device breakdowns. Everything here is
 * measured, not a counter guess. Read-only; record injected by the page.
 */
class EventViewsWidget extends Widget
{
    public ?Event $record = null;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.resources.events.widgets.event-views';

    /**
     * @return array<string,mixed>
     */
    public function getData(): array
    {
        $event = $this->record;
        if (! $event) {
            return ['total' => 0];
        }

        $base = fn () => EventView::where('event_id', $event->id);

        $total = (int) $base()->count();
        if ($total === 0) {
            return ['total' => 0];
        }

        $unique = (int) $base()->distinct('visitor_key')->count('visitor_key');

        // Returning = visitors seen more than once.
        $returning = (int) DB::table('event_views')
            ->where('event_id', $event->id)
            ->select('visitor_key')
            ->groupBy('visitor_key')
            ->havingRaw('count(*) > 1')
            ->get()->count();
        $returningPct = $unique > 0 ? (int) round($returning / $unique * 100) : 0;

        $today = (int) $base()->where('created_at', '>=', now()->startOfDay())->count();
        $week = (int) $base()->where('created_at', '>=', now()->startOfWeek())->count();
        $month = (int) $base()->where('created_at', '>=', now()->startOfMonth())->count();

        // Peak hour across all views.
        $hours = $base()->pluck('created_at')->filter()
            ->map(fn ($t) => (int) Carbon::parse($t)->format('G'))->countBy();
        $peakHour = $hours->isEmpty() ? null : (int) $hours->sortDesc()->keys()->first();

        return [
            'total' => $total,
            'unique' => $unique,
            'returningPct' => $returningPct,
            'today' => $today,
            'week' => $week,
            'month' => $month,
            'peak' => $peakHour === null ? '—' : $this->hourLabel($peakHour) . '–' . $this->hourLabel($peakHour + 1),
            'daily' => $this->dailySeries($event),
            'sources' => $this->breakdown($event, 'source', $total),
            'devices' => $this->breakdown($event, 'device', $total),
        ];
    }

    /** @return array<int,array{day:string,count:int,pct:int}> last 14 days, zero-filled */
    private function dailySeries(Event $event): array
    {
        $rows = EventView::where('event_id', $event->id)
            ->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->pluck('created_at')
            ->groupBy(fn ($t) => Carbon::parse($t)->toDateString())
            ->map->count();

        $max = max(1, (int) $rows->max());
        $series = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = now()->subDays($i);
            $c = (int) ($rows[$d->toDateString()] ?? 0);
            $series[] = ['day' => $d->format('d M'), 'count' => $c, 'pct' => (int) round($c / $max * 100)];
        }

        return $series;
    }

    /** @return array<int,array{label:string,count:int,pct:int}> */
    private function breakdown(Event $event, string $column, int $total): array
    {
        return EventView::where('event_id', $event->id)
            ->selectRaw("{$column} as k, count(*) as c")
            ->groupBy($column)->orderByDesc('c')->get()
            ->map(fn ($r) => [
                'label' => ucfirst((string) $r->k),
                'count' => (int) $r->c,
                'pct' => $total > 0 ? (int) round($r->c / $total * 100) : 0,
            ])->all();
    }

    private function hourLabel(int $h): string
    {
        $h = ($h + 24) % 24;
        $suffix = $h < 12 ? 'AM' : 'PM';
        $display = $h % 12 === 0 ? 12 : $h % 12;

        return $display . ' ' . $suffix;
    }
}
