<?php

declare(strict_types=1);

namespace App\Filament\Resources\Venues\Widgets;

use App\Models\Booking;
use App\Models\Venue;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

/**
 * Which weekdays this venue gets booked — helps owners spot dead days to promote.
 * Counts all-time paid venue bookings bucketed by the slot's day of week.
 */
class VenuePeakDaysWidget extends ChartWidget
{
    /** Injected by the page via InteractsWithRecord::getWidgetData(). */
    public ?Venue $record = null;

    protected ?string $heading = 'Peak days';

    protected ?string $description = 'Bookings by day of week';

    protected int | string | array $columnSpan = 'full';

    private const PAID = ['confirmed', 'paid', 'completed'];

    protected function getData(): array
    {
        if (! $this->record) {
            return ['datasets' => [], 'labels' => []];
        }

        $rows = Booking::where('booking_type', 'venue')
            ->where('venue_id', $this->record->id)
            ->whereIn(DB::raw('lower(status)'), self::PAID)
            ->whereNotNull('slot_date')
            ->get(['slot_date']);

        // 0 = Monday … 6 = Sunday.
        $buckets = array_fill(0, 7, 0);
        foreach ($rows as $row) {
            $dow = (int) $row->slot_date->dayOfWeekIso - 1;
            $buckets[$dow]++;
        }

        return [
            'datasets' => [[
                'label' => 'Bookings',
                'data' => array_values($buckets),
                'backgroundColor' => 'rgba(34, 197, 94, 0.6)',
                'borderColor' => '#16a34a',
            ]],
            'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}
