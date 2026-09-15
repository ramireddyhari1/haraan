<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Stats\LeaderboardRankService;
use App\Services\Stats\PlayerCareerService;

/**
 * Legacy entry points, kept so existing callers keep compiling.
 *
 * This class used to hold `freezeMatchStats()`, which on every match completion padded
 * squads with IPL players' names and invented batters' runs with mt_rand, then ranked the
 * leaderboard on those numbers. That generator is gone. Real per-match figures come from
 * {@see \App\Services\Stats\MatchPlayerStatsCalculator} (the match's own log), careers from
 * {@see PlayerCareerService}, and completion side effects from {@see MatchCompletion}.
 */
final class PlayerStatsService
{
    /** Recompute one player's careers from their real match rows. */
    public static function reaggregatePlayerStats(string $playerId): void
    {
        if (app(PlayerCareerService::class)->refresh([$playerId])) {
            app(LeaderboardRankService::class)->recalculate();
        }
    }

    /** Country / state / district rankings on career runs. */
    public static function recalculateLeaderboardRankings(): void
    {
        app(LeaderboardRankService::class)->recalculate();
    }
}
