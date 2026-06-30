<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LiveMatch;
use App\Models\MatchXpLedger;
use App\Models\User;
use App\Support\ActionboardXp;
use App\Services\ReputationService;
use Illuminate\Support\Carbon;

/**
 * Awards XP for a settled match.
 *
 *   XP = (base_xp + win/MOM bonus) × trustMultiplier × diversityMultiplier
 *
 *   • bonuses count only at trust >= medium
 *   • diversity multiplier decays the n-th match vs the SAME opponents this month
 *   • ranked match XP → users.ranked_xp; otherwise → users.casual_xp
 *
 * Idempotent: re-settling a match replaces its ledger rows and re-aggregates
 * the affected players' totals, so it can be called safely more than once.
 */
final class PlayerXpLedgerService
{
    public static function award(LiveMatch $match): void
    {
        // Private matches never earn XP — they are pure scoreboards. Hard backstop:
        // with no ledger rows written, they are automatically absent from every
        // leaderboard and XP total.
        if ($match->is_private) {
            return;
        }

        // Only registered players (id present) earn XP. Guests are ignored.
        $homeIds = self::registeredIds($match->home_squad ?? []);
        $awayIds = self::registeredIds($match->away_squad ?? []);

        // Players whose totals we must recompute (include any previously credited).
        $previously = MatchXpLedger::where('match_id', $match->id)->pluck('player_id')->all();

        // Idempotency: wipe prior rows for this match before recomputing.
        MatchXpLedger::where('match_id', $match->id)->delete();

        $trust      = $match->trust_level ?? 'low';
        $trustMult  = ActionboardXp::trustMultiplier($trust);
        $bonusOK    = ActionboardXp::trustRank($trust) >= ActionboardXp::trustRank(ActionboardXp::RANKED_MIN_TRUST);
        $isRanked   = (bool) $match->is_ranked;
        $awardedAt  = Carbon::now();
        $month      = $awardedAt->format('Y-m');

        $sides = [
            ['ids' => $homeIds, 'opp' => $awayIds, 'side' => 'home'],
            ['ids' => $awayIds, 'opp' => $homeIds, 'side' => 'away'],
        ];

        foreach ($sides as $s) {
            $opponentKey = self::opponentKey($s['opp']);

            foreach ($s['ids'] as $pid) {
                $occurrence = self::priorSameOpponentCount($pid, $month, $opponentKey, $match) + 1;
                $diversity  = ActionboardXp::diversityMultiplier($occurrence);

                $won = $bonusOK && $match->result === $s['side'];
                $mom = $bonusOK && $match->mom_player_id !== null && (string) $match->mom_player_id === (string) $pid;

                $effectiveBase = $match->base_xp
                    + ($won ? (int) round($match->base_xp * ActionboardXp::WIN_BONUS_FRACTION) : 0)
                    + ($mom ? (int) round($match->base_xp * ActionboardXp::MOM_BONUS_FRACTION) : 0);

                $xp = (int) round($effectiveBase * $trustMult * $diversity);

                MatchXpLedger::create([
                    'player_id'            => $pid,
                    'match_id'             => $match->id,
                    'xp'                   => $xp,
                    'base_xp'              => $match->base_xp,
                    'trust_level'          => $trust,
                    'trust_multiplier'     => $trustMult,
                    'diversity_multiplier' => $diversity,
                    'is_ranked'            => $isRanked,
                    'won'                  => $won,
                    'mom'                  => $mom,
                    'opponent_key'         => $opponentKey,
                    'season_month'         => $month,
                    'awarded_at'           => $awardedAt,
                ]);
            }
        }

        // Re-aggregate every affected player (current + previously credited).
        $affected = array_unique(array_merge($homeIds, $awayIds, $previously));
        foreach ($affected as $pid) {
            self::reaggregatePlayerXp($pid);
        }
    }

    /**
     * Recompute a player's All-Time ranked/casual XP and trust score from the
     * ledger. Deterministic, so safe to re-run.
     */
    public static function reaggregatePlayerXp(string $playerId): void
    {
        $user = User::where('player_id', $playerId)->first();
        if (!$user) {
            return;
        }

        $rankedXp = (int) MatchXpLedger::where('player_id', $playerId)->where('is_ranked', true)->sum('xp');
        $casualXp = (int) MatchXpLedger::where('player_id', $playerId)->where('is_ranked', false)->sum('xp');

        // Trust recovery: +N per ranked match, capped. Penalties (disputes etc.)
        // will subtract here once that subsystem exists (Sprint 5).
        $rankedMatches = MatchXpLedger::where('player_id', $playerId)->where('is_ranked', true)->count();
        $penalties = ReputationService::totalPenalties($playerId);
        $trustScore = ActionboardXp::TRUST_SCORE_START
            + ($rankedMatches * ActionboardXp::TRUST_SCORE_RECOVERY_PER_RANKED_MATCH)
            - $penalties;
        $trustScore = max(ActionboardXp::TRUST_SCORE_MIN, min(ActionboardXp::TRUST_SCORE_MAX, $trustScore));

        $user->update([
            'ranked_xp'   => $rankedXp,
            'casual_xp'   => $casualXp,
            'trust_score' => $trustScore,
        ]);
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    /** Sorted list of registered player_ids from a squad (drops guests/dupes). */
    private static function registeredIds(array $squad): array
    {
        $ids = [];
        foreach ($squad as $p) {
            $id = is_array($p) ? ($p['id'] ?? null) : null;
            if (!empty($id)) {
                $ids[(string) $id] = true;
            }
        }
        // array_keys casts numeric strings to int — force back to string.
        $ids = array_map('strval', array_keys($ids));
        sort($ids);
        return $ids;
    }

    /** Stable key identifying a set of opponents (for same-pair decay). */
    private static function opponentKey(array $opponentIds): string
    {
        return $opponentIds === [] ? '' : implode('|', $opponentIds);
    }

    /**
     * How many earlier matches this month pitted this player against the same
     * opponents. "Earlier" = lower match created_at (tie-break id).
     */
    private static function priorSameOpponentCount(string $playerId, string $month, string $opponentKey, LiveMatch $current): int
    {
        if ($opponentKey === '') {
            return 0;
        }

        return (int) MatchXpLedger::query()
            ->from('match_xp_ledger as l')
            ->join('live_matches as m', 'm.id', '=', 'l.match_id')
            ->where('l.player_id', $playerId)
            ->where('l.season_month', $month)
            ->where('l.opponent_key', $opponentKey)
            ->where('l.match_id', '!=', $current->id)
            ->where(function ($q) use ($current): void {
                $q->where('m.created_at', '<', $current->created_at)
                  ->orWhere(function ($q2) use ($current): void {
                      $q2->where('m.created_at', '=', $current->created_at)
                         ->where('m.id', '<', $current->id);
                  });
            })
            ->count();
    }
}
