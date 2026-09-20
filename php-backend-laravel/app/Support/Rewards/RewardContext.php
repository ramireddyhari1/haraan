<?php

declare(strict_types=1);

namespace App\Support\Rewards;

use App\Models\LiveMatch;
use App\Models\User;
use App\Services\Membership\EntitlementSet;
use App\Services\Stats\MatchPlayerStatsCalculator;

/** Everything a rule may ask about one participant of one finished match. Built once per player. */
final class RewardContext
{
    public function __construct(
        public readonly User $user,
        public readonly LiveMatch $match,
        public readonly string $side,
        /** won | lost | tied | unknown */
        public readonly string $outcome,
        public readonly bool $isPlayerOfMatch,
        public readonly string $trustLevel,
        public readonly bool $settled,
        public readonly int $minRegisteredPerSide,
        public readonly int $playStreakWeeks,
        public readonly EntitlementSet $entitlements,
    ) {}

    /**
     * The player's outcome, from the same scoreline reading the stats pipeline uses (cricket's
     * result line, other sports' score). live_matches.result is not used: no scoring path
     * writes it.
     */
    public static function outcomeFor(LiveMatch $match, string $side): string
    {
        $result = MatchPlayerStatsCalculator::resultFor($match);

        return match (true) {
            $result === null => 'unknown',
            $result === 'draw' => 'tied',
            $result === $side => 'won',
            default => 'lost',
        };
    }
}
