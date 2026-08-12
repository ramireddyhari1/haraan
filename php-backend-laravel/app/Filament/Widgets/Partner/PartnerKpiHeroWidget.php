<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Partner;

use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The dashboard's "money hero" — the first thing an organiser reads.
 *
 * One dominant revenue figure (last 14 days, with a real vs-previous delta and a
 * daily sparkline) leads, three supporting KPIs sit beside it: tickets sold,
 * check-in rate, and refund rate. Lane-aware — an event organiser sees ticket
 * revenue, a venue owner sees turf-booking revenue — and everything runs through
 * the partner-scoping concerns so the numbers are always the partner's own.
 *
 * Self-contained (Blade + inline CSS) like the other bespoke partner strips, so
 * it deploys with no Vite rebuild. Replaces the generic stats widget in the
 * partner dashboard; /control keeps its own.
 */
class PartnerKpiHeroWidget extends Widget
{
    use \App\Filament\Concerns\ScopesToPartnerEvents;
    use \App\Filament\Concerns\ScopesToPartnerVenues;
    // Reads the dashboard's global period control via $this->pageFilters['range'].
    use \Filament\Widgets\Concerns\InteractsWithPageFilters;

    protected string $view = 'filament.widgets.partner.kpi-hero';

    protected static ?int $sort = 0;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    /** Money widget — desk staff need the 'reports' capability to see it. */
    public static function canView(): bool
    {
        return auth()->user()?->hasPartnerPermission('reports') ?? false;
    }

    /** Statuses that represent money actually collected. */
    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    private function isEventLane(): bool
    {
        return auth()->user()?->partner_type === 'event';
    }

    private function laneBookings(): Builder
    {
        return $this->isEventLane() ? $this->scopedBookingQuery() : $this->scopedVenueBookingQuery();
    }

    /**
     * All the numbers the view needs, computed once.
     *
     * @return array<string, mixed>
     */
    /** The window (in days) from the dashboard's global period control. */
    private function windowDays(): int
    {
        $range = (int) ($this->pageFilters['range'] ?? \App\Filament\Pages\Dashboard::DEFAULT_PERIOD);

        return in_array($range, [7, 14, 30, 90], true) ? $range : \App\Filament\Pages\Dashboard::DEFAULT_PERIOD;
    }

    public function getStats(): array
    {
        $days = $this->windowDays();
        $now = now();
        $curStart = $now->copy()->startOfDay()->subDays($days - 1);
        $prevStart = $curStart->copy()->subDays($days);

        // Pull the last 28 days of paid rows once, bucket in PHP (matches the
        // trend widget's approach — zero-filled gaps, app timezone).
        $paid = (clone $this->laneBookings())
            ->whereIn(\Illuminate\Support\Facades\DB::raw('lower(status)'), self::PAID)
            ->where('created_at', '>=', $prevStart)
            ->get(['total_amount', 'quantity', 'status', 'created_at']);

        $curRevenue = 0.0;
        $prevRevenue = 0.0;
        $tickets = 0;
        $checkedIn = 0;
        $paidCount = 0;
        $daily = array_fill(0, $days, 0.0);

        foreach ($paid as $row) {
            $amount = (float) $row->total_amount;
            if ($row->created_at >= $curStart) {
                $curRevenue += $amount;
                $tickets += max(1, (int) $row->quantity);
                $paidCount++;
                if (strtolower((string) $row->status) === 'checked_in') {
                    $checkedIn++;
                }
                $idx = (int) $curStart->diffInDays($row->created_at);
                if ($idx >= 0 && $idx < $days) {
                    $daily[$idx] += $amount;
                }
            } else {
                $prevRevenue += $amount;
            }
        }

        // Refund/cancel rate over the current window (against all bookings, paid or not).
        $windowAll = (clone $this->laneBookings())->where('created_at', '>=', $curStart)->count();
        $refunded = (clone $this->laneBookings())
            ->where('created_at', '>=', $curStart)
            ->whereIn(\Illuminate\Support\Facades\DB::raw('lower(status)'), ['refunded', 'cancelled', 'canceled'])
            ->count();

        $delta = $prevRevenue > 0
            ? round((($curRevenue - $prevRevenue) / $prevRevenue) * 100, 1)
            : null;

        return [
            'isEventLane' => $this->isEventLane(),
            'days' => $days,
            'revenue' => $curRevenue,
            'delta' => $delta,
            'tickets' => $tickets,
            'checkedInRate' => $paidCount > 0 ? (int) round($checkedIn / $paidCount * 100) : null,
            'refundRate' => $windowAll > 0 ? round($refunded / $windowAll * 100, 1) : 0.0,
            'refundCount' => $refunded,
            'bookingCount' => $windowAll,
            'spark' => $this->sparkPath($daily),
        ];
    }

    /** Turn 14 daily revenue points into a normalised SVG polyline path (0–120 × 0–34). */
    private function sparkPath(array $daily): string
    {
        $n = count($daily);
        $max = max($daily) ?: 1;
        $pts = [];
        foreach ($daily as $i => $v) {
            $x = $n > 1 ? round($i / ($n - 1) * 120, 1) : 0;
            $y = round(32 - ($v / $max) * 28, 1);
            $pts[] = "{$x},{$y}";
        }

        return 'M' . implode(' L', $pts);
    }
}
