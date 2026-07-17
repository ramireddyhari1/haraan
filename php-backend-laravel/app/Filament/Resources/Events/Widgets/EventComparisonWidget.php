<?php

declare(strict_types=1);

namespace App\Filament\Resources\Events\Widgets;

use App\Models\Booking;
use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Filament\Widgets\Widget;

/**
 * This event vs. the host's previous event — the "are we doing better?" view. Picks the same
 * host's most recent earlier event (by date) and compares real metrics side by side with a delta.
 * Read-only; record injected by the page. Renders nothing when there's no earlier event to compare.
 */
class EventComparisonWidget extends Widget
{
    public ?Event $record = null;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.resources.events.widgets.event-comparison';

    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    public function hasComparison(): bool
    {
        return $this->previousEvent() !== null;
    }

    private ?Event $prevCache = null;

    private bool $prevResolved = false;

    private function previousEvent(): ?Event
    {
        if ($this->prevResolved) {
            return $this->prevCache;
        }
        $this->prevResolved = true;

        $event = $this->record;
        if (! $event) {
            return $this->prevCache = null;
        }

        $q = Event::query()->where('id', '!=', $event->id);
        // Same host (partner), else same organization, else any earlier event as a loose baseline.
        if ($event->partner_id !== null) {
            $q->where('partner_id', $event->partner_id);
        } elseif ($event->organization_id !== null) {
            $q->where('organization_id', $event->organization_id);
        }
        if ($event->date !== null) {
            $q->whereDate('date', '<', $event->date);
        }

        return $this->prevCache = $q->orderByDesc('date')->first();
    }

    /**
     * @return array{prevTitle:string,rows:array<int,array{label:string,cur:string,prev:string,dir:string}>}|null
     */
    public function getComparison(): ?array
    {
        $event = $this->record;
        $prev = $this->previousEvent();
        if (! $event || ! $prev) {
            return null;
        }

        $cur = $this->metrics($event);
        $old = $this->metrics($prev);

        $rows = [
            $this->row('Views', $cur['views'], $old['views'], 'int'),
            $this->row('Tickets sold', $cur['tickets'], $old['tickets'], 'int'),
            $this->row('Revenue', $cur['revenue'], $old['revenue'], 'money'),
            $this->row('Conversion', $cur['conversion'], $old['conversion'], 'pct', higherBetter: true),
            $this->row('Show-up', $cur['showup'], $old['showup'], 'pct', higherBetter: true),
            $this->row('Sell-through', $cur['fill'], $old['fill'], 'pct', higherBetter: true),
            $this->row('Rating', $cur['rating'], $old['rating'], 'rating', higherBetter: true),
            $this->row('No-show %', $cur['noshow'], $old['noshow'], 'pct', higherBetter: false),
        ];

        return ['prevTitle' => (string) $prev->title, 'rows' => $rows];
    }

    /** @return array<string,float> */
    private function metrics(Event $e): array
    {
        $paid = Booking::where('event_id', $e->id)->whereIn(DB::raw('lower(status)'), self::PAID);
        $tickets = (int) (clone $paid)->sum('quantity');
        $orders = (int) (clone $paid)->count();
        $checkedIn = (int) (clone $paid)->sum('checked_in_count');
        $views = max((int) $e->views, 0);
        $capacity = max((int) $e->total_slots, 0);
        $available = max((int) $e->available_slots, 0);
        $sold = max($capacity - $available, 0);

        return [
            'views' => $views,
            'tickets' => $tickets,
            'revenue' => (float) (clone $paid)->sum('total_amount'),
            'conversion' => $views > 0 ? $orders / $views * 100 : 0.0,
            'showup' => $tickets > 0 ? $checkedIn / $tickets * 100 : 0.0,
            'fill' => $capacity > 0 ? $sold / $capacity * 100 : 0.0,
            'rating' => (float) ($e->rating ?? 0),
            'noshow' => $tickets > 0 ? max($tickets - $checkedIn, 0) / $tickets * 100 : 0.0,
        ];
    }

    /**
     * @return array{label:string,cur:string,prev:string,dir:string}
     */
    private function row(string $label, float $cur, float $prev, string $fmt, bool $higherBetter = true): array
    {
        $dir = 'flat';
        if (abs($cur - $prev) > 1e-9) {
            $up = $cur > $prev;
            $dir = ($up === $higherBetter) ? 'good' : 'bad';
        }

        return [
            'label' => $label,
            'cur' => $this->fmt($cur, $fmt),
            'prev' => $this->fmt($prev, $fmt),
            'dir' => $dir,
        ];
    }

    private function fmt(float $v, string $fmt): string
    {
        return match ($fmt) {
            'money' => '₹' . number_format($v),
            'pct' => number_format($v, 1) . '%',
            'rating' => $v > 0 ? number_format($v, 1) : '—',
            default => number_format($v),
        };
    }
}
