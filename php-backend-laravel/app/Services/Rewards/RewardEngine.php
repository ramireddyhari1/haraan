<?php

declare(strict_types=1);

namespace App\Services\Rewards;

use App\Models\LiveMatch;
use App\Models\RewardGrant;
use App\Models\RewardProgram;
use App\Models\RewardRule;
use App\Models\User;
use App\Services\Membership\MemberEntitlements;
use App\Support\ActionboardXp;
use App\Support\Membership\MemberFeature;
use App\Support\PlatformRules;
use App\Support\Rewards\RewardConditions;
use App\Support\Rewards\RewardContext;
use App\Support\Rewards\RewardGeo;
use App\Support\Rewards\RewardTypes;
use App\Support\Rewards\ZoneMatch;
use Illuminate\Support\Facades\DB;

/**
 * The post-match reward engine. Three entry points, all idempotent:
 *
 *  - onCompleted(match)  — the match finished (MatchCompletion::followThrough). Every registered
 *                          player in either squad: weekly streak, badges, and the rules that fire
 *                          on completion. Money-value rewards are granted LOCKED.
 *  - onSettled(match)    — the result settled (MatchVerificationService::settle). Locked rewards
 *                          unlock when the trust reaches rewards.min_trust_to_unlock; badges that
 *                          depend on the XP ledger unlock; rules that fire on settlement run.
 *  - onReopened(matchId) — the finish was undone. Unclaimed rewards from it are revoked (and
 *                          their Bonus XP reversed) so the re-finished match can grant again.
 *
 * It never writes competitive XP, never compares plan codes (MemberEntitlements only), and
 * never offers a rewarded ad to a member whose plan hides ads.
 */
final class RewardEngine
{
    public function __construct(
        private readonly MemberEntitlements $entitlements,
        private readonly RewardLedger $ledger,
        private readonly BadgeService $badges,
        private readonly WeeklyStreakService $streaks,
        private readonly RewardNotifier $notifier,
    ) {}

    public static function enabled(): bool
    {
        return PlatformRules::bool('rewards.enabled') && ! PlatformRules::bool('ops.rewards_disabled');
    }

    /** Rewarded ads can be offered at all (switches on, ad unit configured). */
    public static function rewardedAdsAvailable(): bool
    {
        return PlatformRules::bool('rewards.rewarded_ads_enabled')
            && ! PlatformRules::bool('ops.rewarded_ads_disabled')
            && PlatformRules::string('rewards.admob_ad_unit_id') !== '';
    }

    public function onCompleted(LiveMatch $match): void
    {
        if (! $this->eligibleMatch($match)) {
            return;
        }

        DB::table('reward_evaluations')->insertOrIgnore([
            'match_id' => $match->id, 'trigger' => RewardRule::TRIGGER_COMPLETED, 'evaluated_at' => now(),
        ]);

        // Where the match happened is a property of the match, not of the player, so it is
        // resolved once here rather than per participant.
        $geo = RewardGeo::forMatch($match);
        $targets = $this->targets($geo);

        foreach ($this->participants($match) as [$user, $side]) {
            $ctx = $this->context($user, $match, $side);
            $run = new RunResult;

            $this->baseline($user);
            $this->streak($user, $match, $run);
            $this->awardBadges($user, $match, $run);
            $this->applyRules($targets, $geo, RewardRule::TRIGGER_COMPLETED, $ctx, $run);

            $this->notifier->afterCompletion($user, $match, $run);
        }
    }

    public function onSettled(LiveMatch $match): void
    {
        $match = $match->fresh() ?? $match;
        if (! $this->eligibleMatch($match)) {
            return;
        }

        $minTrust = PlatformRules::string('rewards.min_trust_to_unlock');
        $reached = ActionboardXp::trustRank((string) ($match->trust_level ?? 'low'));

        $geo = RewardGeo::forMatch($match);
        $targets = $this->targets($geo);

        foreach ($this->participants($match) as [$user, $side]) {
            $run = new RunResult;
            $this->baseline($user);

            $waiting = RewardGrant::query()
                ->where('user_id', $user->id)->where('match_id', $match->id)
                ->where('status', RewardGrant::LOCKED)->where('needs_verification', true)
                ->get();

            foreach ($waiting as $grant) {
                // A location-targeted money reward carries its own, stricter bar — snapshotted
                // on the grant, so changing the rule later can't unlock what is already out.
                $floor = $grant->requiredTrustLevel() ?? $minTrust;

                if ($reached < ActionboardXp::trustRank($floor)) {
                    // Left locked (an organiser or a venue can still raise the trust); it
                    // expires on its date if nobody does. The player is told why.
                    $this->ledger->explain($grant, 'trust_too_low');

                    continue;
                }
                if ($this->ledger->clearLock($grant, 'verification')) {
                    $run->unlocked[] = $grant->fresh();
                } elseif ($grant->fresh()?->needs_ad) {
                    $run->waitingForAd[] = $grant->fresh();
                }
            }

            $this->awardBadges($user, $match, $run);
            $ctx = $this->context($user, $match, $side);
            $this->applyRules($targets, $geo, RewardRule::TRIGGER_SETTLED, $ctx, $run);

            $this->notifier->afterSettlement($user, $match, $run);
        }
    }

