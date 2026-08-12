<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Events\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/**
 * Event-intelligence KPIs for the Events overview — events, tickets sold (with a
 * capacity meter), revenue and sell-through, scoped to the partner's own events.
 *
 * Rebuilt as premium KPI widgets (self-contained Blade + inline CSS, theme-aware,
 * no Vite rebuild) so an organiser reads event health at a glance — the
 * BookMyShow-partner / Eventbrite-organizer feel — instead of four flat stat
 * boxes.
 */
class EventsStatsWidget extends Widget
{
    use \App\Filament\Concerns\ScopesToPartnerEvents;

    protected string $view = 'filament.clusters.events.widgets.events-stats';

    protected static ?int $sort = -2;

    protected int | string | array $columnSpan = 'full';

    // Eager: also used on the short partner dashboard where a lazy widget would
    // never intersect to load.
    protected static bool $isLazy = false;

    /** Statuses that represent money actually collected. */
    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    /**
     * View-ready KPI summary.
     *
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        $events = $this->scopedEventQuery()->count();
        $totalSeats = (int) $this->scopedEventQuery()->sum('total_slots');
        $available = (int) $this->scopedEventQuery()->sum('available_slots');
        $sold = max($totalSeats - $available, 0);
        $sellThrough = $totalSeats > 0 ? (int) round($sold / $totalSeats * 100) : 0;

        $soldOut = $this->scopedEventQuery()
            ->whereColumn('available_slots', '<=', 'total_slots')
            ->where('available_slots', '<=', 0)
            ->count();

        $eventBookings = (int) $this->scopedBookingQuery()->whereNotNull('event_id')->count();

        $revenue = (float) $this->scopedBookingQuery()
            ->whereNotNull('event_id')
            ->whereIn(DB::raw('lower(status)'), self::PAID)
            ->sum('total_amount');

        return [
            'events'      => $events,
            'sold'        => number_format($sold),
            'totalSeats'  => number_format($totalSeats),
            'sellThrough' => min(100, max(0, $sellThrough)),
            'soldOut'     => $soldOut,
            'bookings'    => $eventBookings,
            'revenue'     => $this->inr($revenue),
            'tone'        => $sellThrough >= 80 ? 'hot' : ($sellThrough >= 50 ? 'warm' : 'cool'),
        ];
    }

    /** ₹1,84,290 — Indian grouping, whole rupees. */
    private function inr(float $n): string
    {
        $n = (int) round($n);
        $str = (string) abs($n);
        if (strlen($str) <= 3) {
            return '₹' . $str;
        }
        $last3 = substr($str, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($str, 0, -3));

        return '₹' . $rest . ',' . $last3;
    }
}
