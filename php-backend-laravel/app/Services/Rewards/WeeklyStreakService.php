<?php

declare(strict_types=1);

namespace App\Services\Rewards;

use App\Models\LiveMatch;
use App\Models\PlayerStreak;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The weekly play streak: consecutive ISO weeks in which the player finished at least one
 * match. The week is the one the match finished in. Recording is idempotent within a week —
 * a second match that week (or a re-run for the same match) changes nothing.
 */
final class WeeklyStreakService
{
    /**
     * @return array{extended: bool, current: int, best: int, period: string}
     *                                                                        extended = this call moved the streak into a new week
     */
    public function record(User $user, LiveMatch $match): array
    {
        $when = Carbon::instance($match->completed_at ?? now());
        $period = self::period($when);
        $previous = self::period($when->copy()->subWeek());

        return DB::transaction(function () use ($user, $match, $period, $previous): array {
            $streak = PlayerStreak::query()->firstOrCreate(
                ['user_id' => $user->id, 'kind' => PlayerStreak::PLAY_WEEK],
                ['current' => 0, 'best' => 0],
            );

            if ($streak->last_period !== null && strcmp($streak->last_period, $period) >= 0) {
                // Same week, or a late-finishing older match: never moves the streak.
                return ['extended' => false, 'current' => (int) $streak->current, 'best' => (int) $streak->best, 'period' => $period];
            }

            $current = $streak->last_period === $previous ? (int) $streak->current + 1 : 1;

            // Conditional on the period we read, so two matches finishing at once can't both extend.
            $updated = PlayerStreak::query()
                ->whereKey($streak->id)
                ->where(fn ($q) => $streak->last_period === null ? $q->whereNull('last_period') : $q->where('last_period', $streak->last_period))
                ->update([
                    'current' => $current,
                    'best' => max((int) $streak->best, $current),
                    'last_period' => $period,
                    'last_match_id' => $match->id,
                    'updated_at' => now(),
                ]);

            if ($updated === 0) {
                $streak->refresh();

                return ['extended' => false, 'current' => (int) $streak->current, 'best' => (int) $streak->best, 'period' => $period];
            }

            return ['extended' => true, 'current' => $current, 'best' => max((int) $streak->best, $current), 'period' => $period];
        });
    }

    /** @return array{current: int, best: int, last_period: ?string} */
    public function forUser(User $user): array
    {
        $streak = PlayerStreak::query()->where('user_id', $user->id)->where('kind', PlayerStreak::PLAY_WEEK)->first();

        return [
            'current' => $streak?->liveCurrent() ?? 0,
            'best' => (int) ($streak->best ?? 0),
            'last_period' => $streak?->last_period,
        ];
    }

    public static function period(Carbon $when): string
    {
        return $when->format('o-\WW');
    }
}