    public function onReopened(int $matchId): void
    {
        $grants = RewardGrant::query()->where('match_id', $matchId)
            ->whereNotNull('rule_id')
            ->where(fn ($q) => $q->whereIn('status', RewardGrant::OPEN)
                ->orWhere(fn ($b) => $b->where('type', RewardTypes::BONUS_XP)->where('status', RewardGrant::CLAIMED)))
            ->get();

        foreach ($grants as $grant) {
            $this->ledger->revoke($grant, 'match_reopened', null, true);
        }

        DB::table('reward_evaluations')->where('match_id', $matchId)->delete();
    }

    /** Has the completion trigger run for this match (used by the API's lazy evaluation)? */
    public function evaluated(LiveMatch $match): bool
    {
        return DB::table('reward_evaluations')
            ->where('match_id', $match->id)->where('trigger', RewardRule::TRIGGER_COMPLETED)->exists();
    }

    /**
     * Registered players in either squad, as [User, side]. Guests (no id) get nothing.
     *
     * @return list<array{0: User, 1: string}>
     */
    public function participants(LiveMatch $match): array
    {
        $ids = [];
        foreach (['home' => $match->home_squad, 'away' => $match->away_squad] as $side => $squad) {
            foreach ((array) $squad as $p) {
                $id = is_array($p) ? trim((string) ($p['id'] ?? '')) : '';
                if ($id !== '' && ! isset($ids[$id])) {
                    $ids[$id] = $side;
                }
            }
        }
        if ($ids === []) {
            return [];
        }

        return User::query()->whereIn('player_id', array_keys($ids))->orderBy('id')->get()
            ->map(fn (User $u): array => [$u, $ids[(string) $u->player_id]])
            ->values()->all();
    }

    public function isParticipant(LiveMatch $match, User $user): bool
    {
        return $match->sideOf($user) !== null;
    }

    private function eligibleMatch(LiveMatch $match): bool
    {
        return self::enabled()
            && $match->isFinished()
            && (! $match->is_private || PlatformRules::bool('rewards.private_matches'));
    }

    private function context(User $user, LiveMatch $match, string $side): RewardContext
    {
        return new RewardContext(
            user: $user,
            match: $match,
            side: $side,
            outcome: RewardContext::outcomeFor($match, $side),
            isPlayerOfMatch: $match->mom_player_id !== null && (string) $match->mom_player_id === (string) $user->player_id,
            trustLevel: (string) ($match->trust_level ?? 'low'),
            settled: in_array($match->verification_status, ['settled', 'expired'], true),
            minRegisteredPerSide: min($match->distinctRegisteredPlayers('home'), $match->distinctRegisteredPlayers('away')),
            playStreakWeeks: $this->streaks->forUser($user)['current'],
            entitlements: $this->entitlements->for($user),
        );
    }

    /**
     * The first time the engine meets a player, badges they already had are recorded as
     * celebrated — the first match after launch shouldn't announce ten old badges.
     */
    private function baseline(User $user): void
    {
        if ($user->rewards_baselined_at !== null) {
            return;
        }
        $this->badges->sync($user, null, false);
        User::query()->whereKey($user->id)->whereNull('rewards_baselined_at')->update(['rewards_baselined_at' => now()]);
        $user->rewards_baselined_at = now();
    }

    private function streak(User $user, LiveMatch $match, RunResult $run): void
    {
        $streak = $this->streaks->record($user, $match);
        $run->streak = $streak;

        if (! $streak['extended'] || $streak['current'] < 2) {
            return;
        }

        $bonus = PlatformRules::int('rewards.streak_bonus_xp');
        $grant = $this->ledger->record(
            $user,
            (int) $match->id,
            RewardTypes::STREAK,
            "streak:{$user->id}:{$streak['period']}",
            $streak['current'].'-week streak',
            'You played '.$streak['current'].' weeks in a row.',
            ['weeks' => $streak['current'], 'best' => $streak['best']],
            $bonus,
        );
        if ($grant !== null) {
            $run->streakGrant = $grant;
        }
    }

    private function awardBadges(User $user, LiveMatch $match, RunResult $run): void
    {
        foreach ($this->badges->sync($user, (int) $match->id, true) as $badge) {
            $def = $badge->definition;
            $run->badges[] = $badge;
            $this->ledger->record(
                $user,
                (int) $match->id,
                RewardTypes::BADGE,
                "badge:{$user->id}:{$badge->badge_key}",
                ($def?->name ?? $badge->badge_key).' badge',
                $def?->description,
                ['badge_key' => $badge->badge_key, 'tier' => $def?->tier, 'icon' => $def?->icon],
                (int) ($def?->bonus_xp ?? 0),
            );
        }
    }

