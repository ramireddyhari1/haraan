<?php

declare(strict_types=1);

namespace App\Support\Rewards;

use App\Services\Rewards\BadgeService;

/**
 * What a badge can measure. Each metric is computed from real data by
 * {@see BadgeService::metrics()} — nothing here is invented.
 */
final class BadgeMetrics
{
    public const METRICS = [
        'matches_played' => 'Verified matches played (XP ledger)',
        'wins' => 'Verified wins',
        'player_of_match' => 'Player of the match awards',
        'best_win_streak' => 'Best run of consecutive wins',
        'high_score' => 'Highest cricket score',
        'career_wickets' => 'Career wickets',
        'district_rank' => 'District rank at or better than',
        'play_streak_weeks' => 'Best weekly play streak',
    ];

    public static function exists(string $metric): bool
    {
        return array_key_exists($metric, self::METRICS);
    }

    /** Lower is better for ranks; everything else unlocks at or above the threshold. */
    public static function reached(string $metric, ?int $value, int $threshold): bool
    {
        if ($metric === 'district_rank') {
            return $value !== null && $value > 0 && $value <= $threshold;
        }

        return (int) $value >= $threshold;
    }
}
