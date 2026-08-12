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

    protected ?string $heading = 'Peak hours';

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

        return [
            'datasets' => [[
                'label' => 'Bookings',
                'data' => array_values($hours),
                'backgroundColor' => '#0A66FF',
                'borderRadius' => 4,
            ]],
            'labels' => array_map(fn (int $h): string => $this->label($h), array_keys($hours)),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
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
