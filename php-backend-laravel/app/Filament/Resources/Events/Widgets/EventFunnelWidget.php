<?php

declare(strict_types=1);

namespace App\Filament\Resources\Events\Widgets;

use App\Models\Booking;
use App\Models\Event;
use App\Models\EventView;
use Illuminate\Support\Facades\DB;
use Filament\Widgets\Widget;

/**
 * Conversion funnel — Viewed → Checkout started → Paid — built only from stages we can measure
 * honestly today: unique visitors (event_views), orders begun (any booking row), and paid orders.
 * The finer micro-steps in the wishlist (ticket-page open, ticket selected) aren't instrumented
 * yet, so they're not invented; this shows the three real stages plus per-step drop-off.
 * Read-only; record injected by the page.
 */
class EventFunnelWidget extends Widget
{
    public ?Event $record = null;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.resources.events.widgets.event-funnel';

    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    /**
     * @return array{stages:array<int,array{label:string,count:int,pctTop:int,drop:?int,note:string}>,conversion:float}|array{stages:array}
     */
    public function getFunnel(): array
    {
        $event = $this->record;
        if (! $event) {
            return ['stages' => []];
        }

        // Top of funnel: unique tracked visitors; fall back to the raw counter for events whose
        // views predate per-view tracking (so an old event with sales doesn't show a 0 top).
        $unique = (int) EventView::where('event_id', $event->id)->distinct('visitor_key')->count('visitor_key');
        $viewed = $unique > 0 ? $unique : max((int) $event->views, 0);

        $started = (int) Booking::where('event_id', $event->id)->count();       // any order begun
        $paid = (int) Booking::where('event_id', $event->id)
            ->whereIn(DB::raw('lower(status)'), self::PAID)->count();

        // Keep the funnel monotonic for display even if tracking started mid-life
        // (e.g. more orders than tracked viewers): the top is at least the widest stage.
        $top = max($viewed, $started, $paid, 1);

        // View tracking is new: for events whose real views predate it, the tracked "viewed"
        // count can be smaller than the order count, which would make conversion read >100%.
        // Detect that and show a caveat instead of a misleading number.
        $ramping = $viewed < $started;

        $stages = [
            $this->stage('Viewed', $viewed, $top, null, $unique > 0 ? 'unique visitors' : 'views (pre-tracking)'),
            $this->stage('Checkout started', $started, $top, $ramping ? null : $viewed, 'orders begun'),
            $this->stage('Paid', $paid, $top, $started, 'completed payment'),
        ];

        $conversion = ($viewed > 0 && ! $ramping) ? round($paid / $viewed * 100, 2) : null;

        return ['stages' => $stages, 'conversion' => $conversion, 'ramping' => $ramping];
    }

    /**
     * @return array{label:string,count:int,pctTop:int,drop:?int,note:string}
     */
    private function stage(string $label, int $count, int $top, ?int $prev, string $note): array
    {
        // Drop-off vs the previous stage (null for the first stage).
        $drop = null;
        if ($prev !== null && $prev > 0) {
            $drop = (int) round(max($prev - $count, 0) / $prev * 100);
        }

        return [
            'label' => $label,
            'count' => $count,
            'pctTop' => $top > 0 ? (int) round($count / $top * 100) : 0,
            'drop' => $drop,
            'note' => $note,
        ];
    }
}
