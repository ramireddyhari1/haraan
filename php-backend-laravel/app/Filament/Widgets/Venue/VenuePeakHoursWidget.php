<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Venue;

use App\Filament\Widgets\Venue\Concerns\ScopesToVenueLane;
use App\Models\Booking;
use Illuminate\Support\Carbon;
use Filament\Widgets\ChartWidget;

/**
 * When this venue actually sells, by hour of day, over the last 30 days.
 *
 * The useful reading is the trough, not the peak: evenings are always full, and
 * the money a venue is leaving on the table is the flat stretch between late
 * morning and mid-afternoon. The heading names that window explicitly so the
 * owner doesn't have to squint at bars to find it.
 */
class VenuePeakHoursWidget extends ChartWidget
{
    use ScopesToVenueLane;

    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    // A 30-day pattern doesn't move while you watch it.
    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Peak hours';

    protected ?string $description = 'Bookings by start time, last 30 days';

    // A standing pattern, not the headline — keep it a strip, not a wall.
    protected ?string $maxHeight = '220px';

    protected int | string | array $columnSpan = 'full';

    /** @var array<int, int>|null hour => bookings, memoised per request */
    private ?array $byHour = null;

    public function getHeading(): ?string
    {
        $hours = $this->hourCounts();

        if (array_sum($hours) === 0) {
            return 'Peak hours';
        }

        // Only daytime hours can meaningfully be "dead" — nobody expects 5am to sell.
        $daytime = array_filter(
            $hours,
            fn (int $count, int $hour): bool => $hour >= 9 && $hour <= 17,
            ARRAY_FILTER_USE_BOTH,
        );

        if ($daytime === [] || array_sum($daytime) === array_sum($hours)) {
            return 'Peak hours';
        }

        $quietest = array_keys($daytime, min($daytime), true)[0] ?? null;

        return $quietest === null
            ? 'Peak hours'
            : sprintf('Peak hours · quietest slot is %s', $this->label($quietest));
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $hours = $this->hourCounts();

        // The busy hours carry the brand blue; the rest recede to a tint, so the
        // eye lands on "when the venue sells" before reading any axis.
        $max = max($hours) ?: 0;
        $colors = array_map(
            fn (int $n): string => $max > 0 && $n >= $max * 0.75 ? '#2563EB' : '#C7D7FB',
            array_values($hours),
        );

        return [
            'datasets' => [[
                'label' => 'Bookings',
                'data' => array_values($hours),
                'backgroundColor' => $colors,
                'hoverBackgroundColor' => '#1D4ED8',
                'borderRadius' => 6,
                'borderSkipped' => false,
                'maxBarThickness' => 28,
            ]],
            'labels' => array_map(fn (int $h): string => $this->label($h), array_keys($hours)),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'animation' => ['duration' => 700, 'easing' => 'easeOutQuart'],
            'scales' => [
                'x' => ['grid' => ['display' => false], 'border' => ['display' => false],
                    'ticks' => ['color' => '#8A93A6', 'font' => ['size' => 11], 'maxRotation' => 0, 'autoSkipPadding' => 10]],
                'y' => ['beginAtZero' => true, 'border' => ['display' => false],
                    'grid' => ['color' => 'rgba(15,23,42,0.06)'],
                    'ticks' => ['precision' => 0, 'color' => '#8A93A6', 'font' => ['size' => 11], 'maxTicksLimit' => 5]],
            ],
        ];
    }

    /**
     * Bookings per hour of day across the trailing 30 days.
     *
     * `start_time` is a "HH:MM" string, so the bucketing happens in PHP rather
     * than as database-specific time arithmetic — the volumes here are a single
     * venue's month, not a scan worth optimising.
     *
     * @return array<int, int>  hour (5..23) => count
     */
    private function hourCounts(): array
    {
        if ($this->byHour !== null) {
            return $this->byHour;
        }

        $buckets = [];
        foreach (range(5, 23) as $hour) {
            $buckets[$hour] = 0;
        }

        $rows = $this->liveBookings()
            ->where('slot_date', '>=', Carbon::today()->subDays(30))
            ->whereNotNull('start_time')
            ->get(['start_time']);

        foreach ($rows as $row) {
            $ts = strtotime((string) $row->start_time);

            if ($ts === false) {
                continue;
            }

            $hour = (int) date('G', $ts);

            if (array_key_exists($hour, $buckets)) {
                $buckets[$hour]++;
            }
        }

        return $this->byHour = $buckets;
    }

    /** 19 → "7 PM" */
    private function label(int $hour): string
    {
        return date('g A', mktime($hour, 0) ?: 0);
    }
}
