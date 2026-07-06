<?php

declare(strict_types=1);

namespace App\Filament\Resources\Venues\Widgets;

use App\Models\Booking;
use App\Models\Venue;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

/**
 * Venue bookings + revenue by slot date over the trailing two weeks.
 */
class VenueBookingsChartWidget extends ChartWidget
{
    /** Injected by the page via InteractsWithRecord::getWidgetData(). */
    public ?Venue $record = null;

    protected ?string $heading = 'Bookings over time';

    protected ?string $description = 'By slot date, last 14 days';

    protected int | string | array $columnSpan = 'full';

    private const PAID = ['confirmed', 'paid', 'completed'];

    protected function getData(): array
    {
        if (! $this->record) {
            return ['datasets' => [], 'labels' => []];
        }

        $start = now()->startOfDay()->subDays(13);

        $rows = Booking::where('booking_type', 'venue')
            ->where('venue_id', $this->record->id)
            ->whereIn(DB::raw('lower(status)'), self::PAID)
            ->whereNotNull('slot_date')
            ->whereDate('slot_date', '>=', $start->toDateString())
            ->get(['slot_date', 'total_amount']);

        $byDay = [];
        foreach ($rows as $row) {
            $key = $row->slot_date->format('Y-m-d');
            $byDay[$key]['count'] = ($byDay[$key]['count'] ?? 0) + 1;
            $byDay[$key]['rev'] = ($byDay[$key]['rev'] ?? 0) + (float) $row->total_amount;
        }

        $labels = [];
        $counts = [];
        $revenue = [];
        for ($i = 0; $i < 14; $i++) {
            $day = $start->copy()->addDays($i);
            $key = $day->format('Y-m-d');
            $labels[] = $day->format('d M');
            $counts[] = $byDay[$key]['count'] ?? 0;
            $revenue[] = round($byDay[$key]['rev'] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Bookings',
                    'data' => $counts,
                    'type' => 'bar',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                    'borderColor' => '#3b82f6',
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Revenue (₹)',
                    'data' => $revenue,
                    'type' => 'line',
                    'borderColor' => '#22c55e',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.15)',
                    'fill' => true,
                    'tension' => 0.35,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y'  => ['position' => 'left', 'beginAtZero' => true],
                'y1' => ['position' => 'right', 'beginAtZero' => true, 'grid' => ['drawOnChartArea' => false]],
            ],
        ];
    }
}
