<?php

declare(strict_types=1);

namespace Tests\Feature\Rewards;

use App\Models\BadgeDefinition;
use App\Models\BonusXpEntry;
use App\Models\MatchXpLedger;
use App\Models\Notification;
use App\Models\PlayerBadge;
use App\Models\PlayerStreak;
use App\Models\RewardCode;
use App\Models\RewardGrant;
use App\Models\RewardProgram;
use App\Services\MatchCompletion;
use App\Services\Rewards\RewardEngine;
use App\Services\Rewards\RewardMaintenance;
use App\Support\Rewards\RewardTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class RewardEngineTest extends TestCase
{
    use RefreshDatabase;
    use RewardFixtures;

    public function test_every_registered_player_gets_bonus_xp_and_guests_get_nothing(): void
    {
        $this->rule($this->program(), RewardTypes::BONUS_XP, ['amount' => 20]);
        [$a, $b, $c] = [$this->player(), $this->player(), $this->player()];

        $match = $this->finishedMatch([$a, $b], [$c]);

        foreach ([$a, $b, $c] as $user) {
            $grant = RewardGrant::query()->where('user_id', $user->id)->where('match_id', $match->id)->where('type', RewardTypes::BONUS_XP)->sole();
            self::assertSame(RewardGrant::CLAIMED, $grant->status, 'Bonus XP needs no claim');
            self::assertSame(20, BonusXpEntry::totalFor($user->id));
        }
        self::assertSame(3, RewardGrant::query()->where('type', RewardTypes::BONUS_XP)->count(), 'guests (id: null) get nothing');
    }

    public function test_bonus_xp_never_touches_competitive_xp_or_the_leaderboard_ledger(): void
    {
        $this->rule($this->program(), RewardTypes::BONUS_XP, ['amount' => 500]);
        [$a, $b] = [$this->player(), $this->player()];

        $match = $this->finishedMatch([$a], [$b]);
        self::assertSame(0, MatchXpLedger::query()->count(), 'no competitive XP before settlement');
        self::assertSame(0, (int) $a->fresh()->casual_xp);
        self::assertSame(0, (int) $a->fresh()->ranked_xp);

        $this->settle($match, 'medium');
        $competitive = (int) MatchXpLedger::query()->where('player_id', $a->player_id)->sum('xp');

        // Competitive XP is exactly the ledger's own formula; the 500 Bonus XP is nowhere in it.
        self::assertSame($competitive, (int) $a->fresh()->casual_xp + (int) $a->fresh()->ranked_xp);
        self::assertLessThan(500, $competitive);
        self::assertSame(500, BonusXpEntry::totalFor($a->id));
    }

    public function test_running_the_engine_again_grants_nothing_twice(): void
    {
        $this->rule($this->program(), RewardTypes::BONUS_XP, ['amount' => 10]);
        [$a, $b] = [$this->player(), $this->player()];
        $match = $this->finishedMatch([$a], [$b]);

        app(MatchCompletion::class)->followThrough((int) $match->id, false);
        app(RewardEngine::class)->onCompleted($match);
        app(RewardEngine::class)->onCompleted($match);

        self::assertSame(1, RewardGrant::query()->where('user_id', $a->id)->where('type', RewardTypes::BONUS_XP)->count());
        self::assertSame(10, BonusXpEntry::totalFor($a->id));
    }

    public function test_money_value_rewards_start_locked_and_unlock_only_on_a_trusted_result(): void
    {
        $this->rule($this->program(), RewardTypes::HARAAN_COUPON, ['discount_type' => 'fixed', 'discount' => 50, 'scope' => 'all', 'valid_days' => 10]);
        [$a, $b] = [$this->player(), $this->player()];
        $match = $this->finishedMatch([$a], [$b]);

        $grant = RewardGrant::query()->where('user_id', $a->id)->sole();
        self::assertSame(RewardGrant::LOCKED, $grant->status);
        self::assertSame(['verification'], $grant->lockReasons());

        $this->settle($match, 'medium');

        self::assertSame(RewardGrant::AVAILABLE, $grant->fresh()->status);
        self::assertNull($grant->fresh()->coupon_id, 'the coupon itself is issued on claim');
    }

    public function test_a_low_trust_result_keeps_money_rewards_locked_until_they_expire(): void
    {
        $this->rule($this->program(), RewardTypes::HARAAN_COUPON, ['discount_type' => 'percent', 'discount' => 10, 'scope' => 'event'], ['expires_after_days' => 3]);
        [$a, $b] = [$this->player(), $this->player()];
        $match = $this->finishedMatch([$a], [$b]);

        $this->settle($match, 'low');
        $grant = RewardGrant::query()->where('user_id', $a->id)->where('type', RewardTypes::HARAAN_COUPON)->sole();
        self::assertSame(RewardGrant::LOCKED, $grant->status);
        self::assertSame('trust_too_low', $grant->status_reason);

        // An organiser can still raise the trust later and unlock it.
        $this->settle($match, 'high');
        self::assertSame(RewardGrant::AVAILABLE, $grant->fresh()->status);
    }

    public function test_the_admin_can_raise_the_trust_needed_to_unlock(): void
    {
        $this->rules(['rewards.min_trust_to_unlock' => 'verified']);
        $this->rule($this->program(), RewardTypes::HARAAN_COUPON, ['discount' => 50]);
        [$a, $b] = [$this->player(), $this->player()];
        $match = $this->finishedMatch([$a], [$b]);

        $this->settle($match, 'high');
        self::assertSame(RewardGrant::LOCKED, RewardGrant::query()->where('user_id', $a->id)->where('type', RewardTypes::HARAAN_COUPON)->sole()->status);
    }

    public function test_locked_rewards_expire_and_release_their_sponsor_code(): void
    {
        $pool = $this->pool(['PAYFAST-AAAA']);
        $this->rule($this->sponsoredProgram(), RewardTypes::SPONSOR_CODE, ['pool_id' => $pool->id], ['expires_after_days' => 1]);
        [$a, $b] = [$this->player(), $this->player()];
        $this->finishedMatch([$a], []);

        $grant = RewardGrant::query()->where('user_id', $a->id)->sole();
        self::assertNotNull(RewardCode::query()->sole()->grant_id, 'the code is reserved at grant time');

        Carbon::setTestNow(now()->addDays(2));
        app(RewardMaintenance::class)->run();
        Carbon::setTestNow();

        self::assertSame(RewardGrant::EXPIRED, $grant->fresh()->status);
        self::assertNull(RewardCode::query()->sole()->grant_id, 'an unrevealed code goes back to the pool');
    }

    public function test_a_code_pool_gives_each_code_to_exactly_one_player(): void
    {
        $pool = $this->pool(['ONLY-ONE-1234']);
        $this->rule($this->sponsoredProgram(), RewardTypes::SPONSOR_CODE, ['pool_id' => $pool->id]);
        [$a, $b] = [$this->player(), $this->player()];

        $this->finishedMatch([$a], [$b]);

        self::assertSame(1, RewardGrant::query()->where('type', RewardTypes::SPONSOR_CODE)->count(), 'the second player finds the pool empty');
        self::assertSame(1, RewardCode::query()->whereNotNull('grant_id')->count());
    }

    public function test_a_program_budget_is_never_overspent(): void
    {
        $this->rule($this->program(['budget_total' => 2]), RewardTypes::BONUS_XP, ['amount' => 5]);
        $players = [$this->player(), $this->player(), $this->player()];

        $this->finishedMatch([$players[0], $players[1]], [$players[2]]);

        self::assertSame(2, RewardGrant::query()->where('type', RewardTypes::BONUS_XP)->count());
        self::assertSame(2, (int) RewardProgram::query()->sole()->grants_count);
    }

    public function test_reopening_a_match_revokes_its_unclaimed_rewards_and_reverses_bonus_xp(): void
    {
        $program = $this->program();
        $this->rule($program, RewardTypes::BONUS_XP, ['amount' => 30]);
        $this->rule($program, RewardTypes::HARAAN_COUPON, ['discount' => 40]);
        [$a, $b] = [$this->player(), $this->player()];
        $match = $this->finishedMatch([$a], [$b]);
        self::assertSame(30, BonusXpEntry::totalFor($a->id));

        app(RewardEngine::class)->onReopened((int) $match->id);

        self::assertSame(0, BonusXpEntry::totalFor($a->id), 'Bonus XP is reversed with a negative entry');
        self::assertSame(0, RewardGrant::query()->where('user_id', $a->id)->whereNotNull('rule_id')->where('status', '!=', RewardGrant::REVOKED)->count());

        // Finishing again grants again (the revoked rows stay as history).
        app(RewardEngine::class)->onCompleted($match->fresh());
        self::assertSame(30, BonusXpEntry::totalFor($a->id));
        self::assertSame(2, RewardGrant::query()->where('user_id', $a->id)->where('status', RewardGrant::REVOKED)->count());
    }

    public function test_private_matches_earn_no_rewards_unless_the_admin_allows_it(): void
    {
        $this->rule($this->program(), RewardTypes::BONUS_XP, ['amount' => 5]);
        [$a, $b] = [$this->player(), $this->player()];

        $this->finishedMatch([$a], [$b], ['is_private' => true]);
        self::assertSame(0, RewardGrant::query()->count());

        $this->rules(['rewards.private_matches' => true]);
        $this->finishedMatch([$a], [$b], ['is_private' => true]);
        self::assertSame(2, RewardGrant::query()->where('type', RewardTypes::BONUS_XP)->count());
    }

    public function test_the_emergency_switch_stops_the_engine(): void
    {
        $this->rule($this->program(), RewardTypes::BONUS_XP, ['amount' => 5]);
        $this->rules(['ops.rewards_disabled' => true]);

        $this->finishedMatch([$this->player()], [$this->player()]);

        self::assertSame(0, RewardGrant::query()->count());
        self::assertSame(0, PlayerStreak::query()->count());
    }

    public function test_result_conditions_use_the_real_scoreline(): void
    {
        $this->rule($this->program(), RewardTypes::BONUS_XP, ['amount' => 15], ['conditions' => ['result' => 'won']]);
        [$winner, $loser] = [$this->player(), $this->player()];

        $this->finishedMatch([$winner], [$loser]); // home 2–1

        self::assertSame(15, BonusXpEntry::totalFor($winner->id));
        self::assertSame(0, BonusXpEntry::totalFor($loser->id));
    }

    public function test_per_match_and_per_rule_caps_hold(): void
    {
        $this->rules(['rewards.max_grants_per_match' => 1]);
        $program = $this->program();
        $this->rule($program, RewardTypes::BONUS_XP, ['amount' => 5]);
        $this->rule($program, RewardTypes::BONUS_XP, ['amount' => 6]);
        $a = $this->player();

        $this->finishedMatch([$a], []);
        self::assertSame(1, RewardGrant::query()->where('user_id', $a->id)->whereNotNull('rule_id')->count());

        $this->rules(['rewards.max_grants_per_match' => 10]);
        $capped = $this->rule($program, RewardTypes::BONUS_XP, ['amount' => 7], ['per_user_total_cap' => 1]);
        $this->finishedMatch([$a], []);
        $this->finishedMatch([$a], []);
        self::assertSame(1, RewardGrant::query()->where('rule_id', $capped->id)->count());
    }

    public function test_the_sponsored_offer_limit_comes_from_the_members_plan(): void
    {
        $program = $this->sponsoredProgram();
        $this->rule($program, RewardTypes::OFFER_LINK, ['url' => 'https://payfast.example/a']);
        $this->rule($program, RewardTypes::OFFER_LINK, ['url' => 'https://payfast.example/b']);
        $free = $this->player();
        $pro = $this->player();
        $this->paidSubscription($pro, 'pro');

        $this->finishedMatch([$free], [$pro]);

        self::assertSame(1, RewardGrant::query()->where('user_id', $free->id)->count(), 'Free: 1 offer per match');
        self::assertSame(2, RewardGrant::query()->where('user_id', $pro->id)->count(), 'Pro: 2 offers per match');
    }

    public function test_member_only_programs_and_the_bonus_xp_boost_go_through_entitlements(): void
    {
        $this->rule($this->program(['members_only' => true]), RewardTypes::BONUS_XP, ['amount' => 100]);
        $free = $this->player();
        $pro = $this->player();
        $this->paidSubscription($pro, 'pro');

        $this->finishedMatch([$free], [$pro]);

        self::assertSame(0, BonusXpEntry::totalFor($free->id));
        self::assertSame(110, BonusXpEntry::totalFor($pro->id), 'Pro gets its 10% Bonus XP boost');
    }

    public function test_a_no_ads_member_is_never_given_a_watch_to_unlock_reward(): void
    {
        $this->enableRewardedAds();
        $this->rule($this->program(), RewardTypes::HARAAN_COUPON, ['discount' => 20], ['unlock_method' => 'rewarded_ad']);
        $free = $this->player();
        $pro = $this->player();
        $this->paidSubscription($pro, 'pro');

        $this->finishedMatch([$free], [$pro]);

        self::assertTrue(RewardGrant::query()->where('user_id', $free->id)->sole()->needs_ad);
        $proGrant = RewardGrant::query()->where('user_id', $pro->id)->sole();
        self::assertFalse($proGrant->needs_ad, 'Pro unlocks without the video (rewards.ad_unlock_skip)');
    }

    public function test_ad_unlock_rules_are_skipped_when_rewarded_ads_are_off(): void
    {
        $this->rule($this->program(), RewardTypes::BONUS_XP, ['amount' => 20], ['unlock_method' => 'rewarded_ad']);
        $this->finishedMatch([$this->player()], []);

        self::assertSame(0, RewardGrant::query()->where('type', RewardTypes::BONUS_XP)->count(), 'never promise a video we can’t serve');
    }

    public function test_the_weekly_streak_extends_once_a_week_and_pays_its_bonus(): void
    {
        $this->rules(['rewards.streak_bonus_xp' => 12]);
        $a = $this->player();

        Carbon::setTestNow(Carbon::parse('2026-09-07 18:00'));   // week 37
        $this->finishedMatch([$a], []);
        $this->finishedMatch([$a], []);                          // same week: no change
        self::assertSame(1, PlayerStreak::query()->sole()->current);

        Carbon::setTestNow(Carbon::parse('2026-09-14 18:00'));   // week 38
        $this->finishedMatch([$a], []);
        self::assertSame(2, PlayerStreak::query()->sole()->current);
        self::assertSame(12, BonusXpEntry::totalFor($a->id));

        Carbon::setTestNow(Carbon::parse('2026-09-28 18:00'));   // skipped week 39
        $this->finishedMatch([$a], []);
        self::assertSame(1, PlayerStreak::query()->sole()->current);
        self::assertSame(2, PlayerStreak::query()->sole()->best);
        Carbon::setTestNow();
    }

    public function test_badges_are_persisted_once_with_the_match_that_unlocked_them(): void
    {
        BadgeDefinition::query()->create(['key' => 'week1', 'name' => 'Turned up', 'metric' => 'play_streak_weeks', 'threshold' => 1, 'bonus_xp' => 5, 'tier' => 'bronze']);
        $a = $this->player();

        $match = $this->finishedMatch([$a], []);
        $this->finishedMatch([$a], []);

        $badge = PlayerBadge::query()->where('user_id', $a->id)->where('badge_key', 'week1')->sole();
        self::assertSame((int) $match->id, (int) $badge->match_id);
        self::assertNull($badge->celebrated_at, 'waiting for the celebration');
        self::assertSame(5, BonusXpEntry::totalFor($a->id));
    }

    public function test_badges_a_player_already_had_are_recorded_without_a_celebration(): void
    {
        BadgeDefinition::query()->create(['key' => 'regular', 'name' => 'Regular', 'metric' => 'play_streak_weeks', 'threshold' => 1]);
        $a = $this->player();
        // Played before rewards launched: already past the threshold, never baselined.
        PlayerStreak::query()->create(['user_id' => $a->id, 'kind' => 'play_week', 'current' => 1, 'best' => 1, 'last_period' => '2020-W01']);

        $this->finishedMatch([$a], []);

        $old = PlayerBadge::query()->where('user_id', $a->id)->where('badge_key', 'regular')->sole();
        self::assertNotNull($old->celebrated_at, 'a baseline badge is never announced');
        self::assertNull($old->match_id);
    }

    public function test_every_participant_is_notified_once_with_a_deep_link(): void
    {
        $this->rule($this->program(), RewardTypes::HARAAN_COUPON, ['discount' => 25]);
        [$a, $b] = [$this->player(), $this->player()];
        $match = $this->finishedMatch([$a], [$b]);

        foreach ([$a, $b] as $user) {
            $n = Notification::query()->where('audience_type', 'user')->where('audience_value', (string) $user->id)->sole();
            self::assertSame('rewards', $n->source);
            self::assertSame('haraan://rewards/match/'.$match->id, $n->deep_link);
            self::assertStringContainsString('unlocks when the result is confirmed', $n->body);
        }

        $this->settle($match, 'medium');
        self::assertSame(2, Notification::query()->where('audience_value', (string) $a->id)->count(), 'one more when it unlocks');
    }

    public function test_notifications_can_be_switched_off(): void
    {
        $this->rules(['rewards.notify_match' => false, 'rewards.notify_badges' => false, 'rewards.notify_streak' => false]);
        $this->rule($this->program(), RewardTypes::BONUS_XP, ['amount' => 5]);
        $this->finishedMatch([$this->player()], []);

        self::assertSame(0, Notification::query()->count());
    }

    public function test_no_reward_service_writes_competitive_xp(): void
    {
        foreach (glob(app_path('Services/Rewards/*.php')) as $file) {
            $src = (string) file_get_contents($file);
            $name = basename($file);
            self::assertDoesNotMatchRegularExpression('/MatchXpLedger::(create|query\(\)->[^;]*->(insert|update|delete|create))/', $src, "{$name} writes match_xp_ledger");
            self::assertDoesNotMatchRegularExpression("/table\('match_xp_ledger'\)[^;]*->(insert|update|delete)/", $src, "{$name} writes match_xp_ledger");
            self::assertDoesNotMatchRegularExpression("/'(ranked_xp|casual_xp)'\s*=>/", $src, "{$name} writes competitive XP");
            self::assertStringNotContainsString('PlayerXpLedgerService', $src, $name);
        }
    }
}
