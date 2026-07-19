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
            $byDay[$key]['count'] = ($byDay[$key]['count'] ?? 0) + max(1, (int) $row->quantity);
            $byDay[$key]['rev'] = ($byDay[$key]['rev'] ?? 0) + (float) $row->total_amount;
        }

        $labels = [];
        $counts = [];
        $revenue = [];
        $labelEvery = $days > 14 ? 2 : 1;

        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            $key = $day->format('Y-m-d');
            $labels[] = $i % $labelEvery === 0 ? $day->format('d M') : '';
            $counts[] = $byDay[$key]['count'] ?? 0;
            $revenue[] = round($byDay[$key]['rev'] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => $this->isEventLane() ? 'Tickets sold' : 'Bookings',
                    'data' => $counts,
                    'borderColor' => '#22c55e',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.15)',
                    'fill' => true,
                    'tension' => 0.35,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Revenue (₹)',
                    'data' => $revenue,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.15)',
                    'fill' => false,
                    'tension' => 0.35,
                    'yAxisID' => 'y1',
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
        return [
            'scales' => [
                'y' => ['position' => 'left', 'beginAtZero' => true, 'ticks' => ['precision' => 0]],
                'y1' => ['position' => 'right', 'beginAtZero' => true, 'grid' => ['drawOnChartArea' => false]],
            ],
            'plugins' => ['legend' => ['display' => true]],
        ];
    }
}
