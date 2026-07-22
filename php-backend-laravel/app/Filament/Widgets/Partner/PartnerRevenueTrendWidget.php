<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Partner;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

/**
 * Revenue + bookings over the last N days for the partner's own business.
 *
 * Lane-aware: an event organiser sees their ticket sales, a venue owner sees
 * their turf bookings. Reads run through the partner-scoping concerns so the
 * numbers are always the partner's own, never the platform's.
 */
class PartnerRevenueTrendWidget extends ChartWidget
{
    use \App\Filament\Concerns\ScopesToPartnerEvents;
    use \App\Filament\Concerns\ScopesToPartnerVenues;

    protected static ?int $sort = 1;

    protected ?string $heading = 'Revenue';

    protected int | string | array $columnSpan = 'full';

    // Controlled height — pairs with maintainAspectRatio:false for a calm, wide
    // money chart rather than a tall default aspect ratio.
    protected ?string $maxHeight = '260px';

    // Render eagerly — on the short dashboard grid a lazy chart never intersects.
    protected static bool $isLazy = false;

    /** Statuses that represent money actually collected. */
    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    public ?string $filter = '14';

    protected function getFilters(): ?array
    {
        return ['7' => 'Last 7 days', '14' => 'Last 14 days', '30' => 'Last 30 days'];
    }

    private function isEventLane(): bool
    {
        return auth()->user()?->partner_type === 'event';
    }

    protected function getData(): array
    {
        $days = (int) ($this->filter ?? 14);
        $start = now()->startOfDay()->subDays($days - 1);

        $base = $this->isEventLane() ? $this->scopedBookingQuery() : $this->scopedVenueBookingQuery();

        $rows = $base
            ->whereIn(DB::raw('lower(status)'), self::PAID)
            ->where('created_at', '>=', $start)
            ->get(['total_amount', 'quantity', 'created_at']);

        // Bucket in PHP so gaps are zero-filled and the timezone matches the app.
        $byDay = [];
        foreach ($rows as $row) {
            $key = $row->created_at->format('Y-m-d');
            $byDay[$key]['rev'] = ($byDay[$key]['rev'] ?? 0) + (float) $row->total_amount;
        }

        $labels = [];
        $revenue = [];
        $labelEvery = $days > 14 ? 3 : 2;

        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            $key = $day->format('Y-m-d');
            // Label only every Nth day + always the last, so the axis stays clean.
            $labels[] = ($i % $labelEvery === 0 || $i === $days - 1) ? $day->format('d M') : '';
            $revenue[] = round($byDay[$key]['rev'] ?? 0);
        }

        // Emphasise only the final point — the "where we are now" dot.
        $pointRadius = array_fill(0, $days, 0);
        $pointRadius[$days - 1] = 5;

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (₹)',
                    'data' => $revenue,
                    'borderColor' => '#0f9d63',
                    'backgroundColor' => 'rgba(15, 157, 99, 0.12)',
                    'fill' => true,
                    'tension' => 0.4,
                    'borderWidth' => 2.5,
                    'pointRadius' => $pointRadius,
                    'pointHoverRadius' => 5,
                    'pointBackgroundColor' => '#0f9d63',
                    'pointBorderColor' => '#ffffff',
                    'pointBorderWidth' => 2,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        // Single-axis, minimal grid, no legend — the money story with no noise.
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'border' => ['display' => false],
                    'grid' => ['color' => 'rgba(120,130,150,0.12)'],
                    'ticks' => ['precision' => 0, 'maxTicksLimit' => 5],
                ],
                'x' => [
                    'border' => ['display' => false],
                    'grid' => ['display' => false],
                ],
            ],
            'plugins' => ['legend' => ['display' => false]],
            'maintainAspectRatio' => false,
            'elements' => ['line' => ['capBezierPoints' => true]],
        ];
    }
}
