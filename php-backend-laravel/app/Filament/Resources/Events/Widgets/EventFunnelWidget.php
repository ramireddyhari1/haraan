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

        return [
            'stages' => $stages,
            'conversion' => $conversion,
            'ramping' => $ramping,
            'svg' => $this->buildSvg($stages),
            'rail' => $this->buildRail($stages),
        ];
    }

    /**
     * Enrich each stage for the breakdown rail: an icon, its accent colours
     * (blue deepening down, green for the final "goal" stage), a progress width,
     * and step-retention (the share of the previous step that continued — the
     * positive flip-side of drop-off).
     *
     * @param  array<int,array{label:string,count:int,pctTop:int,drop:?int,lost:int,note:string}>  $stages
     * @return array<int,array<string,mixed>>
     */
    private function buildRail(array $stages): array
    {
        $n = count($stages);

        // Blue ramp for the funnel body; the last stage is the green payoff.
        $blues = [
            ['bar' => '#9CC0EE', 'icon' => '#3b82f6'],
            ['bar' => '#4B8FE0', 'icon' => '#2563eb'],
            ['bar' => '#2F7FD0', 'icon' => '#1d4ed8'],
        ];
        $icons = ['heroicon-m-eye', 'heroicon-m-shopping-cart', 'heroicon-m-tag'];

        $rail = [];
        foreach ($stages as $i => $s) {
            $isLast = $i === $n - 1;
            $tone = $blues[min($i, count($blues) - 1)];

            $rail[] = [
                'label'    => $s['label'],
                'note'     => $s['note'],
                'count'    => (int) $s['count'],
                'pctTop'   => (int) $s['pctTop'],
                'drop'     => $s['drop'],
                'lost'     => (int) $s['lost'],
                'retained' => is_null($s['drop']) ? null : max(0, 100 - (int) $s['drop']),
                'isLast'   => $isLast,
                'icon'     => $isLast ? 'heroicon-m-check-circle' : ($icons[$i] ?? 'heroicon-m-chevron-right'),
                'barHex'   => $isLast ? '#16a34a' : $tone['bar'],
                'iconHex'  => $isLast ? '#16a34a' : $tone['icon'],
                'iconBg'   => $isLast ? 'rgb(22 163 74 / .12)' : 'rgb(37 99 235 / .11)',
            ];
        }

        return $rail;
    }

    /**
     * Pre-compute the continuous-funnel SVG geometry so the blade stays math-free.
     * Each stage becomes a trapezoid tapering to the next stage's width (the last
     * stage is a straight cap), giving one narrowing silhouette. Returns the
     * viewBox size plus per-segment polygon points and centred count labels.
     *
     * @param  array<int,array{label:string,count:int,pctTop:int,drop:?int,lost:int,note:string}>  $stages
     * @return array{width:int,height:int,segments:array<int,array{points:string,count:int,tx:int,ty:int,fs:int,dark:bool}>}
     */
    private function buildSvg(array $stages): array
    {
        $width   = 340;
        $cx      = 170;   // horizontal centre
        $maxHalf = 156;   // half-width of a 100% stage (spans x14..326)
        $minHalf = 20;    // floor so a tiny stage stays visible
        $segH    = 104;   // height of a tapering segment
        $capH    = 56;    // height of the final (flat) cap
        $gap     = 8;     // visual gap between segments

        $n = count($stages);

        $half = static fn (int $pct): int => (int) max($minHalf, round($pct / 100 * $maxHalf));

        $segments = [];
        $y = 0;

        foreach ($stages as $i => $s) {
            $isLast = $i === $n - 1;
            $topHalf = $half((int) $s['pctTop']);
            $h = $isLast ? $capH : $segH;
            $botHalf = $isLast ? $topHalf : $half((int) $stages[$i + 1]['pctTop']);

            $y1 = $y + $h;
            $points = implode(' ', [
                ($cx - $topHalf) . ',' . $y,
                ($cx + $topHalf) . ',' . $y,
                ($cx + $botHalf) . ',' . $y1,
                ($cx - $botHalf) . ',' . $y1,
            ]);

            $segments[] = [
                'points' => $points,
                'count'  => (int) $s['count'],
                'tx'     => $cx,
                'ty'     => (int) round($y + $h * 0.5 + 9),
                'fs'     => $i === 0 ? 30 : ($isLast ? 20 : 26),
                'dark'   => $i === 0, // light top band → dark ink; deeper bands → white
            ];

            $y = $y1 + $gap;
        }

        return ['width' => $width, 'height' => max($y - $gap, 1), 'segments' => $segments];
    }

    /**
     * @return array{label:string,count:int,pctTop:int,drop:?int,lost:int,note:string}
     */
    private function stage(string $label, int $count, int $top, ?int $prev, string $note): array
    {
        // Drop-off vs the previous stage (null for the first stage). `lost` is the
        // raw headcount that fell away — what a host actually acts on.
        $drop = null;
        $lost = 0;
        if ($prev !== null && $prev > 0) {
            $lost = max($prev - $count, 0);
            $drop = (int) round($lost / $prev * 100);
        }

        return [
            'label' => $label,
            'count' => $count,
            'pctTop' => $top > 0 ? (int) round($count / $top * 100) : 0,
            'drop' => $drop,
            'lost' => $lost,
            'note' => $note,
        ];
    }
}
