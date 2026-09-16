<?php

declare(strict_types=1);

namespace Tests\Feature\Membership;

use App\Filament\Resources\MemberSubscriptions\Pages\ViewMemberSubscription;
use App\Models\LiveMatch;
use App\Models\MemberEntitlementOverride;
use App\Models\MemberPlanEntitlement;
use App\Models\MemberSportSelection;
use App\Models\MemberSubscription;
use App\Models\User;
use App\Services\CricketInsights;
use App\Services\Insights\SportInsights;
use App\Services\InningsHeadline;
use App\Services\Membership\MemberEntitlements;
use App\Services\Membership\SportInsightsAccess;
use App\Services\PlayerInningsIQ;
use App\Support\Membership\InsightSports;
use App\Support\Membership\MemberFeature;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Sport-specific advanced insights: Free none, Pro three sports the member chooses, Hero every
 * sport — enforced at every insights endpoint before anything expensive is built.
 */
class MemberInsightSportsTest extends TestCase
{
    use MembershipFixtures;
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureRazorpay();
        $this->owner = $this->member();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function match(string $sport, array $extra = []): LiveMatch
    {
        $match = LiveMatch::create(array_merge([
            'title' => 'Insights gate', 'home' => 'HHH', 'away' => 'KKJ',
            'home_score' => 0, 'away_score' => 0, 'status' => 'Live', 'sport' => $sport,
        ], $extra));
        $match->forceFill(['user_id' => $this->owner->id])->save();

        return $match;
    }

    /** Any attempt to build an insight fails the request loudly. */
    private function forbidInsightBuilders(): void
    {
        foreach ([CricketInsights::class, SportInsights::class, PlayerInningsIQ::class, InningsHeadline::class] as $class) {
            $this->app->bind($class, fn () => throw new \RuntimeException("{$class} was built before authorization"));
        }
    }

    private function onPlan(User $user, string $plan): MemberSubscription
    {
        MemberEntitlements::flush();

        return $this->paidSubscription($user, $plan);
    }

    private function selectAs(User $user, array $sports)
    {
        return $this->asMember($user)->putJson('/api/membership/insight-sports', ['sports' => $sports]);
    }

    // ── Catalogue ───────────────────────────────────────────────────────────

    public function test_the_feature_is_seeded_on_every_plan(): void
    {
        $values = MemberPlanEntitlement::query()
            ->where('feature_key', MemberFeature::INSIGHTS_ADVANCED_SPORTS)
            ->get()
            ->mapWithKeys(fn (MemberPlanEntitlement $e) => [$e->plan->code => [$e->enabled, $e->limit_value]])
            ->all();

        ksort($values);
        $this->assertSame(['free' => [false, 0], 'hero' => [true, null], 'pro' => [true, 3]], $values);
        $this->assertCount(8, InsightSports::keys());
    }

    // ── The gate ────────────────────────────────────────────────────────────

    public function test_guests_and_free_members_are_refused_before_anything_is_built(): void
    {
        $this->forbidInsightBuilders();
        $football = $this->match('football');
        $cricket = $this->match('cricket');

        $this->getJson("/api/live-matches/{$football->id}/insights")
            ->assertForbidden()
            ->assertJson(['code' => 'upgrade_required', 'feature' => MemberFeature::INSIGHTS_ADVANCED_SPORTS, 'sport' => 'football', 'upgrade_plan' => 'pro']);

        $free = $this->member();
        foreach (["/api/live-matches/{$cricket->id}/insights", "/api/live-matches/{$football->id}/insights", "/api/matches/{$cricket->id}/iq"] as $url) {
            $this->asMember($free)->getJson($url)->assertForbidden()->assertJsonPath('code', 'upgrade_required');
        }
    }

    public function test_a_hidden_match_stays_a_404_rather_than_a_paywall(): void
    {
        $this->forbidInsightBuilders();
        $private = $this->match('football', ['is_private' => true]);

        $this->asMember($this->member())->getJson("/api/live-matches/{$private->id}/insights")->assertNotFound();
        $this->getJson("/api/matches/{$private->id}/iq")->assertNotFound();
    }

