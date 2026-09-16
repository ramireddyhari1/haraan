<?php

declare(strict_types=1);

namespace Tests\Feature\Membership;

use App\Jobs\ReviewMatchClip;
use App\Models\Ad;
use App\Models\LiveMatch;
use App\Models\MatchDevice;
use App\Models\MemberPlanEntitlement;
use App\Models\Tournament;
use App\Models\User;
use App\Services\Membership\MemberEntitlements;
use App\Support\Membership\MemberFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Every entitlement in the matrix, exercised at the real endpoint that enforces it. These are
 * the tests that fail if a gate is removed, bypassed, or moved somewhere it no longer runs.
 */
class MemberFeatureGatesTest extends TestCase
{
    use MembershipFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureRazorpay();
    }

    // ── ads.hidden ──────────────────────────────────────────────────────────

    public function test_ads_are_served_to_guests_and_free_members_but_not_to_pro(): void
    {
        Ad::create([
            'sponsor' => 'Gully Gear', 'title' => 'Bats 20% off', 'cta_text' => 'Shop',
            'cta_url' => 'https://example.com', 'placement' => 'match_live', 'is_active' => true, 'sort_order' => 0,
        ]);

        $this->getJson('/api/ads?placement=match_live')->assertOk()->assertJsonCount(1, 'data');

        $free = $this->member();
        $this->asMember($free)->getJson('/api/ads?placement=match_live')->assertOk()->assertJsonCount(1, 'data');

        $pro = $this->member();
        $this->paidSubscription($pro, 'pro');
        $this->asMember($pro)->getJson('/api/ads?placement=match_live')->assertOk()->assertJsonCount(0, 'data');
    }

    // ── ai.career_read + profile.member_badge ───────────────────────────────

    public function test_ai_career_read_and_badge_follow_the_profile_owners_plan(): void
    {
        $player = $this->player();
        $player->forceFill(['player_id' => 'HRNTEST001'])->save();
        DB::table('player_career_analysis')->insert([
            'player_id' => 'HRNTEST001', 'fingerprint' => str_repeat('a', 32),
            'lines' => json_encode(['Scores quickly through the off side.']),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $cricket = fn ($response) => collect($response->json('career_book.sports'))->firstWhere('key', 'cricket');

        $free = $this->asMember($player)->getJson('/api/players/me')->assertOk();
        $this->assertNull($cricket($free)['analysis'], 'a written read already in cache is still withheld on Free');
        $this->assertTrue($cricket($free)['analysis_locked']);
        $this->assertNull($free->json('member_badge'));

        $this->paidSubscription($player, 'pro');
        MemberEntitlements::flush();

        $pro = $this->asMember($player)->getJson('/api/players/me')->assertOk();
        $this->assertSame(['Scores quickly through the off side.'], $cricket($pro)['analysis']['lines']);
        $this->assertFalse($cricket($pro)['analysis_locked']);
        $this->assertSame('pro', $pro->json('member_badge'));

        // Another viewer sees the owner's entitlement, not their own.
        $this->getJson('/api/players/HRNTEST001')->assertOk()->assertJsonPath('member_badge', 'pro');
    }

    // ── ai.delivery_review ──────────────────────────────────────────────────

    private function scoredMatch(User $owner): LiveMatch
    {
        $match = LiveMatch::create(['sport' => 'cricket', 'status' => 'live', 'home' => 'KDK', 'away' => 'NLW', 'title' => 'KDK vs NLW']);
        $match->forceFill(['user_id' => $owner->id])->save();

        return $match;
    }

    private function clip(LiveMatch $match): int
    {
        return (int) DB::table('match_device_clips')->insertGetId([
            'match_id' => $match->id, 'device_id' => 1, 'role' => MatchDevice::ROLE_LBW,
            'path' => 'match-clips/' . $match->id . '/' . uniqid() . '.mp4',
            'bytes' => 1_000_000, 'duration_ms' => 8000, 'over_ball' => '9.5',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function enableReviews(): void
    {
        // DeliveryReview reports itself configured from these; nothing is ever called (jobs are faked).
        config(['services.gemini.key' => 'test-key', 'services.vertex.key_path' => '', 'services.vertex.project' => 'test']);
    }

    public function test_delivery_reviews_are_metered_per_month_on_free(): void
    {
        Queue::fake();
        $this->enableReviews();
        $scorer = $this->player();
        $match = $this->scoredMatch($scorer);

        $first = $this->asMember($scorer)->postJson("/api/matches/{$match->id}/clips/{$this->clip($match)}/review");
        if ($first->status() === 503) {
            $this->markTestSkipped('Delivery review is not configurable in this environment.');
        }
        $first->assertStatus(202);
        $this->asMember($scorer)->postJson("/api/matches/{$match->id}/clips/{$this->clip($match)}/review")->assertStatus(202);

        $third = $this->clip($match);
        $this->asMember($scorer)->postJson("/api/matches/{$match->id}/clips/{$third}/review")
            ->assertForbidden()
            ->assertJsonPath('code', 'limit_reached')
            ->assertJsonPath('feature', MemberFeature::AI_DELIVERY_REVIEW)
            ->assertJsonPath('limit', 2)
            ->assertJsonPath('used', 2)
            ->assertJsonPath('upgrade_plan', 'pro');

        Queue::assertPushed(ReviewMatchClip::class, 2);
        $this->assertNull(DB::table('match_device_clips')->find($third)->review_status, 'a refused review never reaches the queue');
    }

    public function test_a_cached_review_is_free_and_a_failed_review_is_refunded(): void
    {
        Queue::fake();
        $this->enableReviews();
        $scorer = $this->player();
        $match = $this->scoredMatch($scorer);

        $clipId = $this->clip($match);
        DB::table('match_device_clips')->where('id', $clipId)->update(['analysis' => json_encode(['visibility' => 'good']), 'review_status' => 'completed']);
        $cached = $this->asMember($scorer)->postJson("/api/matches/{$match->id}/clips/{$clipId}/review");
        if ($cached->status() === 503) {
            $this->markTestSkipped('Delivery review is not configurable in this environment.');
        }
        $cached->assertOk();
        $this->assertSame(0, app(MemberEntitlements::class)->usage($scorer, MemberFeature::AI_DELIVERY_REVIEW));

        $failing = $this->clip($match);
        $this->asMember($scorer)->postJson("/api/matches/{$match->id}/clips/{$failing}/review")->assertStatus(202);
        $this->assertSame(1, app(MemberEntitlements::class)->usage($scorer, MemberFeature::AI_DELIVERY_REVIEW));

        // The queue gives up on the job.
        (new ReviewMatchClip($failing, 'x.mp4', 'lbw'))->failed(new \RuntimeException('vertex down'));
        (new ReviewMatchClip($failing, 'x.mp4', 'lbw'))->failed(new \RuntimeException('reported twice'));

        $this->assertSame(0, app(MemberEntitlements::class)->usage($scorer, MemberFeature::AI_DELIVERY_REVIEW), 'refunded exactly once');
    }

    public function test_hero_delivery_reviews_are_unlimited(): void
    {
        Queue::fake();
        $this->enableReviews();
        $scorer = $this->player();
        $this->paidSubscription($scorer, 'hero');
        $match = $this->scoredMatch($scorer);

        for ($i = 0; $i < 4; $i++) {
            $r = $this->asMember($scorer)->postJson("/api/matches/{$match->id}/clips/{$this->clip($match)}/review");
            if ($r->status() === 503) {
                $this->markTestSkipped('Delivery review is not configurable in this environment.');
            }
            $r->assertStatus(202);
        }
    }

    // ── matches.camera_angles ───────────────────────────────────────────────

    public function test_free_scorers_pair_one_camera_angle_and_pro_pairs_two(): void
    {
        $scorer = $this->player();
        $match = $this->scoredMatch($scorer);

        $this->asMember($scorer)->postJson("/api/matches/{$match->id}/devices", ['role' => MatchDevice::ROLE_LBW])->assertOk();
        // Re-pairing the same angle is not a second angle.
        $this->asMember($scorer)->postJson("/api/matches/{$match->id}/devices", ['role' => MatchDevice::ROLE_LBW])->assertOk();
        $this->asMember($scorer)->postJson("/api/matches/{$match->id}/devices", ['role' => MatchDevice::ROLE_BOWLER])
            ->assertForbidden()
            ->assertJsonPath('code', 'limit_reached')
            ->assertJsonPath('feature', MemberFeature::MATCHES_CAMERA_ANGLES);

        $this->paidSubscription($scorer, 'pro');
        MemberEntitlements::flush();
        $this->asMember($scorer)->postJson("/api/matches/{$match->id}/devices", ['role' => MatchDevice::ROLE_BOWLER])->assertOk();
    }

    // ── tournaments.active_hosted ───────────────────────────────────────────

    /** @return array<string, mixed> */
    private function tournamentForm(): array
    {
        return [
            'sport' => 'cricket', 'name' => 'Kadapa Premier League', 'category' => 'open', 'gender' => 'men',
            'age_group' => 'open', 'city' => 'Kadapa', 'venue' => 'YSR Stadium',
            'start_date' => Carbon::today()->addDays(3)->toDateString(),
            'end_date' => Carbon::today()->addDays(10)->toDateString(),
            'match_format' => 't20', 'players_per_side' => 11, 'ball_type' => 'leather', 'surface' => 'turf',
            'structure' => 'league_knockout', 'teams_count' => 16, 'entry_fee' => 5000,
            'prize_pool' => '₹1,00,000 + trophy', 'organizer_name' => 'Reddy', 'organizer_phone' => '+91 98765 43210',
        ];
    }

    private function existingTournament(User $host, Carbon $endsOn): void
    {
        Tournament::create([
            'user_id' => $host->id, 'category' => 'open', 'city' => 'Kadapa', 'match_format' => 't20',
            'overs_per_innings' => 20, 'ball_type' => 'tennis', 'surface' => 'turf', 'structure' => 'knockout',
            'players_per_side' => 11, 'organizer_name' => 'Reddy', 'organizer_phone' => '9876543210',
            'name' => 'Cup ' . uniqid(), 'start_date' => $endsOn->copy()->subDays(3), 'end_date' => $endsOn,
        ]);
    }

    public function test_free_hosts_run_one_tournament_at_a_time_and_finished_ones_dont_count(): void
    {
        $host = $this->player();
        $this->existingTournament($host, Carbon::today()->subDays(2));
        $this->existingTournament($host, Carbon::today()->subDays(40));

        $this->asMember($host)->post('/api/tournaments', $this->tournamentForm(), ['Accept' => 'application/json'])->assertCreated();

        $this->asMember($host)->post('/api/tournaments', $this->tournamentForm(), ['Accept' => 'application/json'])
            ->assertForbidden()
            ->assertJsonPath('code', 'limit_reached')
            ->assertJsonPath('upgrade_plan', 'pro');
    }

    public function test_an_invalid_tournament_form_gets_field_errors_before_the_plan_limit(): void
    {
        $host = $this->player();
        $this->existingTournament($host, Carbon::today()->addDays(5));

        $this->asMember($host)->post('/api/tournaments', ['sport' => 'cricket'], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_admin_edits_to_a_plan_limit_apply_immediately(): void
    {
        $host = $this->player();
        $this->existingTournament($host, Carbon::today()->addDays(5));

        MemberPlanEntitlement::query()
            ->where('plan_id', $this->plan('free')->id)
            ->where('feature_key', MemberFeature::TOURNAMENTS_ACTIVE_HOSTED)
            ->update(['limit_value' => 2]);

        $this->asMember($host)->post('/api/tournaments', $this->tournamentForm(), ['Accept' => 'application/json'])->assertCreated();
    }

    // ── support.priority ────────────────────────────────────────────────────

    public function test_support_threads_are_marked_priority_for_hero_only(): void
    {
        $pro = $this->member();
        $this->paidSubscription($pro, 'pro');
        $this->asMember($pro)->getJson('/api/support/thread')->assertOk()->assertJsonPath('thread.priority', false);

        $hero = $this->member();
        $this->paidSubscription($hero, 'hero');
        $this->asMember($hero)->getJson('/api/support/thread')->assertOk()->assertJsonPath('thread.priority', true);
    }

    // ── middleware ──────────────────────────────────────────────────────────

    public function test_route_middleware_gate_renders_the_shared_denial(): void
    {
        \Illuminate\Support\Facades\Route::middleware(['auth.jwt', 'member.entitled:' . MemberFeature::AI_CAREER_READ])
            ->get('/api/_test/career-read', fn () => response()->json(['ok' => true]));

        $free = $this->member();
        $this->asMember($free)->getJson('/api/_test/career-read')
            ->assertForbidden()
            ->assertJson(['code' => 'upgrade_required', 'feature' => 'ai.career_read', 'plan' => 'free', 'upgrade_plan' => 'pro']);

        $pro = $this->member();
        $this->paidSubscription($pro, 'pro');
        $this->asMember($pro)->getJson('/api/_test/career-read')->assertOk();
    }
}
