<?php

declare(strict_types=1);

namespace App\Services\Rewards;

use App\Models\LiveMatch;
use App\Models\MemberPlan;
use App\Models\RewardCode;
use App\Models\RewardCodePool;
use App\Models\RewardGrant;
use App\Models\RewardProgram;
use App\Models\RewardRule;
use App\Models\User;
use App\Support\PlatformRules;
use App\Support\Rewards\RewardTypes;
use App\Support\Rewards\ZoneMatch;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Every write to reward_grants goes through here, so the state machine lives in one place.
 *
 * Duplication is impossible by construction:
 *  - a grant's dedupe_key is unique, so the same rule can't grant twice for the same match;
 *  - program budgets are spent by a single conditional UPDATE (`grants_count < budget`), so
 *    two matches finishing together can't overspend a sponsor;
 *  - a sponsor code is taken by `UPDATE … WHERE grant_id IS NULL`, so one code has one owner;
 *  - every state change is a conditional UPDATE on the state it expects to leave.
 * Each grant is created inside a transaction: if the budget or the pool runs out, nothing is kept.
 */
final class RewardLedger
{
    public function __construct(private readonly BonusXp $bonusXp) {}

    public static function ruleDedupeKey(RewardRule $rule, LiveMatch $match, User $user): string
    {
        return "rule:{$rule->id}:match:{$match->id}:user:{$user->id}";
    }

    /**
     * Grant a rule's reward. Returns null when it already exists, the budget is spent or the
     * code pool is empty.
     */
    public function grantFromRule(User $user, LiveMatch $match, RewardProgram $program, RewardRule $rule, bool $needsVerification, bool $needsAd, ?ZoneMatch $zone = null, ?string $minTrust = null, ?string $groundKey = null): ?RewardGrant
    {
        $dedupe = self::ruleDedupeKey($rule, $match, $user);
        if (RewardGrant::query()->where('dedupe_key', $dedupe)->exists()) {
            return null;
        }

        $payload = (array) $rule->payload;
        $locked = $needsVerification || $needsAd;
        $days = $rule->expires_after_days ?: PlatformRules::int('rewards.grant_expiry_days');
        $geo = $zone?->snapshot($minTrust);

        try {
            return DB::transaction(function () use ($user, $match, $program, $rule, $payload, $dedupe, $locked, $needsVerification, $needsAd, $days, $zone, $geo, $groundKey): ?RewardGrant {
                if (! $this->spendBudget($program)) {
                    throw new RewardUnavailable;
                }

                // A location-targeted grant also spends the ground's daily allowance, so one
                // group at one turf can't take a sponsor's whole city budget over a weekend.
                if ($geo !== null && ! $this->spendGroundDay($groundKey)) {
                    throw new RewardUnavailable;
                }

                $bonus = $rule->reward_type === RewardTypes::BONUS_XP
                    ? $this->bonusXp->boosted($user, (int) ($payload['amount'] ?? 0))
                    : null;

                $grant = RewardGrant::query()->create([
                    'user_id' => $user->id,
                    'match_id' => $match->id,
                    'program_id' => $program->id,
                    'rule_id' => $rule->id,
                    'zone_id' => $zone?->zone?->id,
                    'source' => $program->isSponsored() ? 'sponsored' : 'haraan',
                    'type' => $rule->reward_type,
                    'status' => $locked ? RewardGrant::LOCKED : RewardGrant::AVAILABLE,
                    'needs_verification' => $needsVerification,
                    'needs_ad' => $needsAd,
                    'dedupe_key' => $dedupe,
                    'title' => $this->titleFor($program, $rule, $bonus),
                    'description' => $program->description,
                    'value' => $this->valueFor($program, $rule, $geo),
                    'bonus_xp' => $bonus,
                    'unlocked_at' => $locked ? null : now(),
                    'expires_at' => now()->addDays($days),
                ]);

                if ($rule->reward_type === RewardTypes::SPONSOR_CODE && ! $this->reserveCode($grant, (int) ($payload['pool_id'] ?? 0))) {
                    throw new RewardUnavailable;
                }

                if (! $locked) {
                    $this->afterUnlock($grant);
                }

                return $grant->fresh();
            });
        } catch (UniqueConstraintViolationException|RewardUnavailable) {
            return null;
        }
    }

