<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Partner;

use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * "Money by day" — the last seven days of collected revenue as a compact bar
 * strip. The best day is highlighted and today pulses, so an operator reads their
 * week at a glance on a phone far quicker than a smooth line chart lets them.
 *
 * Lane-aware (event ticket revenue vs turf-booking revenue) and partner-scoped
 * through the same concerns as the other money widgets, so the numbers are always
 * the partner's own. Self-contained bars (Blade + inline CSS) — no Vite rebuild.
 */
class PartnerDailyEarningsWidget extends Widget
{
    use \App\Filament\Concerns\ScopesToPartnerEvents;
    use \App\Filament\Concerns\ScopesToPartnerVenues;

    protected string $view = 'filament.widgets.partner.daily-earnings';

    protected static ?int $sort = 1;

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
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $days = 7;
        $start = now()->startOfDay()->subDays($days - 1);
        $todayKey = now()->format('Y-m-d');

        $rows = $this->laneBookings()
            ->whereIn(DB::raw('lower(status)'), self::PAID)
            ->where('created_at', '>=', $start)
            ->get(['total_amount', 'created_at']);

        // Bucket in PHP so gaps are zero-filled and the timezone matches the app.
        $byDay = [];
        foreach ($rows as $row) {
            $key = $row->created_at->format('Y-m-d');
            $byDay[$key] = ($byDay[$key] ?? 0) + (float) $row->total_amount;
        }

        $bars = [];
        $total = 0.0;
        $max = 0.0;
        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            $key = $day->format('Y-m-d');
            $value = $byDay[$key] ?? 0.0;
            $total += $value;
            $max = max($max, $value);
            $bars[] = [
                'letter' => substr($day->format('D'), 0, 1),
                'date' => $day->format('j M'),
                'value' => $value,
                'isToday' => $key === $todayKey,
            ];
        }

        // First day that hits the peak (>0) is the "best day" callout.
        $bestIdx = null;
        $best = 0.0;
        foreach ($bars as $i => $bar) {
            if ($bar['value'] > $best) {
                $best = $bar['value'];
                $bestIdx = $i;
            }
        }

        return [
            'bars' => $bars,
            'total' => $total,
            'max' => $max ?: 1.0,
            'bestIdx' => $bestIdx,
            'best' => $best,
            'bestDate' => $bestIdx !== null ? $bars[$bestIdx]['date'] : null,
            'isEvent' => $this->isEventLane(),
        ];
    }
}
