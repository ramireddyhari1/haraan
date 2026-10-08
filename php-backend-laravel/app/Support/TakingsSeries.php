<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Buckets paid bookings into bars for the admin console's takings chart: hours for
 * "today", days up to 30 days, weeks for 90 days and months (last 12) for all time.
 * Buckets are cut in the business zone, so "today" is the venue's day, not UTC's.
 *
 * Feeds `filament.partials.cc-bar-chart`.
 */
final class TakingsSeries
{
    /**
     * @param  Builder  $paid  paid bookings, already narrowed to whatever the page shows
     * @return array{bars: array<int,array<string,mixed>>, max: float, peak: ?int, unit: string, empty: bool}
     */
    public static function build(string $range, Builder $paid): array
    {
        $zone = BusinessClock::zone();
        $now = BusinessClock::now();

        [$unit, $start, $count] = match ($range) {
            'today' => ['hour', $now->copy()->startOfDay(), 24],
            '7d' => ['day', $now->copy()->startOfDay()->subDays(6), 7],
            '90d' => ['week', $now->copy()->startOfWeek()->subWeeks(12), 13],
            'all' => ['month', $now->copy()->startOfMonth()->subMonths(11), 12],
            default => ['day', $now->copy()->startOfDay()->subDays(29), 30],
        };

        $step = fn (Carbon $c, int $n): Carbon => match ($unit) {
            'hour' => $c->copy()->addHours($n),
            'week' => $c->copy()->addWeeks($n),
            'month' => $c->copy()->addMonths($n),
            default => $c->copy()->addDays($n),
        };

        $buckets = [];
        for ($i = 0; $i < $count; $i++) {
            $buckets[] = ['from' => $step($start, $i), 'to' => $step($start, $i + 1), 'value' => 0.0, 'orders' => 0];
        }

        $rows = (clone $paid)
            ->where('bookings.created_at', '>=', $start->copy()->setTimezone(config('app.timezone')))
            ->get(['bookings.created_at', 'bookings.total_amount']);

        foreach ($rows as $row) {
            $at = $row->created_at ? Carbon::parse($row->created_at)->setTimezone($zone) : null;
            if (! $at) {
                continue;
            }
            foreach ($buckets as $i => $b) {
                if ($at >= $b['from'] && $at < $b['to']) {
                    $buckets[$i]['value'] += (float) $row->total_amount;
                    $buckets[$i]['orders']++;
                    break;
                }
            }
        }

        $bars = [];
        $peak = null;
        foreach ($buckets as $i => $b) {
            /** @var Carbon $from */
            $from = $b['from'];
            $bars[] = [
                'value' => round($b['value'], 2),
                'fmt' => Rupees::format($b['value']),
                'orders' => $b['orders'],
                'label' => match ($unit) {
                    'hour' => $from->format('g A'),
                    'week' => 'Week of '.$from->format('j M'),
                    'month' => $from->format('M Y'),
                    default => $from->format('D, j M'),
                },
                'tick' => match ($unit) {
                    'hour' => $from->hour % 6 === 0 ? $from->format('ga') : '',
                    'week' => $i % 3 === 0 ? $from->format('j M') : '',
                    'month' => $from->format('M'),
                    default => ($count <= 7 || $i % 5 === 0 || $i === $count - 1) ? $from->format($count <= 7 ? 'D' : 'j M') : '',
                },
                'current' => $now >= $b['from'] && $now < $b['to'],
            ];
            if ($b['value'] > 0 && ($peak === null || $b['value'] > $bars[$peak]['value'])) {
                $peak = $i;
            }
        }

        return [
            'bars' => $bars,
            'max' => (float) max(array_column($bars, 'value') ?: [0]),
            'peak' => $peak,
            'unit' => $unit,
            'empty' => $peak === null,
        ];
    }
}
