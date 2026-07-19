<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The KPI header for the Users list — the summary an operator wants before the
 * rows: how many people there are, how many are active, how fast it's growing,
 * and who holds elevated access. Frames the table the way BookMyShow/Stripe
 * admin lists open on tiles rather than a bare grid. Reuses the last_seen_at
 * heartbeat and created_at, so every number is a cheap count on real data.
 */
class UsersStatsWidget extends StatsOverviewWidget
{
    use \App\Filament\Concerns\RefreshesOnContentUpdate;

    protected function getStats(): array
    {
        $now = Carbon::now();
        $weekStart = $now->copy()->subDays(6)->startOfDay();  // last 7 days inclusive
        $prevStart = $now->copy()->subDays(13)->startOfDay();
        $prevEnd = $now->copy()->subDays(7)->endOfDay();

        $total = User::count();

        // Signups over the last 14 days, split in PHP so one query serves both the
        // week-over-week trend and the sparkline.
        $recent = User::query()->where('created_at', '>=', $prevStart)->get(['created_at']);
        $thisWeek = $recent->filter(fn ($u) => $u->created_at >= $weekStart);
        $prevWeek = $recent->filter(fn ($u) => $u->created_at >= $prevStart && $u->created_at <= $prevEnd);
        $signupSpark = $this->dailyCounts($thisWeek, $weekStart, $now);

        $onlineNow = User::where('last_seen_at', '>=', $now->copy()->subMinutes(5))->count();
        $wau = User::where('last_seen_at', '>=', $now->copy()->subDays(7))->count();
        $mau = User::where('last_seen_at', '>=', $now->copy()->subDays(30))->count();

        // Role casing is mixed in the DB — match case-insensitively.
        $staff = User::whereRaw("upper(role) in ('ADMIN','COADMIN')")->count();
        $partners = User::whereRaw("upper(role) = 'PARTNER'")->count();

        return [
            Stat::make('Total users', number_format($total))
                ->description($total > 0 ? "{$staff} staff · {$partners} partners" : 'No accounts yet')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            $this->newSignupsStat($thisWeek->count(), $prevWeek->count(), $signupSpark),

            Stat::make('Active · 7 days', number_format($wau))
                ->description($onlineNow > 0 ? "{$onlineNow} online now · {$mau} this month" : "{$mau} this month")
                ->descriptionIcon('heroicon-m-signal')
                ->color($wau > 0 ? 'success' : 'gray'),

            Stat::make('Staff & partners', number_format($staff + $partners))
                ->description('accounts with elevated access')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('info'),
        ];
    }

    private function newSignupsStat(int $current, int $prev, array $spark): Stat
    {
        [$label, $color, $icon] = $this->trend($current, $prev);

        $stat = Stat::make('New this week', number_format($current))
            ->descriptionIcon($icon)
            ->chart($spark)
            ->color($current > 0 ? $color : 'gray');

        return $current > 0
            ? $stat->description($label . ' vs previous 7 days')
            : $stat->description('No new signups this week');
    }

    /**
     * Per-day counts across [start, end], zero-filled so the sparkline always has
     * one point per day even on quiet days.
     *
     * @param  Collection<int, User>  $rows
     * @return list<int>
     */
    private function dailyCounts(Collection $rows, Carbon $start, Carbon $end): array
    {
        $series = [];
        for ($d = $start->copy(); $d <= $end; $d->addDay()) {
            $series[$d->toDateString()] = 0;
        }
        foreach ($rows as $u) {
            $key = $u->created_at?->toDateString();
            if ($key !== null && isset($series[$key])) {
                $series[$key]++;
            }
        }

        return array_values($series);
    }

    /**
     * @return array{0: string, 1: string, 2: string}  [label, color, icon]
     */
    private function trend(int|float $current, int|float $prev): array
    {
        if ($prev <= 0) {
            return $current > 0
                ? ['New', 'success', 'heroicon-m-arrow-trending-up']
                : ['—', 'gray', 'heroicon-m-minus'];
        }

        $pct = (int) round((($current - $prev) / $prev) * 100);

        return match (true) {
            $pct > 0 => ['+' . $pct . '%', 'success', 'heroicon-m-arrow-trending-up'],
            $pct < 0 => [$pct . '%', 'danger', 'heroicon-m-arrow-trending-down'],
            default => ['0%', 'gray', 'heroicon-m-minus'],
        };
    }
}
