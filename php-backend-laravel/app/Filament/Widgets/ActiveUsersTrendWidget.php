<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\UserActivityDay;
use Filament\Widgets\ChartWidget;

/**
 * The growth story behind the "Active users" stat — daily active users over time, split
 * into new (first-ever active day) vs returning, so an operator can tell real acquisition
 * from the same faces coming back. Fed by the append-only user_activity_days log, which
 * the JWT heartbeat writes; a lone last_seen_at column could never draw this line.
 */
class ActiveUsersTrendWidget extends ChartWidget
{
    use \App\Filament\Concerns\RefreshesOnContentUpdate;

    protected static ?int $sort = -30;

    protected ?string $heading = 'Active users over time';

    protected ?string $description = 'Daily active users — new vs returning';

    protected int | string | array $columnSpan = 'full';

    public ?string $filter = '30';

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
        $days = (int) ($this->filter ?? 30);
        $start = now()->startOfDay()->subDays($days - 1);

        // Global first-active day per user (may predate the window) → lets us label a
        // day's active user as "new" only on their very first appearance, ever.
        $firstSeen = UserActivityDay::query()
            ->selectRaw('user_id, MIN(activity_date) as first_date')
            ->groupBy('user_id')
            ->pluck('first_date', 'user_id')
            ->map(fn ($d) => substr((string) $d, 0, 10));

        $rows = UserActivityDay::query()
            ->where('activity_date', '>=', $start->toDateString())
            ->get(['user_id', 'activity_date']);

        // Bucket per day: total distinct users, and how many were brand new that day.
        $byDay = [];
        foreach ($rows as $row) {
            $key = $row->activity_date->format('Y-m-d');
            $byDay[$key]['dau'][$row->user_id] = true;
            if (($firstSeen[$row->user_id] ?? null) === $key) {
                $byDay[$key]['new'][$row->user_id] = true;
            }
        }

        $labels = [];
        $dau = [];
        $new = [];
        $returning = [];
        $labelEvery = $days > 30 ? 7 : ($days > 14 ? 2 : 1);

        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            $key = $day->format('Y-m-d');
            $labels[] = $i % $labelEvery === 0 ? $day->format('d M') : '';

            $total = count($byDay[$key]['dau'] ?? []);
            $newCount = count($byDay[$key]['new'] ?? []);
            $dau[] = $total;
            $new[] = $newCount;
            $returning[] = $total - $newCount;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Active users (DAU)',
                    'data' => $dau,
                    'borderColor' => '#22c55e',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.15)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
                [
                    'label' => 'New',
                    'data' => $new,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.12)',
                    'fill' => false,
                    'tension' => 0.35,
                ],
                [
                    'label' => 'Returning',
                    'data' => $returning,
                    'borderColor' => '#a855f7',
                    'backgroundColor' => 'rgba(168, 85, 247, 0.12)',
                    'fill' => false,
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
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
        ];
    }
}
