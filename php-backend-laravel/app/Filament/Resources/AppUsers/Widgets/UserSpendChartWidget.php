<?php

declare(strict_types=1);

namespace App\Filament\Resources\AppUsers\Widgets;

use App\Models\Booking;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Trailing 12-month paid booking spend timeline for a single user.
 * Cached for 300s to ensure instant loads during profile review.
 */
class UserSpendChartWidget extends ChartWidget
{
    public ?User $record = null;

    protected ?string $heading = 'Spend history';

    protected ?string $description = 'Paid booking spend (₹) per month, trailing 12 months';

    protected int | string | array $columnSpan = 'full';

    private const PAID_STATUSES = ['confirmed', 'paid', 'completed', 'checked_in'];

    protected function getData(): array
    {
        if (! $this->record) {
            return ['datasets' => [], 'labels' => []];
        }

        $userId = $this->record->id;

        return Cache::remember("user:{$userId}:spend_chart", 300, function () use ($userId): array {
            $start = now()->startOfMonth()->subMonths(11);

            $rows = Booking::query()
                ->where('user_id', $userId)
                ->whereIn(DB::raw('lower(status)'), self::PAID_STATUSES)
                ->where('created_at', '>=', $start)
                ->get(['total_amount', 'created_at']);

            // Bucket by month
            $byMonth = [];
            foreach ($rows as $row) {
                $key = $row->created_at->format('Y-m');
                $byMonth[$key] = ($byMonth[$key] ?? 0) + (float) $row->total_amount;
            }

            $labels = [];
            $spend = [];
            for ($i = 0; $i < 12; $i++) {
                $month = $start->copy()->addMonths($i);
                $labels[] = $month->format('M Y');
                $spend[] = round($byMonth[$month->format('Y-m')] ?? 0);
            }

            return [
                'datasets' => [
                    [
                        'label' => 'Spend (₹)',
                        'data' => $spend,
                        'borderColor' => '#4f46e5',
                        'backgroundColor' => 'rgba(79, 70, 229, 0.12)',
                        'fill' => true,
                        'tension' => 0.35,
                    ],
                ],
                'labels' => $labels,
            ];
        });
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
