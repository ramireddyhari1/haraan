<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ReputationEvent;
use App\Models\User;
use App\Support\ActionboardXp;

/**
 * Haraan ActionBoard reputation / trust score.
 *
 * Trust score = START + (ranked matches × recovery) − Σ(penalties), clamped to
 * [MIN, MAX]. Penalties are recorded as immutable events so the score is always
 * deterministically re-derivable (mirrors how XP is re-aggregated from the ledger).
 */
final class ReputationService
{
    /**
     * Record a penalty against a player and re-derive their trust score.
     */
    public static function penalize(
        string $playerId,
        string $type,
        ?string $reason = null,
        ?int $matchId = null,
        ?int $reportedBy = null,
    ): ?ReputationEvent {
        if (!ActionboardXp::isValidPenalty($type)) {
            return null;
        }

        $event = ReputationEvent::create([
            'player_id'   => $playerId,
            'type'        => $type,
            'amount'      => ActionboardXp::penaltyAmount($type),
            'match_id'    => $matchId,
            'reported_by' => $reportedBy,
            'reason'      => $reason,
        ]);

        self::recomputeTrustScore($playerId);

        return $event;
    }

    /**
     * Re-derive and persist a player's trust score from ranked matches + penalties.
     */
    public static function recomputeTrustScore(string $playerId): int
    {
        $user = User::where('player_id', $playerId)->first();
        if (!$user) {
            return ActionboardXp::TRUST_SCORE_START;
        }

        $rankedMatches = \App\Models\MatchXpLedger::where('player_id', $playerId)
            ->where('is_ranked', true)
            ->count();

        $penalties = (int) ReputationEvent::where('player_id', $playerId)->sum('amount');

        $score = ActionboardXp::TRUST_SCORE_START
            + ($rankedMatches * ActionboardXp::TRUST_SCORE_RECOVERY_PER_RANKED_MATCH)
            - $penalties;

        $score = max(ActionboardXp::TRUST_SCORE_MIN, min(ActionboardXp::TRUST_SCORE_MAX, $score));

        $user->update(['trust_score' => $score]);

        return $score;
    }

    /** Total penalty magnitude on a player (used by XP re-aggregation). */
    public static function totalPenalties(string $playerId): int
    {
        return (int) ReputationEvent::where('player_id', $playerId)->sum('amount');
    }

    // ── Privilege gates ──────────────────────────────────────────────────────

    public static function canCreateRankedTournament(User $user): bool
    {
        return (int) ($user->trust_score ?? ActionboardXp::TRUST_SCORE_START)
            >= ActionboardXp::RANKED_TOURNAMENT_MIN_TRUST_SCORE;
    }

    public static function canOrganize(User $user): bool
    {
        return (bool) ($user->is_organizer ?? false)
            && (int) ($user->trust_score ?? ActionboardXp::TRUST_SCORE_START)
                >= ActionboardXp::ORGANIZE_MIN_TRUST_SCORE;
    }

    public static function canVerifyResults(User $user): bool
    {
        return self::canOrganize($user)
            && (int) ($user->trust_score ?? ActionboardXp::TRUST_SCORE_START)
                >= ActionboardXp::VERIFY_MIN_TRUST_SCORE;
    }
}