    public function test_pro_unlocks_exactly_the_three_chosen_sports(): void
    {
        $pro = $this->member();
        $this->onPlan($pro, 'pro');
        $football = $this->match('football');
        $basketball = $this->match('basketball');
        $cricket = $this->match('cricket');

        // Before choosing, nothing is unlocked.
        $this->asMember($pro)->getJson("/api/live-matches/{$football->id}/insights")
            ->assertForbidden()
            ->assertJson(['code' => 'selection_required', 'sport' => 'football', 'limit' => 3, 'used' => 0]);

        $this->selectAs($pro, ['cricket', 'football', 'kabaddi'])
            ->assertOk()
            ->assertJsonPath('data.mode', 'choose')
            ->assertJsonPath('data.selected', ['cricket', 'football', 'kabaddi'])
            ->assertJsonPath('data.slots_left', 0);

        $this->asMember($pro)->getJson("/api/live-matches/{$football->id}/insights")->assertOk();
        $this->asMember($pro)->getJson("/api/live-matches/{$cricket->id}/insights")->assertOk()->assertJsonStructure(['innings', 'analysis']);
        $this->asMember($pro)->getJson("/api/matches/{$cricket->id}/iq")->assertOk();

        $this->asMember($pro)->getJson("/api/live-matches/{$basketball->id}/insights")
            ->assertForbidden()
            ->assertJson(['code' => 'selection_required', 'sport' => 'basketball', 'upgrade_plan' => 'hero', 'used' => 3]);
    }

    public function test_hero_unlocks_every_sport_without_choosing(): void
    {
        $hero = $this->member();
        $this->onPlan($hero, 'hero');

        foreach (InsightSports::keys() as $sport) {
            $this->assertTrue(app(SportInsightsAccess::class)->allows($hero, $sport), $sport);
        }
        $this->asMember($hero)->getJson('/api/live-matches/' . $this->match('table_tennis')->id . '/insights')->assertOk();

        $this->selectAs($hero, ['cricket'])->assertStatus(422)->assertJsonPath('code', 'all_sports_included');
        $this->asMember($hero)->getJson('/api/membership/insight-sports')
            ->assertOk()
            ->assertJsonPath('data.mode', 'all')
            ->assertJsonPath('data.limit', null)
            ->assertJsonPath('data.sports.7.unlocked', true);
    }

    public function test_a_loosely_written_match_sport_still_matches_its_selection(): void
    {
        $pro = $this->member();
        $this->onPlan($pro, 'pro');
        $this->selectAs($pro, ['table_tennis'])->assertOk();

        $this->asMember($pro)->getJson('/api/live-matches/' . $this->match('Table Tennis')->id . '/insights')->assertOk();
        // Old cricket rows have no sport at all.
        $this->assertFalse(app(SportInsightsAccess::class)->allows($pro, ''));
    }

    // ── Saving a selection ──────────────────────────────────────────────────

    public function test_selection_validation(): void
    {
        $pro = $this->member();
        $this->onPlan($pro, 'pro');

        $this->selectAs($pro, ['cricket', 'football', 'kabaddi', 'tennis'])->assertStatus(422)->assertJsonPath('code', 'too_many_sports');
        $this->selectAs($pro, ['cricket', 'darts'])->assertStatus(422)->assertJsonPath('code', 'unknown_sport');
        $this->asMember($pro)->putJson('/api/membership/insight-sports', [])->assertStatus(422);
        $this->assertSame(0, MemberSportSelection::query()->count(), 'a refused save writes nothing');

        // Duplicates collapse to one choice.
        $this->selectAs($pro, ['cricket', 'cricket', 'football'])->assertOk()->assertJsonPath('data.selected', ['cricket', 'football'])->assertJsonPath('data.slots_left', 1);

        $this->flushHeaders()->putJson('/api/membership/insight-sports', ['sports' => ['cricket']])->assertUnauthorized();

        $free = $this->member();
        $this->selectAs($free, ['cricket'])->assertForbidden()->assertJsonPath('code', 'upgrade_required');
    }

