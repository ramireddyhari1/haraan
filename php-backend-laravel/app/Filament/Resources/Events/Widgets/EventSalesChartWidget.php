<?php

declare(strict_types=1);

namespace App\Filament\Resources\Events\Widgets;

use App\Models\Booking;
use App\Models\Event;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Sales momentum for a single event: revenue (₹) and tickets sold per day
 * over the trailing two weeks. Real booking data only.
 */
class EventSalesChartWidget extends ChartWidget
{
    /** Injected by the page via InteractsWithRecord::getWidgetData(). */
    public ?Event $record = null;

    protected ?string $heading = 'Sales over time';

    protected ?string $description = 'Revenue and tickets sold, last 14 days';

    protected int | string | array $columnSpan = 'full';

    /** Statuses that represent money actually earned. */
    private const PAID = ['confirmed', 'paid', 'completed'];

    protected function getData(): array
    {
        $labels   = [];
        $revenue  = [];
        $tickets  = [];

        if (! $this->record) {
            return ['datasets' => [], 'labels' => []];
        }

        $start = now()->startOfDay()->subDays(13);

        $rows = Booking::where('event_id', $this->record->id)
            ->whereIn(\DB::raw('lower(status)'), self::PAID)
            ->where('created_at', '>=', $start)
            ->get(['total_amount', 'quantity', 'created_at']);

        // Bucket in PHP so timezone handling matches the app and gaps are filled.
        $byDay = [];
        foreach ($rows as $row) {
            $key = $row->created_at->format('Y-m-d');
            $byDay[$key]['rev'] = ($byDay[$key]['rev'] ?? 0) + (float) $row->total_amount;
            $byDay[$key]['qty'] = ($byDay[$key]['qty'] ?? 0) + (int) $row->quantity;
        }

        for ($i = 0; $i < 14; $i++) {
            $day = $start->copy()->addDays($i);
            $key = $day->format('Y-m-d');
            $labels[]  = $day->format('d M');
            $revenue[] = round($byDay[$key]['rev'] ?? 0);
            $tickets[] = $byDay[$key]['qty'] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (₹)',
                    'data' => $revenue,
                    'borderColor' => '#22c55e',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.15)',
                    'fill' => true,
                    'tension' => 0.35,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Tickets',
                    'data' => $tickets,
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
                'y'  => ['position' => 'left', 'beginAtZero' => true],
                'y1' => [
                    'position' => 'right',
                    'beginAtZero' => true,
                    'grid' => ['drawOnChartArea' => false],
                ],
            ],
        ];
    }
}
