<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;

/**
 * The KPI header for the Users list — the summary an operator wants before the
 * rows: how many people there are, how many are active, how fast it's growing,
 * and who holds elevated access. Frames the table the way BookMyShow/Stripe
 * admin lists open on tiles rather than a bare grid. Cached with a short TTL so
 * high-frequency list navigation and searches never hammer the database.
 */
class UsersStatsWidget extends StatsOverviewWidget
{
    use \App\Filament\Concerns\RefreshesOnContentUpdate;

    public const CACHE_KEY = 'admin:users_stats_kpis';
    public const CACHE_TTL_SECONDS = 60;

    public static function invalidateCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    #[On('haraan-content-updated')]
    public function refreshOnContentUpdate(): void
    {
        self::invalidateCache();
    }

    protected function getStats(): array
    {
        /** @var array{total: int, thisWeekCount: int, prevWeekCount: int, signupSpark: list<int>, onlineNow: int, wau: int, mau: int, staff: int, partners: int} $data */
        $data = Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
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
            $signupSpark = self::calculateDailyCounts($thisWeek, $weekStart, $now);

            $onlineNow = User::where('last_seen_at', '>=', $now->copy()->subMinutes(5))->count();
            $wau = User::where('last_seen_at', '>=', $now->copy()->subDays(7))->count();
            $mau = User::where('last_seen_at', '>=', $now->copy()->subDays(30))->count();

            // Roles are normalized uppercase and indexed.
            $staff = User::whereIn('role', ['ADMIN', 'COADMIN'])->count();
            $partners = User::where('role', 'PARTNER')->count();

            return [
                'total'         => $total,
                'thisWeekCount' => $thisWeek->count(),
                'prevWeekCount' => $prevWeek->count(),
                'signupSpark'   => $signupSpark,
                'onlineNow'     => $onlineNow,
                'wau'           => $wau,
                'mau'           => $mau,
                'staff'         => $staff,
                'partners'      => $partners,
            ];
        });

        return [
            Stat::make('Total users', number_format($data['total']))
                ->description($data['total'] > 0 ? "{$data['staff']} staff · {$data['partners']} partners" : 'No accounts yet')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            $this->newSignupsStat($data['thisWeekCount'], $data['prevWeekCount'], $data['signupSpark']),

            Stat::make('Active · 7 days', number_format($data['wau']))
                ->description($data['onlineNow'] > 0 ? "{$data['onlineNow']} online now · {$data['mau']} this month" : "{$data['mau']} this month")
                ->descriptionIcon('heroicon-m-signal')
                ->color($data['wau'] > 0 ? 'success' : 'gray'),

            Stat::make('Staff & partners', number_format($data['staff'] + $data['partners']))
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
    private static function calculateDailyCounts(Collection $rows, Carbon $start, Carbon $end): array
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
