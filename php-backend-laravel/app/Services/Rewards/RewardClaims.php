<?php

declare(strict_types=1);

namespace App\Services\Rewards;

use App\Models\Coupon;
use App\Models\MemberEntitlementOverride;
use App\Models\MemberPlan;
use App\Models\RewardGrant;
use App\Models\User;
use App\Services\Membership\MemberEntitlements;
use App\Support\Membership\MemberFeature;
use App\Support\Rewards\RewardTypes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Claiming an available reward. Only its owner can, only once: the state moves
 * available → claimed by a conditional UPDATE inside the same transaction that issues the
 * reward, so two taps (or two devices) can't claim twice, and a failed issue leaves it available.
 *
 *  - Haraan coupon: a new coupon owned by the player, single use, with its own random code.
 *  - Sponsor code: the code reserved at grant time is revealed to the owner.
 *  - Membership trial: time-limited member overrides — only for features the trial actually
 *    improves, so a trial can never downgrade a better plan the member already has.
 *  - Offer link: marked claimed; the app opens the link.
 */
final class RewardClaims
{
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(private readonly MemberEntitlements $entitlements) {}

    /** @throws RewardClaimException */
    public function claim(User $user, RewardGrant $grant): RewardGrant
    {
        if ((int) $grant->user_id !== (int) $user->id) {
            throw new RewardClaimException('Reward not found.', 'not_found', 404);
        }
        if (! RewardEngine::enabled()) {
            throw new RewardClaimException('Rewards are paused for a short while. Please try again soon.', 'rewards_paused', 503);
        }
        if ($grant->status === RewardGrant::AVAILABLE && $grant->expires_at !== null && $grant->expires_at->isPast()) {
            app(RewardLedger::class)->expire($grant);
            throw new RewardClaimException('This reward has expired.', 'expired', 410);
        }
        if ($grant->status !== RewardGrant::AVAILABLE) {
            throw new RewardClaimException(match ($grant->status) {
                RewardGrant::LOCKED => 'This reward is still locked.',
                RewardGrant::CLAIMED, RewardGrant::REDEEMED => 'You have already claimed this reward.',
                default => 'This reward can no longer be claimed.',
            }, 'not_claimable', 409);
        }

        return DB::transaction(function () use ($user, $grant): RewardGrant {
            $taken = RewardGrant::query()->whereKey($grant->id)->where('user_id', $user->id)
                ->where('status', RewardGrant::AVAILABLE)
                ->update(['status' => RewardGrant::CLAIMED, 'claimed_at' => now(), 'updated_at' => now()]);
            if ($taken !== 1) {
                throw new RewardClaimException('You have already claimed this reward.', 'not_claimable', 409);
            }

            $changes = match ($grant->type) {
                RewardTypes::HARAAN_COUPON => ['coupon_id' => $this->issueCoupon($user, $grant)->id],
                RewardTypes::MEMBERSHIP_TRIAL => ['override_ids' => $this->startTrial($user, $grant)],
                RewardTypes::SPONSOR_CODE => $grant->reward_code_id !== null ? [] : throw new RewardClaimException('This reward is no longer available.', 'not_claimable', 409),
                default => [],
            };

            if ($changes !== []) {
                RewardGrant::query()->whereKey($grant->id)->update($changes);
            }

            return $grant->fresh();
        });
    }

    private function issueCoupon(User $user, RewardGrant $grant): Coupon
    {
        $spec = (array) (($grant->value ?? [])['coupon'] ?? []);
        $spec = RewardTypes::normalizePayload(RewardTypes::HARAAN_COUPON, $spec);

        return Coupon::query()->create([
            'code' => $this->uniqueCode(),
            'type' => $spec['discount_type'],
            'discount' => $spec['discount'],
            'max_discount' => $spec['max_discount'],
            'min_order' => $spec['min_order'] ?? 0,
            'scope' => $spec['scope'],
            'event_id' => null,
            'venue_id' => $spec['venue_id'] ?? null,
            'max_uses' => 1,
            'per_customer_limit' => null,
            'uses' => 0,
            'active' => true,
            'expires_at' => now()->addDays((int) $spec['valid_days']),
            'eligibility' => 'all',
            'owner_user_id' => $user->id,
            'source' => 'reward',
            'reward_grant_id' => $grant->id,
        ]);
    }

    /** @return list<int> override ids */
    private function startTrial(User $user, RewardGrant $grant): array
    {
        $value = (array) ($grant->value ?? []);
        $plan = MemberPlan::query()->with('entitlements')->find((int) ($value['plan_id'] ?? 0));
        if ($plan === null) {
            throw new RewardClaimException('This trial is no longer available.', 'not_claimable', 409);
        }

        $current = $this->entitlements->for($user);
        $ends = now()->addDays(max(1, (int) ($value['days'] ?? 7)));
        $ids = [];

        foreach ($plan->entitlements as $row) {
            $key = (string) $row->feature_key;
            if (! $row->enabled || ! MemberFeature::exists($key)) {
                continue;
            }
            $better = MemberFeature::isBoolean($key)
                ? ! $current->allows($key)
                : ($current->limit($key) !== null && ($row->limit_value === null || $row->limit_value > $current->limit($key)));
            if (! $better) {
                continue;
            }

            $ids[] = MemberEntitlementOverride::query()->create([
                'user_id' => $user->id,
                'feature_key' => $key,
                'enabled' => true,
                'limit_value' => MemberFeature::isBoolean($key) ? null : $row->limit_value,
                'expires_at' => $ends,
                'reason' => mb_substr('Reward #'.$grant->id.': '.$grant->title, 0, 255),
            ])->id;
        }

        if ($ids === []) {
            throw new RewardClaimException('Your plan already includes everything in this trial.', 'already_included', 409);
        }

        MemberEntitlements::flush((int) $user->id);

        return $ids;
    }

    private function uniqueCode(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $code = 'HR';
            for ($c = 0; $c < 8; $c++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
            if (Coupon::findByCode($code) === null) {
                return $code;
            }
        }

        return 'HR'.strtoupper(Str::random(12));
    }
}
