<?php

declare(strict_types=1);

namespace App\Services\Rewards;

use App\Models\BonusXpEntry;
use App\Models\RewardGrant;
use App\Models\User;
use App\Services\Membership\MemberEntitlements;
use App\Support\Membership\MemberFeature;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Writes Bonus XP. Never competitive XP: this class touches bonus_xp_ledger only — not
 * match_xp_ledger, not users.casual_xp / ranked_xp, not leaderboards.
 */
final class BonusXp
{
    /** A boost above this is treated as this, whatever a plan says. */
    public const MAX_BOOST_PERCENT = 500;

    public function __construct(private readonly MemberEntitlements $entitlements) {}

    /** The amount after the member's Bonus XP boost (rewards.bonus_xp_boost, in percent). */
    public function boosted(User $user, int $amount): int
    {
        $boost = $this->entitlements->limit($user, MemberFeature::REWARDS_BONUS_XP_BOOST);
        $boost = $boost === null ? 0 : max(0, min(self::MAX_BOOST_PERCENT, $boost));

        return (int) round($amount * (100 + $boost) / 100);
    }

    /** Credit a grant's Bonus XP. Idempotent per grant. */
    public function creditGrant(RewardGrant $grant): void
    {
        $amount = (int) $grant->bonus_xp;
        if ($amount === 0) {
            return;
        }

        $this->write($grant->user_id, $amount, mb_substr((string) $grant->title, 0, 120), $grant->id, $grant->match_id, 'grant:'.$grant->id);
    }

    /** Take back a grant's Bonus XP with a negative row. Idempotent per grant. */
    public function reverseGrant(RewardGrant $grant): void
    {
        $credited = BonusXpEntry::query()->where('dedupe_key', 'grant:'.$grant->id)->value('amount');
        if ($credited === null || (int) $credited === 0) {
            return;
        }

        $this->write($grant->user_id, -1 * (int) $credited, 'Reversed: '.mb_substr((string) $grant->title, 0, 100), $grant->id, $grant->match_id, 'reversal:grant:'.$grant->id);
    }

    public function total(User $user): int
    {
        return BonusXpEntry::totalFor((int) $user->id);
    }

    private function write(int $userId, int $amount, string $reason, ?int $grantId, ?int $matchId, string $dedupe): void
    {
        try {
            BonusXpEntry::query()->create([
                'user_id' => $userId,
                'amount' => $amount,
                'reason' => $reason,
                'grant_id' => $grantId,
                'match_id' => $matchId,
                'dedupe_key' => $dedupe,
                'created_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Already written — the ledger is idempotent by design.
        }
    }
}
