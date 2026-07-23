<?php

declare(strict_types=1);

namespace App\Filament\Resources\Events\Widgets;

use App\Models\Booking;
use App\Models\Event;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

/**
 * Arrival curve for a single event: how attendees checked in over time, bucketed
 * by hour. Bars = arrivals per hour, line = cumulative — the shape hosts use to
 * plan gate staffing. Each booking contributes its checked_in_count at its first
 * check-in time (checked_in_at).
 */
class EventArrivalCurveWidget extends ChartWidget
{
    /** Injected by the page via InteractsWithRecord::getWidgetData(). */
    public ?Event $record = null;

    protected ?string $heading = 'Arrival curve';

    protected ?string $description = 'Check-ins over time (gate staffing)';

    protected int | string | array $columnSpan = 'full';

    /** Statuses that represent money actually earned. */
    private const PAID = ['confirmed', 'paid', 'completed'];

    protected function getData(): array
    {
        if (! $this->record) {
            return ['datasets' => [], 'labels' => []];
        }

        $rows = Booking::where('event_id', $this->record->id)
            ->whereIn(DB::raw('lower(status)'), self::PAID)
            ->whereNotNull('checked_in_at')
            ->where('checked_in_count', '>', 0)
            ->get(['checked_in_at', 'checked_in_count']);

        if ($rows->isEmpty()) {
            return ['datasets' => [], 'labels' => []];
        }

        // Bucket arrivals into hourly slots between the first and last check-in.
        $byHour = [];
        foreach ($rows as $row) {
            $key = $row->checked_in_at->copy()->startOfHour()->format('Y-m-d H:00');
            $byHour[$key] = ($byHour[$key] ?? 0) + (int) $row->checked_in_count;
        }

        $start = $rows->min('checked_in_at')->copy()->startOfHour();
        $end   = $rows->max('checked_in_at')->copy()->startOfHour();

        $labels     = [];
        $perHour    = [];
        $cumulative = [];
        $running    = 0;

        $cursor = $start->copy();
        // Cap at 48 hourly buckets so a stray early check-in can't explode the axis.
        $guard = 0;
        while ($cursor->lte($end) && $guard < 48) {
            $key = $cursor->format('Y-m-d H:00');
            $count = $byHour[$key] ?? 0;
            $running += $count;

            $labels[]     = $cursor->format('d M · H:00');
            $perHour[]    = $count;
            $cumulative[] = $running;

            $cursor->addHour();
            $guard++;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Arrivals',
                    'data' => $perHour,
                    'type' => 'bar',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                    'borderColor' => '#3b82f6',
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Cumulative',
                    'data' => $cumulative,
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
        // Base type; the cumulative dataset overrides to a line (mixed chart).
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y'  => ['position' => 'left', 'beginAtZero' => true, 'title' => ['display' => true, 'text' => 'Per hour']],
                'y1' => [
                    'position' => 'right',
                    'beginAtZero' => true,
                    'grid' => ['drawOnChartArea' => false],
                    'title' => ['display' => true, 'text' => 'Cumulative'],
                ],
            ],
        ];
    }
}
