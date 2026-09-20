<?php

declare(strict_types=1);

namespace Tests\Feature\Rewards;

use App\Models\BadgeDefinition;
use App\Models\Coupon;
use App\Models\LiveMatch;
use App\Models\MemberEntitlementOverride;
use App\Models\PlayerBadge;
use App\Models\RewardGrant;
use App\Services\BookingService;
use App\Services\Membership\MemberEntitlements;
use App\Support\Membership\MemberFeature;
use App\Support\Rewards\RewardTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class RewardApiTest extends TestCase
{
    use RefreshDatabase;
    use RewardFixtures;

    public function test_the_match_screen_separates_pending_competitive_xp_from_bonus_xp_and_rewards(): void
    {
        $program = $this->program();
        $this->rule($program, RewardTypes::BONUS_XP, ['amount' => 20]);
        $this->rule($program, RewardTypes::HARAAN_COUPON, ['discount' => 50]);
        [$a, $b] = [$this->player(), $this->player()];
        $match = $this->finishedMatch([$a], [$b]);

        $res = $this->asMember($a)->getJson("/api/matches/{$match->id}/rewards")->assertOk();

        $res->assertJsonPath('data.competitive_xp.state', 'pending_verification')
            ->assertJsonPath('data.competitive_xp.xp', null)
            ->assertJsonPath('data.bonus_xp.this_match', 20)
            ->assertJsonPath('data.bonus_xp.counts_toward_leaderboard', false)
            ->assertJsonPath('data.viewer.outcome', 'won')
            ->assertJsonPath('data.rewards.locked.0.type', RewardTypes::HARAAN_COUPON)
            ->assertJsonPath('data.rewards.locked.0.lock_reasons', ['verification'])
            ->assertJsonPath('data.rewards.ready', [])
            ->assertJsonPath('data.celebration.seen', false);

        $this->settle($match, 'medium');
        $res = $this->asMember($a)->getJson("/api/matches/{$match->id}/rewards")->assertOk();
        $res->assertJsonPath('data.competitive_xp.state', 'settled')
            ->assertJsonPath('data.rewards.ready.0.claimable', true);
        self::assertIsInt($res->json('data.competitive_xp.xp'));
    }

    public function test_only_players_in_the_match_can_see_its_rewards(): void
    {
        [$a, $b, $stranger] = [$this->player(), $this->player(), $this->player()];
        $match = $this->finishedMatch([$a], [$b]);

        $this->asMember($stranger)->getJson("/api/matches/{$match->id}/rewards")->assertNotFound();
        $this->asMember($stranger)->postJson("/api/matches/{$match->id}/rewards/seen")->assertNotFound();
        $this->flushHeaders()->getJson("/api/matches/{$match->id}/rewards")->assertUnauthorized();
    }

    public function test_an_unfinished_match_has_no_rewards_yet(): void
    {
        $a = $this->player();
        $match = LiveMatch::create([
            'title' => 'Live', 'home' => 'A', 'away' => 'B', 'sport' => 'football', 'status' => 'Live',
            'home_squad' => [['id' => $a->player_id, 'name' => $a->name]], 'away_squad' => [],
        ]);

        $this->asMember($a)->getJson("/api/matches/{$match->id}/rewards")->assertStatus(409)->assertJsonPath('code', 'match_not_finished');
    }

    public function test_the_screen_evaluates_lazily_when_the_player_gets_there_first(): void
    {
        $this->rule($this->program(), RewardTypes::BONUS_XP, ['amount' => 9]);
        [$a, $b] = [$this->player(), $this->player()];
        $match = $this->finishedMatch([$a], [$b], [], false);

        $this->asMember($b)->getJson("/api/matches/{$match->id}/rewards")->assertOk()->assertJsonPath('data.bonus_xp.this_match', 9);
        self::assertSame(2, RewardGrant::query()->where('type', RewardTypes::BONUS_XP)->count(), 'everyone, once');
    }

    public function test_seen_marks_the_celebration_and_its_badges(): void
    {
        BadgeDefinition::query()->create(['key' => 'week1', 'name' => 'Turned up', 'metric' => 'play_streak_weeks', 'threshold' => 1]);
        $a = $this->player();
        $match = $this->finishedMatch([$a], []);

        $this->asMember($a)->getJson("/api/matches/{$match->id}/rewards")->assertJsonPath('data.badges.0.is_new', true);
        $this->asMember($a)->postJson("/api/matches/{$match->id}/rewards/seen")->assertOk();
        $this->asMember($a)->getJson("/api/matches/{$match->id}/rewards")
            ->assertJsonPath('data.celebration.seen', true)
            ->assertJsonPath('data.badges.0.is_new', false);
        self::assertNotNull(PlayerBadge::query()->sole()->celebrated_at);
    }

    public function test_another_players_reward_is_a_404_everywhere(): void
    {
        $this->rule($this->program(), RewardTypes::HARAAN_COUPON, ['discount' => 50]);
        [$a, $b] = [$this->player(), $this->player()];
        $match = $this->finishedMatch([$a], [$b]);
        $this->settle($match, 'medium');
        $grant = RewardGrant::query()->where('user_id', $a->id)->where('type', RewardTypes::HARAAN_COUPON)->sole();

        $this->asMember($b)->getJson("/api/rewards/{$grant->id}")->assertNotFound();
        $this->asMember($b)->postJson("/api/rewards/{$grant->id}/claim")->assertNotFound();
        $this->asMember($b)->postJson("/api/rewards/{$grant->id}/ad-session")->assertNotFound();
        self::assertSame(RewardGrant::AVAILABLE, $grant->fresh()->status);
    }

    public function test_claiming_a_coupon_issues_one_owned_single_use_code_once(): void
    {
        $this->rule($this->program(), RewardTypes::HARAAN_COUPON, ['discount' => 50, 'scope' => 'all', 'valid_days' => 14]);
        [$a, $b] = [$this->player(), $this->player()];
        $match = $this->finishedMatch([$a], [$b]);
        $this->settle($match, 'medium');
        $grant = RewardGrant::query()->where('user_id', $a->id)->where('type', RewardTypes::HARAAN_COUPON)->sole();

        $res = $this->asMember($a)->postJson("/api/rewards/{$grant->id}/claim")->assertOk();
        $code = (string) $res->json('data.claim.code');
        self::assertMatchesRegularExpression('/^HR[A-Z0-9]{8}$/', $code);

        $coupon = Coupon::query()->where('code', $code)->sole();
        self::assertSame($a->id, $coupon->owner_user_id);
        self::assertSame(1, $coupon->max_uses);
        self::assertSame('reward', $coupon->source);

        $this->asMember($a)->postJson("/api/rewards/{$grant->id}/claim")->assertStatus(409);
        self::assertSame(1, Coupon::query()->where('source', 'reward')->count());

        // Owned: the owner can apply it, nobody else can (same answer as a bad code).
        $bookings = app(BookingService::class);
        self::assertSame(50.0, $bookings->resolveCoupon($a, 1, $code, 500.0)['discount']);
        $theirs = $bookings->resolveCoupon($b, 1, $code, 500.0);
        self::assertNull($theirs['coupon']);
        self::assertSame('This code isn’t valid.', $theirs['message']);
        self::assertNull($bookings->resolveVenueCoupon($b, 1, $code, 500.0)['coupon']);

        // Using it marks the reward used.
        $coupon->recordUse();
        self::assertSame(RewardGrant::REDEEMED, $grant->fresh()->status);
        self::assertNull($bookings->resolveCoupon($a, 1, $code, 500.0)['coupon'], 'single use');
    }

    public function test_a_locked_reward_cannot_be_claimed(): void
    {
        $this->rule($this->program(), RewardTypes::HARAAN_COUPON, ['discount' => 50]);
        $a = $this->player();
        $this->finishedMatch([$a], []);
        $grant = RewardGrant::query()->where('user_id', $a->id)->where('type', RewardTypes::HARAAN_COUPON)->sole();

        $this->asMember($a)->postJson("/api/rewards/{$grant->id}/claim")->assertStatus(409)->assertJsonPath('code', 'not_claimable');
        self::assertSame(0, Coupon::query()->count());
    }

    public function test_a_sponsor_code_is_revealed_only_to_its_owner_after_claiming(): void
    {
        $pool = $this->pool(['PAYFAST-9876']);
        $this->rule($this->sponsoredProgram(), RewardTypes::SPONSOR_CODE, ['pool_id' => $pool->id]);
        $a = $this->player();
        $match = $this->finishedMatch([$a], []);
        $this->settle($match, 'medium');
        $grant = RewardGrant::query()->where('user_id', $a->id)->where('type', RewardTypes::SPONSOR_CODE)->sole();

        $before = $this->asMember($a)->getJson("/api/rewards/{$grant->id}")->assertOk();
        self::assertStringNotContainsString('PAYFAST-9876', $before->getContent());
        $this->asMember($a)->getJson("/api/matches/{$match->id}/rewards")->assertJsonPath('data.rewards.ready.0.sponsored', true);

        $this->asMember($a)->postJson("/api/rewards/{$grant->id}/claim")->assertOk()
            ->assertJsonPath('data.claim.code', 'PAYFAST-9876')
            ->assertJsonPath('data.claim.instructions', 'Apply in the PayFast app');

        $list = $this->asMember($a)->getJson('/api/rewards')->assertOk();
        self::assertStringNotContainsString('PAYFAST-9876', $list->getContent(), 'lists never carry codes');
        self::assertStringNotContainsString('PAYFAST-9876', (string) DB::table('reward_codes')->value('code'), 'encrypted at rest');
    }

    public function test_a_membership_trial_adds_time_limited_overrides_and_never_downgrades(): void
    {
        $pro = $this->plan('pro');
        $this->rule($this->program(), RewardTypes::MEMBERSHIP_TRIAL, ['plan_id' => $pro->id, 'days' => 7]);
        $free = $this->player();
        $hero = $this->player();
        $this->paidSubscription($hero, 'hero');
        $match = $this->finishedMatch([$free], [$hero]);
        $this->settle($match, 'medium');

        $grant = RewardGrant::query()->where('user_id', $free->id)->where('type', RewardTypes::MEMBERSHIP_TRIAL)->sole();
        $this->asMember($free)->postJson("/api/rewards/{$grant->id}/claim")->assertOk();

        MemberEntitlements::flush();
        self::assertTrue(app(MemberEntitlements::class)->allows($free, MemberFeature::ADS_HIDDEN));
        $override = MemberEntitlementOverride::query()->where('user_id', $free->id)->where('feature_key', MemberFeature::ADS_HIDDEN)->sole();
        self::assertTrue($override->expires_at->between(now()->addDays(6), now()->addDays(8)));

        $heroGrant = RewardGrant::query()->where('user_id', $hero->id)->where('type', RewardTypes::MEMBERSHIP_TRIAL)->sole();
        $this->asMember($hero)->postJson("/api/rewards/{$heroGrant->id}/claim")->assertStatus(409)->assertJsonPath('code', 'already_included');
        self::assertSame(RewardGrant::AVAILABLE, $heroGrant->fresh()->status, 'left for nothing, not burned');
        self::assertSame(0, MemberEntitlementOverride::query()->where('user_id', $hero->id)->count());
    }

    public function test_history_and_summary_are_the_callers_own(): void
    {
        $this->rule($this->program(), RewardTypes::BONUS_XP, ['amount' => 5]);
        [$a, $b] = [$this->player(), $this->player()];
        $this->finishedMatch([$a], [$b]);

        $this->asMember($a)->getJson('/api/rewards')->assertOk()->assertJsonCount(1, 'data');
        $this->asMember($a)->getJson('/api/rewards/summary')->assertOk()->assertJsonPath('data.bonus_xp_total', 5);
    }

    public function test_claims_stop_under_the_emergency_switch(): void
    {
        $this->rule($this->program(), RewardTypes::HARAAN_COUPON, ['discount' => 50]);
        $a = $this->player();
        $match = $this->finishedMatch([$a], []);
        $this->settle($match, 'medium');
        $grant = RewardGrant::query()->where('user_id', $a->id)->where('type', RewardTypes::HARAAN_COUPON)->sole();

        $this->rules(['ops.rewards_disabled' => true]);
        $this->asMember($a)->postJson("/api/rewards/{$grant->id}/claim")->assertStatus(503);
    }

    public function test_the_profile_keeps_its_achievement_shape_with_persisted_badges(): void
    {
        $a = $this->player();
        $res = $this->asMember($a)->getJson('/api/players/me')->assertOk();

        $keys = array_column((array) $res->json('achievements') ?: (array) $res->json('data.achievements'), 'key');
        self::assertSame(['first_match', 'first_win', 'fifty', 'century', 'mom', 'mvp5', 'streak5', 'veteran', 'top100', 'wkts50', 'week_streak4', 'week_streak12'], $keys);
    }

    public function test_config_tells_a_no_ads_member_there_are_no_rewarded_ads(): void
    {
        $this->enableRewardedAds();
        $free = $this->player();
        $pro = $this->player();
        $this->paidSubscription($pro, 'pro');

        $this->asMember($free)->getJson('/api/config')->assertJsonPath('rewards.rewarded_ads', true);
        $this->asMember($pro)->getJson('/api/config')->assertJsonPath('rewards.rewarded_ads', false);
    }

    public function test_the_screen_reports_the_nearest_badge_and_this_weeks_streak(): void
    {
        [$a, $b] = [$this->player(), $this->player()];
        $match = $this->finishedMatch([$a], [$b]);

        $res = $this->asMember($a)->getJson("/api/matches/{$match->id}/rewards")->assertOk();
        $res->assertJsonPath('data.streak.played_this_week', true)
            ->assertJsonPath('data.match.home_score', 2)
            ->assertJsonPath('data.match.away_score', 1)
            ->assertJsonPath('data.match.match_type', 'casual');

        // Settled once: 1 of 10 matches toward "10 Matches" — the closest real progress.
        $this->settle($match, 'medium');
        $next = $this->asMember($a)->getJson("/api/matches/{$match->id}/rewards")->json('data.next_badge');
        self::assertNotNull($next);
        self::assertLessThan($next['threshold'], $next['value']);
        self::assertGreaterThanOrEqual(0, $next['value']);
    }
}
