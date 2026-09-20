<?php

declare(strict_types=1);

namespace Tests\Feature\Rewards;

use App\Models\LiveMatch;
use App\Models\RewardCode;
use App\Models\RewardCodePool;
use App\Models\RewardProgram;
use App\Models\RewardRule;
use App\Models\RewardSponsor;
use App\Models\User;
use App\Services\MatchCompletion;
use App\Services\MatchVerificationService;
use App\Support\PlatformRules;
use App\Support\Rewards\RewardConditions;
use App\Support\Rewards\RewardTypes;
use Tests\Feature\Membership\MembershipFixtures;

/** Shared setup for the reward suites: players, a finished match, programs and rules. */
trait RewardFixtures
{
    use MembershipFixtures;

    /**
     * A finished football match (home won 2–1) between the given registered players, plus a
     * guest on each side. Its follow-through (stats, rewards…) has run.
     *
     * @param  list<User>  $home
     * @param  list<User>  $away
     */
    protected function finishedMatch(array $home, array $away, array $attributes = [], bool $followThrough = true): LiveMatch
    {
        $squad = fn (array $users) => array_merge(
            array_map(fn (User $u) => ['id' => $u->player_id, 'name' => $u->name], $users),
            [['id' => null, 'name' => 'Guest '.count($users)]],
        );

        $match = LiveMatch::create(array_merge([
            'title' => 'Sunday game', 'home' => 'Reds', 'away' => 'Blues',
            'sport' => 'football', 'status' => 'Live', 'match_type' => 'casual', 'base_xp' => 25,
            'home_score' => 2, 'away_score' => 1,
            'home_squad' => $squad($home),
            'away_squad' => $squad($away),
            'is_private' => false,
        ], $attributes));

        $match->status = 'Completed';
        $match->save();

        if ($followThrough) {
            app(MatchCompletion::class)->followThrough((int) $match->id, true);
        }

        return $match->fresh();
    }

    protected function settle(LiveMatch $match, string $trust): LiveMatch
    {
        return MatchVerificationService::settle($match->fresh(), $trust);
    }

    protected function program(array $attributes = []): RewardProgram
    {
        return RewardProgram::query()->create(array_merge([
            'kind' => RewardProgram::KIND_HARAAN,
            'name' => 'Match rewards',
            'status' => 'live',
            'priority' => 10,
            'headline' => 'Thanks for playing',
        ], $attributes));
    }

    protected function sponsoredProgram(array $attributes = []): RewardProgram
    {
        $sponsor = RewardSponsor::query()->create(['name' => 'PayFast', 'category' => 'payments']);

        return $this->program(array_merge([
            'kind' => RewardProgram::KIND_SPONSORED,
            'sponsor_id' => $sponsor->id,
            'name' => 'PayFast cashback',
            'headline' => '₹50 cashback on PayFast',
            'disclosure' => 'Sponsored by PayFast',
        ], $attributes));
    }

    protected function rule(RewardProgram $program, string $type, array $payload, array $attributes = []): RewardRule
    {
        return RewardRule::query()->create(array_merge([
            'program_id' => $program->id,
            'name' => $type.' rule',
            'trigger' => RewardRule::TRIGGER_COMPLETED,
            'conditions' => RewardConditions::normalize($attributes['conditions'] ?? []),
            'reward_type' => $type,
            'payload' => RewardTypes::normalizePayload($type, $payload),
            'unlock_method' => RewardRule::UNLOCK_AUTO,
            'is_active' => true,
        ], array_diff_key($attributes, ['conditions' => true])));
    }

    /** @param list<string> $codes */
    protected function pool(array $codes): RewardCodePool
    {
        $pool = RewardCodePool::query()->create(['name' => 'PayFast codes', 'instructions' => 'Apply in the PayFast app']);
        foreach ($codes as $code) {
            RewardCode::query()->create(['pool_id' => $pool->id, 'code' => $code, 'code_hash' => RewardCode::hashOf($code)]);
        }

        return $pool;
    }

    /** @param array<string, mixed> $values */
    protected function rules(array $values): void
    {
        PlatformRules::save($values);
    }

    protected function enableRewardedAds(): void
    {
        $this->rules([
            'rewards.rewarded_ads_enabled' => true,
            'rewards.admob_ad_unit_id' => 'ca-app-pub-3940256099942544/5224354917',
        ]);
    }
}
