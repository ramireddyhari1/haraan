<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Partner;

use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;

/**
 * "When your audience buys" — a day-of-week × hour heatmap of booking activity
 * over the last 90 days, plus the single insight that falls out of it ("Most
 * bookings land Fri–Sun, 8–10pm"). This is analytics that tells the organiser
 * what to do — time a ticket drop or an ad push to the peak — rather than just
 * showing a number. Partner-scoped + lane-aware; self-contained Blade + inline
 * CSS, no Vite rebuild.
 */
class PartnerPeakHoursWidget extends Widget
{
    use \App\Filament\Concerns\ScopesToPartnerEvents;
    use \App\Filament\Concerns\ScopesToPartnerVenues;

    protected string $view = 'filament.widgets.partner.peak-hours';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    /** Hour buckets shown as columns — 6am → midnight, where real bookings live. */
    private const HOURS = [6, 8, 10, 12, 14, 16, 18, 20, 22];

    private const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

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
    public function getHeatmap(): array
    {
        $rows = (clone $this->laneBookings())
            ->where('created_at', '>=', now()->subDays(90))
            ->get(['created_at']);

        // grid[dayIndex 0-6 Mon..Sun][hourBucketIndex] = count
        $grid = array_fill(0, 7, array_fill(0, count(self::HOURS), 0));
        $max = 0;
        $total = 0;
        $bestDay = 0;
        $bestHourBucket = 0;

        foreach ($rows as $r) {
            $c = $r->created_at;
            $day = ((int) $c->dayOfWeekIso) - 1;      // 0 = Mon … 6 = Sun
            $hour = (int) $c->format('G');
            $bucket = $this->hourBucket($hour);
            $grid[$day][$bucket]++;
            $total++;
            if ($grid[$day][$bucket] > $max) {
                $max = $grid[$day][$bucket];
                $bestDay = $day;
                $bestHourBucket = $bucket;
            }
        }

        return [
            'grid' => $grid,
            'max' => $max,
            'total' => $total,
            'days' => self::DAYS,
            'hours' => array_map(fn (int $h): string => $this->hourLabel($h), self::HOURS),
            'insight' => $total > 0
                ? $this->insight($grid)
                : null,
            'bestDay' => self::DAYS[$bestDay] ?? null,
            'bestHour' => $this->hourLabel(self::HOURS[$bestHourBucket] ?? 20),
        ];
    }

    /** Snap a real hour to the nearest column bucket. */
    private function hourBucket(int $hour): int
    {
        $best = 0;
        $bestDist = 24;
        foreach (self::HOURS as $i => $h) {
            $d = abs($hour - $h);
            if ($d < $bestDist) {
                $bestDist = $d;
                $best = $i;
            }
        }

        return $best;
    }

    private function hourLabel(int $h): string
    {
        $suffix = $h >= 12 ? 'pm' : 'am';
        $h12 = $h % 12 === 0 ? 12 : $h % 12;

        return $h12 . $suffix;
    }

    /** Human sentence for the busiest window(s). */
    private function insight(array $grid): string
    {
        // Sum per day and per hour bucket to find the dominant band.
        $byDay = array_map('array_sum', $grid);
        $byHour = array_fill(0, count(self::HOURS), 0);
        foreach ($grid as $dayRow) {
            foreach ($dayRow as $i => $v) {
                $byHour[$i] += $v;
            }
        }

        $topDayIdx = array_keys($byDay, max($byDay))[0] ?? 0;
        $topHourIdx = array_keys($byHour, max($byHour))[0] ?? 0;

        $isWeekendHeavy = ($byDay[4] ?? 0) + ($byDay[5] ?? 0) + ($byDay[6] ?? 0)
            > ($byDay[0] ?? 0) + ($byDay[1] ?? 0) + ($byDay[2] ?? 0) + ($byDay[3] ?? 0);

        $dayPhrase = $isWeekendHeavy ? 'Fri–Sun' : self::DAYS[$topDayIdx];
        $hourPhrase = 'around ' . $this->hourLabel(self::HOURS[$topHourIdx]);

        return "Most bookings land {$dayPhrase}, {$hourPhrase} — time your ticket drops and ad pushes to that window.";
    }
}
