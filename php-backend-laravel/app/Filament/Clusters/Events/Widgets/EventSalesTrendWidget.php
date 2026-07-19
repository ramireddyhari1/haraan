<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Events\Widgets;

use App\Models\Booking;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

/**
 * Ticket-sales trend for the Events overview — tickets sold and revenue per day
 * over a selectable window, scoped to event bookings (event_id set; venue
 * bookings excluded). Mirrors the platform RevenueOverviewWidget so the chart
 * behaves identically, just narrowed to events.
 */
class EventSalesTrendWidget extends ChartWidget
{
    use \App\Filament\Concerns\RefreshesOnContentUpdate;

    protected static ?int $sort = 0;

    protected ?string $heading = 'Ticket sales';

    protected ?string $description = 'Tickets sold and revenue from events';

    protected int | string | array $columnSpan = 'full';

    /** Statuses that represent money actually collected. */
    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    public ?string $filter = '14';

    protected function getFilters(): ?array
    {
        return [
            '7' => 'Last 7 days',
            '14' => 'Last 14 days',
            '30' => 'Last 30 days',
            '90' => 'Last 90 days',
        ];
    }

    protected function getData(): array
    {
        $days = (int) ($this->filter ?? 14);
        $start = now()->startOfDay()->subDays($days - 1);

        $rows = Booking::query()
            ->whereIn(DB::raw('lower(status)'), self::PAID)
            ->whereNotNull('event_id')
            ->where('created_at', '>=', $start)
            ->get(['total_amount', 'quantity', 'created_at']);

        // Bucket in PHP so gaps are zero-filled and the timezone matches the app.
        $byDay = [];
        foreach ($rows as $row) {
            $key = $row->created_at->format('Y-m-d');
            $byDay[$key]['tickets'] = ($byDay[$key]['tickets'] ?? 0) + max(1, (int) $row->quantity);
            $byDay[$key]['rev'] = ($byDay[$key]['rev'] ?? 0) + (float) $row->total_amount;
        }

        $labels = [];
        $tickets = [];
        $revenue = [];
        $labelEvery = $days > 30 ? 7 : ($days > 14 ? 2 : 1);

        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            $key = $day->format('Y-m-d');
            $labels[] = $i % $labelEvery === 0 ? $day->format('d M') : '';
            $tickets[] = $byDay[$key]['tickets'] ?? 0;
            $revenue[] = round($byDay[$key]['rev'] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Tickets sold',
                    'data' => $tickets,
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
                'y' => ['position' => 'left', 'beginAtZero' => true],
                'y1' => [
                    'position' => 'right',
                    'beginAtZero' => true,
                    'grid' => ['drawOnChartArea' => false],
                ],
            ],
        ];
    }
}
