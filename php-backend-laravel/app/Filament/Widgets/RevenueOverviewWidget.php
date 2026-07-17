<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Booking;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Platform-wide "Payments Overview" — the Razorpay move: put a scoped revenue trend
 * front-and-centre on the landing page, with a range selector so the operator controls
 * the window. Revenue on the left axis, booking volume on the right.
 */
class RevenueOverviewWidget extends ChartWidget
{
    protected static ?int $sort = -40;

    protected ?string $heading = 'Payments overview';

    protected ?string $description = 'Collected revenue and booking volume';

    protected int | string | array $columnSpan = 'full';

    /** Statuses that represent money actually collected. */
    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    public ?string $filter = '7';

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
        $days = (int) ($this->filter ?? 7);
        $start = now()->startOfDay()->subDays($days - 1);

        $rows = Booking::query()
            ->whereIn(DB::raw('lower(status)'), self::PAID)
            ->where('created_at', '>=', $start)
            ->get(['total_amount', 'created_at']);

        // Bucket in PHP so gaps are zero-filled and timezone matches the rest of the app.
        $byDay = [];
        foreach ($rows as $row) {
            $key = $row->created_at->format('Y-m-d');
            $byDay[$key]['rev'] = ($byDay[$key]['rev'] ?? 0) + (float) $row->total_amount;
            $byDay[$key]['cnt'] = ($byDay[$key]['cnt'] ?? 0) + 1;
        }

        $labels = [];
        $revenue = [];
        $bookings = [];
        // Thin the axis labels on wider windows so they stay legible.
        $labelEvery = $days > 30 ? 7 : ($days > 14 ? 2 : 1);

        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            $key = $day->format('Y-m-d');
            $labels[] = $i % $labelEvery === 0 ? $day->format('d M') : '';
            $revenue[] = round($byDay[$key]['rev'] ?? 0);
            $bookings[] = $byDay[$key]['cnt'] ?? 0;
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
                    'label' => 'Bookings',
                    'data' => $bookings,
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
