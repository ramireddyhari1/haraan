<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LiveMatch;
use App\Models\User;
use App\Support\ActionboardXp;
use Illuminate\Support\Carbon;

/**
 * Haraan ActionBoard verification state machine.
 *
 *   Completed → openVerification() → verification_status = 'pending'
 *                                     (72h deadline starts)
 *
 *   pending → both captains confirm   → settle('medium')
 *           → organizer verifies       → settle('high')
 *           → Haraan venue/booking      → settle('verified')
 *           → deadline passes           → expire() → settle('low') as 'expired'
 *
 * Trust is monotonic — confirmations can only ever *raise* it. Settling sets
 * the trust level and recomputes whether the match is Ranked. XP award lands
 * on top of this in Sprint 3 (see the hook in settle()).
 */
final class MatchVerificationService
{
    /**
     * Open the verification window. Called the moment a match is Completed.
     * Idempotent: re-completing a match does not reset an existing window.
     */
    public static function openVerification(LiveMatch $match): void
    {
        if ($match->is_private) {
            return; // private matches never enter the verification/XP pipeline
        }
        if ($match->verification_status === 'settled') {
            return; // already resolved
        }
        if ($match->verification_status === 'pending') {
            return; // window already open
        }

        $match->update([
            'verification_status'   => 'pending',
            'verification_deadline' => Carbon::now()->addHours(ActionboardXp::VERIFICATION_WINDOW_HOURS),
        ]);
    }

    /**
     * Record a captain's confirmation of the result. When both captains have
     * confirmed, the match settles at Medium trust (unless already higher).
     *
     * @param  string  $side  'home' | 'away'
     */
    public static function confirmByCaptain(LiveMatch $match, string $side): LiveMatch
    {
        if (!in_array($side, ['home', 'away'], true)) {
            return $match;
        }

        $match->update([
            $side === 'home' ? 'home_captain_confirmed' : 'away_captain_confirmed' => true,
        ]);
        $match->refresh();

        if ($match->home_captain_confirmed && $match->away_captain_confirmed) {
            self::settle($match, 'medium');
        }

        return $match;
    }

    /**
     * A tournament organizer verifies the result → High trust.
     */
    public static function verifyByOrganizer(LiveMatch $match, User $organizer): LiveMatch
    {
        $match->update([
            'verified_by' => $organizer->id,
            'verified_at' => Carbon::now(),
        ]);

        return self::settle($match, 'high');
    }

    /**
     * Result confirmed by a Haraan venue / turf booking → Verified trust.
     */
    public static function verifyByVenue(LiveMatch $match, ?int $bookingId = null): LiveMatch
    {
        $match->update([
            'venue_booking_id' => $bookingId,
            'verified_at'      => Carbon::now(),
        ]);

        return self::settle($match, 'verified');
    }

    /**
     * Settle the match at (at least) the given trust level. Monotonic — never
     * downgrades an already-achieved trust. Recomputes Ranked eligibility.
     */
    public static function settle(LiveMatch $match, string $trustLevel): LiveMatch
    {
        // Never downgrade trust already achieved.
        $newTrust = ActionboardXp::trustRank($trustLevel) >= ActionboardXp::trustRank($match->trust_level ?? 'low')
            ? $trustLevel
            : $match->trust_level;

        $match->update([
            'trust_level'         => $newTrust,
            'verification_status' => 'settled',
            'is_ranked'           => self::qualifiesForRanked($match, $newTrust),
        ]);
        $match->refresh();

        // Award XP for this settlement (idempotent — replaces prior rows).
        PlayerXpLedgerService::award($match);

        return $match;
    }

    /**
     * Expire matches whose verification window has lapsed without confirmation.
     * Settles them at Low trust, flagged 'expired'. Returns the count expired.
     */
    public static function expireOverdue(): int
    {
        $overdue = LiveMatch::query()
            ->where('verification_status', 'pending')
            ->whereNotNull('verification_deadline')
            ->where('verification_deadline', '<', Carbon::now())
            ->get();

        foreach ($overdue as $match) {
            self::settle($match, 'low');
            $match->update(['verification_status' => 'expired']);
        }

        return $overdue->count();
    }

    /**
     * A match is Ranked only when trust >= medium AND both sides field enough
     * distinct registered players. Low-trust / guest-heavy games stay Casual.
     */
    private static function qualifiesForRanked(LiveMatch $match, string $trust): bool
    {
        // Private matches are never ranked, regardless of trust or squad size.
        if ($match->is_private) {
            return false;
        }
        if (!ActionboardXp::meetsRankedTrust($trust)) {
            return false;
        }

        $min = ActionboardXp::RANKED_MIN_PLAYERS_PER_SIDE;

        return $match->distinctRegisteredPlayers('home') >= $min
            && $match->distinctRegisteredPlayers('away') >= $min;
    }
}
