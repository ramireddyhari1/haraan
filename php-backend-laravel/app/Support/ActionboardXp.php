<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Haraan ActionBoard ranking constants — the single source of truth for the
 * XP economy. Match type sets the ceiling; trust sets the unlock multiplier.
 *
 *   XP = base_xp(type) × trustMultiplier(trust) × diversityMultiplier(repeat)
 *
 * (diversity multiplier is applied per-player at ledger time — Sprint 3.)
 */
final class ActionboardXp
{
    /** Match type → XP ceiling. */
    public const TYPE_BASE_XP = [
        'casual'     => 25,
        'league'     => 60,
        'tournament' => 100,
    ];

    /** Trust level → multiplier that unlocks the ceiling. */
    public const TRUST_MULTIPLIER = [
        'low'      => 0.25,
        'medium'   => 0.75,
        'high'     => 1.0,
        'verified' => 1.25,
    ];

    /** Ordering so we never downgrade an already-achieved trust level. */
    public const TRUST_RANK = [
        'low'      => 0,
        'medium'   => 1,
        'high'     => 2,
        'verified' => 3,
    ];

    /**
     * Same-opponent decay — the n-th ranked match against the SAME opponents in
     * a month is worth less. Kills the "play one friend 50 times" farm.
     * Index = occurrence (1-based); anything past the table uses the last value.
     */
    public const DIVERSITY_MULTIPLIER = [1 => 1.0, 2 => 0.8, 3 => 0.5, 4 => 0.25];

    /** Win / Man-of-the-Match bonuses (fraction of base) — only at trust >= medium. */
    public const WIN_BONUS_FRACTION = 0.15;
    public const MOM_BONUS_FRACTION = 0.10;

    /** Reputation / trust score bounds and recovery. */
    public const TRUST_SCORE_START = 100;
    public const TRUST_SCORE_MAX   = 100;
    public const TRUST_SCORE_MIN   = 0;
    public const TRUST_SCORE_RECOVERY_PER_RANKED_MATCH = 1;

    /** Reputation penalties (positive magnitudes; subtracted from trust score). */
    public const PENALTY = [
        'match_dispute'          => 10,
        'verification_rejection' => 15,
        'fake_tournament'        => 25,
        'repeated_abuse'         => 50,
    ];

    /**
     * Trust thresholds for privileged actions. Serial abusers fall below these
     * and lose the ability to create ranked tournaments / organize / verify.
     */
    public const RANKED_TOURNAMENT_MIN_TRUST_SCORE = 60;
    public const ORGANIZE_MIN_TRUST_SCORE          = 70;
    public const VERIFY_MIN_TRUST_SCORE            = 70;

    public static function penaltyAmount(string $type): int
    {
        return self::PENALTY[$type] ?? 0;
    }

    public static function isValidPenalty(string $type): bool
    {
        return array_key_exists($type, self::PENALTY);
    }

    /** A match only contributes to Ranked leaderboards at trust >= medium. */
    public const RANKED_MIN_TRUST = 'medium';

    /** Distinct registered players required per side for a match to be Ranked. */
    public const RANKED_MIN_PLAYERS_PER_SIDE = 2;

    /** Hours a Completed match waits for confirmation before expiring to low trust. */
    public const VERIFICATION_WINDOW_HOURS = 72;

    public static function baseXpForType(string $type): int
    {
        return self::TYPE_BASE_XP[$type] ?? self::TYPE_BASE_XP['casual'];
    }

    public static function trustMultiplier(string $trust): float
    {
        return self::TRUST_MULTIPLIER[$trust] ?? self::TRUST_MULTIPLIER['low'];
    }

    public static function trustRank(string $trust): int
    {
        return self::TRUST_RANK[$trust] ?? 0;
    }

    public static function meetsRankedTrust(string $trust): bool
    {
        return self::trustRank($trust) >= self::trustRank(self::RANKED_MIN_TRUST);
    }

    public static function isValidType(string $type): bool
    {
        return array_key_exists($type, self::TYPE_BASE_XP);
    }

    /** Decay multiplier for the n-th (1-based) match vs the same opponents. */
    public static function diversityMultiplier(int $occurrence): float
    {
        if ($occurrence < 1) {
            $occurrence = 1;
        }
        $last = count(self::DIVERSITY_MULTIPLIER);
        return self::DIVERSITY_MULTIPLIER[min($occurrence, $last)];
    }
}