    public function test_a_chosen_sport_is_held_for_the_cooldown_but_empty_slots_fill_any_time(): void
    {
        config(['membership.insight_sport_cooldown_days' => 7]);
        $pro = $this->member();
        $this->onPlan($pro, 'pro');

        $this->selectAs($pro, ['cricket', 'football'])->assertOk();

        Carbon::setTestNow(now()->addDay());
        $this->selectAs($pro, ['cricket', 'football', 'kabaddi'])->assertOk();

        $this->selectAs($pro, ['cricket', 'kabaddi', 'tennis'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'change_locked');
        $this->assertSame(['cricket', 'football', 'kabaddi'], MemberSportSelection::query()->orderBy('id')->pluck('sport')->all());

        $locked = collect($this->asMember($pro)->getJson('/api/membership/insight-sports')->json('data.sports'))->firstWhere('key', 'football');
        $this->assertNotNull($locked['locked_until']);

        Carbon::setTestNow(now()->addDays(7));
        $this->selectAs($pro, ['cricket', 'kabaddi', 'tennis'])->assertOk()->assertJsonPath('data.selected', ['cricket', 'kabaddi', 'tennis']);
    }

    public function test_cooldown_can_be_switched_off(): void
    {
        config(['membership.insight_sport_cooldown_days' => 0]);
        $pro = $this->member();
        $this->onPlan($pro, 'pro');

        $this->selectAs($pro, ['cricket'])->assertOk();
        $this->selectAs($pro, ['football'])->assertOk()->assertJsonPath('data.selected', ['football']);
    }

    // ── Plan changes ────────────────────────────────────────────────────────

    public function test_choices_survive_a_lapse_and_come_back_with_the_plan(): void
    {
        $pro = $this->member();
        $sub = $this->onPlan($pro, 'pro');
        $this->selectAs($pro, ['football'])->assertOk();
        $football = $this->match('football');

        $sub->forceFill(['status' => MemberSubscription::STATUS_CANCELLED, 'ended_at' => now()])->save();
        $this->asMember($pro)->getJson("/api/live-matches/{$football->id}/insights")->assertForbidden()->assertJsonPath('code', 'upgrade_required');
        $this->asMember($pro)->getJson('/api/membership/insight-sports')->assertJsonPath('data.mode', 'none');

        $this->onPlan($pro, 'pro');
        $this->asMember($pro)->getJson("/api/live-matches/{$football->id}/insights")->assertOk();
    }

    public function test_a_lowered_limit_keeps_the_longest_held_choices_and_lets_the_member_trim(): void
    {
        config(['membership.insight_sport_cooldown_days' => 7]);
        $pro = $this->member();
        $this->onPlan($pro, 'pro');
        $this->selectAs($pro, ['cricket'])->assertOk();
        Carbon::setTestNow(now()->addMinute());
        $this->selectAs($pro, ['cricket', 'football', 'kabaddi'])->assertOk();

        MemberPlanEntitlement::query()
            ->where('plan_id', $this->plan('pro')->id)
            ->where('feature_key', MemberFeature::INSIGHTS_ADVANCED_SPORTS)
            ->update(['limit_value' => 2]);
        MemberEntitlements::flush();

        $access = app(SportInsightsAccess::class);
        $this->assertTrue($access->allows($pro, 'cricket'));
        $this->assertTrue($access->allows($pro, 'football'));
        $this->assertFalse($access->allows($pro, 'kabaddi'));
        $this->asMember($pro)->getJson('/api/membership/insight-sports')->assertJsonPath('data.over_limit', true);

        // Over the limit, trimming isn't held back by the cooldown.
        $this->selectAs($pro, ['cricket', 'kabaddi'])->assertOk()->assertJsonPath('data.over_limit', false);
    }

    public function test_an_override_can_open_every_sport_for_one_member(): void
    {
        $free = $this->member();
        MemberEntitlementOverride::create([
            'user_id' => $free->id, 'feature_key' => MemberFeature::INSIGHTS_ADVANCED_SPORTS,
            'enabled' => true, 'limit_value' => null, 'reason' => 'Coach access',
        ]);

        $this->asMember($free)->getJson('/api/live-matches/' . $this->match('volleyball')->id . '/insights')->assertOk();
    }

    // ── Surfaces ────────────────────────────────────────────────────────────

    public function test_membership_state_carries_the_sport_selection(): void
    {
        $pro = $this->member();
        $this->onPlan($pro, 'pro');
        $this->selectAs($pro, ['kabaddi'])->assertOk();

        $this->asMember($pro)->getJson('/api/membership')
            ->assertOk()
            ->assertJsonPath('data.insight_sports.mode', 'choose')
            ->assertJsonPath('data.insight_sports.limit', 3)
            ->assertJsonPath('data.insight_sports.selected', ['kabaddi'])
            ->assertJsonFragment(['key' => MemberFeature::INSIGHTS_ADVANCED_SPORTS, 'limit' => 3]);
    }

    public function test_the_web_insights_tab_is_gated_the_same_way(): void
    {
        $match = $this->match('football');
        $url = "/gamehub/actionboard/match/{$match->id}?tab=insights";

        $free = $this->member();
        $this->app->bind(SportInsights::class, fn () => throw new \RuntimeException('built for a locked viewer'));
        $this->actingAs($free)->get($url)->assertOk()->assertSee('Advanced insights')->assertSee('part of Pro');

        $this->app->offsetUnset(SportInsights::class);
        $hero = $this->member();
        $this->onPlan($hero, 'hero');
        $this->actingAs($hero)->get($url)->assertOk()->assertDontSee('part of Pro');
    }

    public function test_control_shows_what_a_members_insights_cover(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin-ins@haraan.test', 'password' => bcrypt('x'), 'role' => 'ADMIN', 'status' => 'active']);
        $pro = $this->member();
        $sub = $this->onPlan($pro, 'pro');
        $this->selectAs($pro, ['cricket', 'kabaddi'])->assertOk();

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('control'));
        Livewire::test(ViewMemberSubscription::class, ['record' => $sub->getRouteKey()])
            ->assertOk()
            ->assertSee('Cricket, Kabaddi');
    }
}