    /**
     * The live programs this match qualifies for, each paired with the zone it matched.
     *
     * With location targeting off, every program is paired with "anywhere" — which is exactly
     * how programs behaved before zones existed, so the feature genuinely ships dark.
     *
     * @return list<array{0: RewardProgram, 1: ZoneMatch}>
     */
    private function targets(RewardGeo $geo): array
    {
        $programs = RewardProgram::liveWithRules();

        if (! RewardGeo::enabled()) {
            return $programs->map(fn (RewardProgram $p): array => [$p, ZoneMatch::anywhere()])->all();
        }

        return $geo->programTargets($programs);
    }

    /** @param list<array{0: RewardProgram, 1: ZoneMatch}> $targets */
    private function applyRules(array $targets, RewardGeo $geo, string $trigger, RewardContext $ctx, RunResult $run): void
    {
        if ($targets === []) {
            return;
        }

        $user = $ctx->user;
        $match = $ctx->match;
        $set = $ctx->entitlements;

        $offerLimit = $set->limit(MemberFeature::REWARDS_OFFERS_PER_MATCH);
        $perMatchCap = PlatformRules::int('rewards.max_grants_per_match');
        $dailyCap = PlatformRules::int('rewards.daily_grant_cap');
        $baseTrust = PlatformRules::string('rewards.min_trust_to_unlock');
        $reached = $ctx->settled ? ActionboardXp::trustRank($ctx->trustLevel) : -1;
        $groundKey = $geo->groundKey();

        foreach ($targets as [$program, $programZone]) {
            if (! $program->targetsSport($match->sport) || ! $program->targetsMatchType($match->match_type)) {
                continue;
            }
            if ($program->members_only && ! $set->allows(MemberFeature::REWARDS_MEMBER_PROGRAMS)) {
                continue;
            }

            foreach ($program->rules as $rule) {
                if ($rule->trigger !== $trigger || ! RewardTypes::isRuleType((string) $rule->reward_type)) {
                    continue;
                }
                if (! RewardConditions::passes((array) $rule->conditions, $ctx)) {
                    continue;
                }

                // A rule may narrow its program's geography; most inherit it untouched.
                $zone = RewardGeo::enabled() ? $geo->ruleTarget($rule, $programZone) : ZoneMatch::anywhere();
                if ($zone === null) {
                    continue;
                }

                $needsAd = $rule->unlock_method === RewardRule::UNLOCK_AD;
                if ($needsAd) {
                    if ($set->allows(MemberFeature::REWARDS_AD_UNLOCK_SKIP)) {
                        $needsAd = false;
                    } elseif ($set->allows(MemberFeature::ADS_HIDDEN) || ! self::rewardedAdsAvailable()) {
                        // Never show an ad to a no-ads member, and never promise one we can't serve.
                        continue;
                    }
                }

                $counts = $this->counts($user, $match, $rule);
                if ($counts['match'] >= $perMatchCap || $counts['today'] >= $dailyCap) {
                    return;
                }
                if ($program->isSponsored() && $offerLimit !== null && $counts['sponsored_match'] >= $offerLimit) {
                    break;
                }
                if ($rule->per_user_daily_cap !== null && $counts['rule_today'] >= $rule->per_user_daily_cap) {
                    continue;
                }
                if ($rule->per_user_total_cap !== null && $counts['rule_total'] >= $rule->per_user_total_cap) {
                    continue;
                }

                // Money won from a zone-targeted program needs a stricter result than money won
                // anywhere: location is the one input a player could try to fake, so the answer
                // is to demand more of the thing that is actually verified — the result.
                $floor = $rule->isMoneyValue() && $zone->isTargeted()
                    ? ActionboardXp::higherTrust($baseTrust, PlatformRules::string('rewards.geo_min_trust_for_zone_money'))
                    : $baseTrust;

                $needsVerification = $rule->isMoneyValue() && $reached < ActionboardXp::trustRank($floor);
                $grant = $this->ledger->grantFromRule(
                    $user, $match, $program, $rule, $needsVerification, $needsAd,
                    $zone, $rule->isMoneyValue() && $zone->isTargeted() ? $floor : null, $groundKey,
                );
                if ($grant !== null) {
                    $run->granted[] = $grant;
                }
            }
        }
    }

    /** @return array{match: int, today: int, sponsored_match: int, rule_today: int, rule_total: int} */
    private function counts(User $user, LiveMatch $match, RewardRule $rule): array
    {
        $base = RewardGrant::query()->where('user_id', $user->id)
            ->whereNotNull('rule_id')->where('status', '!=', RewardGrant::REVOKED);

        return [
            'match' => (clone $base)->where('match_id', $match->id)->count(),
            'today' => (clone $base)->where('created_at', '>=', now()->startOfDay())->count(),
            'sponsored_match' => (clone $base)->where('match_id', $match->id)->where('source', 'sponsored')->count(),
            'rule_today' => (clone $base)->where('rule_id', $rule->id)->where('created_at', '>=', now()->startOfDay())->count(),
            'rule_total' => (clone $base)->where('rule_id', $rule->id)->count(),
        ];
    }
}