    /**
     * A grant that records something earned (badge, streak) rather than a rule's reward.
     * Claimed on creation; Bonus XP on it is credited at once.
     */
    public function record(User $user, ?int $matchId, string $type, string $dedupe, string $title, ?string $description, array $value, int $bonusXp = 0): ?RewardGrant
    {
        try {
            $grant = RewardGrant::query()->create([
                'user_id' => $user->id,
                'match_id' => $matchId,
                'source' => 'haraan',
                'type' => $type,
                'status' => RewardGrant::CLAIMED,
                'dedupe_key' => $dedupe,
                'title' => $title,
                'description' => $description,
                'value' => $value,
                'bonus_xp' => $bonusXp > 0 ? $this->bonusXp->boosted($user, $bonusXp) : null,
                'unlocked_at' => now(),
                'claimed_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        $this->bonusXp->creditGrant($grant);

        return $grant;
    }

    /**
     * Clear one lock ('verification' | 'rewarded_ad'). Returns true when this call made the
     * grant available.
     */
    public function clearLock(RewardGrant $grant, string $lock): bool
    {
        $column = $lock === 'verification' ? 'needs_verification' : 'needs_ad';

        RewardGrant::query()->whereKey($grant->id)->where('status', RewardGrant::LOCKED)
            ->update([$column => false, 'status_reason' => null, 'updated_at' => now()]);

        $opened = RewardGrant::query()->whereKey($grant->id)
            ->where('status', RewardGrant::LOCKED)
            ->where('needs_verification', false)
            ->where('needs_ad', false)
            ->update(['status' => RewardGrant::AVAILABLE, 'unlocked_at' => now(), 'updated_at' => now()]);

        if ($opened === 1) {
            $this->afterUnlock($grant->fresh());

            return true;
        }

        return false;
    }

    /** Note why a locked grant is still waiting (shown to the player). */
    public function explain(RewardGrant $grant, string $reason): void
    {
        RewardGrant::query()->whereKey($grant->id)->where('status', RewardGrant::LOCKED)
            ->update(['status_reason' => $reason, 'updated_at' => now()]);
    }

    public function expire(RewardGrant $grant, string $reason = 'expired'): bool
    {
        $done = RewardGrant::query()->whereKey($grant->id)->whereIn('status', RewardGrant::OPEN)
            ->update(['status' => RewardGrant::EXPIRED, 'status_reason' => $reason, 'updated_at' => now()]) === 1;

        if ($done) {
            $this->releaseCode($grant);
        }

        return $done;
    }

    /**
     * Revoke a grant that hasn't been claimed. Bonus XP already credited for it is reversed.
     * With $freeKey the dedupe key is suffixed so the same rule can grant again (a reopened match
     * that finishes again).
     */
    public function revoke(RewardGrant $grant, string $reason, ?int $byUserId = null, bool $freeKey = false): bool
    {
        $changes = [
            'status' => RewardGrant::REVOKED,
            'status_reason' => mb_substr($reason, 0, 60),
            'revoked_at' => now(),
            'revoked_by' => $byUserId,
            'updated_at' => now(),
        ];
        if ($freeKey) {
            $changes['dedupe_key'] = mb_substr($grant->dedupe_key, 0, 150).':revoked:'.$grant->id;
        }

        $open = in_array($grant->status, RewardGrant::OPEN, true);
        $autoCredited = $grant->type === RewardTypes::BONUS_XP && $grant->status === RewardGrant::CLAIMED;

        if (! $open && ! $autoCredited) {
            return false;
        }

        $done = RewardGrant::query()->whereKey($grant->id)->where('status', $grant->status)->update($changes) === 1;

        if ($done) {
            $this->releaseCode($grant);
            $this->bonusXp->reverseGrant($grant);
        }

        return $done;
    }

    public function markRedeemedForCoupon(int $couponId): void
    {
        RewardGrant::query()->where('coupon_id', $couponId)->where('status', RewardGrant::CLAIMED)
            ->update(['status' => RewardGrant::REDEEMED, 'redeemed_at' => now(), 'updated_at' => now()]);
    }

    /** Bonus XP needs no claim: once available it is credited and marked claimed. */
    private function afterUnlock(RewardGrant $grant): void
    {
        if ($grant->type !== RewardTypes::BONUS_XP) {
            return;
        }

        $claimed = RewardGrant::query()->whereKey($grant->id)->where('status', RewardGrant::AVAILABLE)
            ->update(['status' => RewardGrant::CLAIMED, 'claimed_at' => now(), 'updated_at' => now()]);

        if ($claimed === 1) {
            $this->bonusXp->creditGrant($grant);
        }
    }

    private function spendBudget(RewardProgram $program): bool
    {
        $spent = RewardProgram::query()->whereKey($program->id)
            ->when($program->budget_total !== null, fn ($q) => $q->where('grants_count', '<', (int) $program->budget_total))
            ->increment('grants_count');

        if ($spent === 0) {
            return false;
        }

        if ($program->budget_daily === null) {
            return true;
        }

        $day = now()->toDateString();
        DB::table('reward_program_days')->insertOrIgnore(['program_id' => $program->id, 'day' => $day, 'grants' => 0]);

        return DB::table('reward_program_days')
            ->where('program_id', $program->id)->where('day', $day)
            ->where('grants', '<', (int) $program->budget_daily)
            ->increment('grants') === 1;
    }

    /**
     * Spend one of a ground's daily allowance, with the same conditional-UPDATE pattern the
     * program budget uses — two matches finishing at the same turf in the same second cannot
     * both slip past the cap. A match we can't place (no ground, no fix) is not rate-limited,
     * because it could never have matched a zone in the first place.
     */
    private function spendGroundDay(?string $groundKey): bool
    {
        if ($groundKey === null) {
            return true;
        }

        $cap = PlatformRules::int('rewards.geo_ground_grants_per_day');
        $day = now()->toDateString();

        DB::table('reward_ground_days')->insertOrIgnore(['ground_key' => $groundKey, 'day' => $day, 'grants' => 0]);

        return DB::table('reward_ground_days')
            ->where('ground_key', $groundKey)->where('day', $day)
            ->where('grants', '<', $cap)
            ->increment('grants') === 1;
    }

    private function reserveCode(RewardGrant $grant, int $poolId): bool
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $id = RewardCode::query()->where('pool_id', $poolId)->whereNull('grant_id')->orderBy('id')->value('id');
            if ($id === null) {
                return false;
            }
            $taken = RewardCode::query()->whereKey($id)->whereNull('grant_id')
                ->update(['grant_id' => $grant->id, 'assigned_at' => now(), 'updated_at' => now()]);
            if ($taken === 1) {
                RewardGrant::query()->whereKey($grant->id)->update(['reward_code_id' => $id]);

                return true;
            }
        }

        return false;
    }

    /** A code that was reserved but never revealed goes back to the pool. */
    private function releaseCode(RewardGrant $grant): void
    {
        if ($grant->reward_code_id === null || $grant->claimed_at !== null) {
            return;
        }
        RewardCode::query()->whereKey($grant->reward_code_id)->where('grant_id', $grant->id)
            ->update(['grant_id' => null, 'assigned_at' => null, 'updated_at' => now()]);
    }

    private function titleFor(RewardProgram $program, RewardRule $rule, ?int $bonus): string
    {
        $p = (array) $rule->payload;

        $title = match ($rule->reward_type) {
            RewardTypes::BONUS_XP => '+'.(int) $bonus.' Bonus XP',
            RewardTypes::HARAAN_COUPON => ($p['discount_type'] ?? 'fixed') === 'percent'
                ? (int) $p['discount'].'% off your next booking'
                : '₹'.number_format((int) ($p['discount'] ?? 0)).' off your next booking',
            RewardTypes::MEMBERSHIP_TRIAL => (int) ($p['days'] ?? 7).' days of '.(MemberPlan::query()->whereKey($p['plan_id'] ?? 0)->value('name') ?? 'membership'),
            default => $program->headline ?: $rule->name,
        };

        return mb_substr($title, 0, 160);
    }

    /**
     * A snapshot of what was promised, safe to show the owner.
     *
     * `geo` records why a location-targeted grant was given — the zone, the measured distance
     * and whether the position came from the match's own fix, its ground or a name match. Kept
     * here rather than read back through `zone_id` so the answer survives the zone later being
     * moved, resized or deleted.
     *
     * @param  array<string, mixed>|null  $geo
     * @return array<string, mixed>
     */
    private function valueFor(RewardProgram $program, RewardRule $rule, ?array $geo = null): array
    {
        $p = (array) $rule->payload;
        $value = [
            'geo' => $geo,
            'sponsored' => $program->isSponsored(),
            'program' => $program->name,
            'headline' => $program->headline,
            'disclosure' => $program->disclosure,
            'terms_url' => $program->terms_url,
            'brand_color' => $program->brand_color ?: $program->sponsor?->brand_color,
            'card_image' => $program->card_image,
            'sponsor' => $program->sponsor === null ? null : [
                'name' => $program->sponsor->name,
                'logo' => $program->sponsor->logo,
                'category' => $program->sponsor->category,
            ],
        ];

        return $value + match ($rule->reward_type) {
            RewardTypes::HARAAN_COUPON => ['coupon' => $p],
            RewardTypes::MEMBERSHIP_TRIAL => ['plan_id' => (int) ($p['plan_id'] ?? 0), 'days' => (int) ($p['days'] ?? 7)],
            RewardTypes::OFFER_LINK => ['cta_text' => $p['cta_text'] ?? 'Open offer'],
            RewardTypes::SPONSOR_CODE => ['instructions' => RewardCodePool::query()->whereKey($p['pool_id'] ?? 0)->value('instructions')],
            default => [],
        };
    }
}
