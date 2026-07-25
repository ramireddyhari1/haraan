<?php

declare(strict_types=1);

namespace App\Filament\Resources\Partners\Widgets;

use App\Models\Booking;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * A partner's earnings trend — paid revenue (₹) per month over the trailing
 * year, across both their events and their venues. Real booking data only.
 */
class PartnerRevenueChartWidget extends ChartWidget
{
    /** Injected by ViewPartner via getWidgetData(). */
    public ?User $record = null;

    protected ?string $heading = 'Revenue over time';

    protected ?string $description = 'Paid revenue per month, last 12 months';

    protected int | string | array $columnSpan = 'full';

    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    protected function getData(): array
    {
        if (! $this->record) {
            return ['datasets' => [], 'labels' => []];
        }

        $eventIds = $this->record->events()->pluck('id')->all();
        $venueIds = $this->record->venues()->pluck('id')->all();

        if ($eventIds === [] && $venueIds === []) {
            return ['datasets' => [], 'labels' => []];
        }

        $start = now()->startOfMonth()->subMonths(11);

        $rows = Booking::query()
            ->where(function (Builder $q) use ($eventIds, $venueIds): void {
                $q->when($eventIds !== [], fn (Builder $b) => $b->orWhereIn('event_id', $eventIds));
                $q->when($venueIds !== [], fn (Builder $b) => $b->orWhereIn('venue_id', $venueIds));
            })
            ->whereIn(DB::raw('lower(status)'), self::PAID)
            ->where('created_at', '>=', $start)
            ->get(['total_amount', 'created_at']);

        // Bucket by month in PHP so gaps are filled and the app timezone applies.
        $byMonth = [];
        foreach ($rows as $row) {
            $key = $row->created_at->format('Y-m');
            $byMonth[$key] = ($byMonth[$key] ?? 0) + (float) $row->total_amount;
        }

        $labels  = [];
        $revenue = [];
        for ($i = 0; $i < 12; $i++) {
            $month = $start->copy()->addMonths($i);
            $labels[]  = $month->format('M Y');
            $revenue[] = round($byMonth[$month->format('Y-m')] ?? 0);
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
                'y' => ['beginAtZero' => true],
            ],
            'plugins' => [
                'legend' => ['display' => false],
            ],
        ];
    }
}
