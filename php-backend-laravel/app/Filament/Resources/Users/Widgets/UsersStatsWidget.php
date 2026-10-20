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

    public const CACHE_KEY = 'admin:users_stats_kpis:v2';
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
        /** @var array{total: int, thisWeekCount: int, prevWeekCount: int, signupSpark: list<int>, onlineNow: int, wau: int, mau: int, neverOpened: int} $data */
        $data = Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
            $now = Carbon::now();
            $weekStart = $now->copy()->subDays(6)->startOfDay();  // last 7 days inclusive
            $prevStart = $now->copy()->subDays(13)->startOfDay();
            $prevEnd = $now->copy()->subDays(7)->endOfDay();

            // The same people as the list under it (AppUserResource): app users only. It
            // counted every account, so staff, partners and HR employees inflated "users".
            $people = fn () => User::query()->where(fn ($q) => $q->whereNull('role')
                ->orWhereNotIn('role', \App\Filament\Resources\AppUsers\AppUserResource::NON_USER_ROLES));

            $total = $people()->count();

            // Signups over the last 14 days, split in PHP so one query serves both the
            // week-over-week trend and the sparkline.
            $recent = $people()->where('created_at', '>=', $prevStart)->get(['created_at']);
            $thisWeek = $recent->filter(fn ($u) => $u->created_at >= $weekStart);
            $prevWeek = $recent->filter(fn ($u) => $u->created_at >= $prevStart && $u->created_at <= $prevEnd);
            $signupSpark = self::calculateDailyCounts($thisWeek, $weekStart, $now);

            $onlineNow = $people()->where('last_seen_at', '>=', $now->copy()->subMinutes(5))->count();
            $wau = $people()->where('last_seen_at', '>=', $now->copy()->subDays(7))->count();
            $mau = $people()->where('last_seen_at', '>=', $now->copy()->subDays(30))->count();

            // Signed up (often at a desk or by OTP on the site) but never opened the app.
            $neverOpened = $people()->whereNull('last_seen_at')->count();

            return [
                'total'         => $total,
                'thisWeekCount' => $thisWeek->count(),
                'prevWeekCount' => $prevWeek->count(),
                'signupSpark'   => $signupSpark,
                'onlineNow'     => $onlineNow,
                'wau'           => $wau,
                'mau'           => $mau,
                'neverOpened'   => $neverOpened,
            ];
        });

        return [
            Stat::make('Total users', number_format($data['total']))
                ->description($data['total'] > 0 ? 'people using Haraan' : 'No players yet')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            $this->newSignupsStat($data['thisWeekCount'], $data['prevWeekCount'], $data['signupSpark']),

            Stat::make('Active · 7 days', number_format($data['wau']))
                ->description($data['onlineNow'] > 0 ? "{$data['onlineNow']} online now · {$data['mau']} this month" : "{$data['mau']} this month")
                ->descriptionIcon('heroicon-m-signal')
                ->color($data['wau'] > 0 ? 'success' : 'gray'),

            Stat::make('Never opened the app', number_format($data['neverOpened']))
                ->description($data['total'] > 0 ? round($data['neverOpened'] / $data['total'] * 100) . '% of users — a welcome nudge candidate' : 'nobody yet')
                ->descriptionIcon('heroicon-m-device-phone-mobile')
                ->color($data['neverOpened'] > 0 ? 'warning' : 'gray'),
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
