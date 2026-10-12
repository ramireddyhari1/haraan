<?php

declare(strict_types=1);

namespace Tests\Feature\Membership;

use App\Models\AppSetting;
use App\Models\LiveMatch;
use App\Models\MatchDevice;
use App\Models\User;
use App\Services\CameraAccess;
use App\Services\Membership\MemberEntitlements;
use App\Support\PlatformRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Second-screen cameras: a plan that includes them (Pro, Hero), or a booked venue slot —
 * a 6–7 PM booking works from 6:00 to 7:00 that evening and not outside it.
 */
class CameraAccessTest extends TestCase
{
    use MembershipFixtures;
    use RefreshDatabase;

    private const ZONE = 'Asia/Kolkata';

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function at(string $time): Carbon
    {
        return Carbon::parse('2026-10-12 ' . $time, self::ZONE);
    }

    private function book(User $user, string $start, string $end, array $extra = []): void
    {
        DB::table('bookings')->insert(array_merge([
            'user_id' => $user->id,
            'booking_type' => 'venue',
            'quantity' => 1,
            'total_amount' => 800,
            'status' => 'CONFIRMED',
            'payment_status' => 'paid',
            'channel' => 'online',
            'slot_date' => '2026-10-12 00:00:00',
            'start_time' => $start,
            'end_time' => $end,
            'created_at' => now(),
            'updated_at' => now(),
        ], $extra));
    }

    private function access(User $owner, string $time): array
    {
        MemberEntitlements::flush();

        return app(CameraAccess::class)->forOwner($owner, $this->at($time));
    }

    private function matchFor(User $owner): LiveMatch
    {
        $id = DB::table('live_matches')->insertGetId([
            'title' => 'A vs B', 'home' => 'A', 'away' => 'B', 'status' => 'Live', 'sport' => 'cricket',
            'user_id' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return LiveMatch::findOrFail($id);
    }

    public function test_a_free_player_has_no_cameras_without_a_booking(): void
    {
        $access = $this->access($this->member(), '18:30');

        $this->assertFalse($access['allowed']);
        $this->assertSame(0, $access['angles']);
        $this->assertNull($access['opensAt']);
    }

    public function test_a_booked_slot_unlocks_exactly_its_own_hour(): void
    {
        $player = $this->member();
        $this->book($player, '18:00', '19:00');

        $before = $this->access($player, '17:59');
        $this->assertFalse($before['allowed']);
        $this->assertSame($this->at('18:00')->utc()->toIso8601String(), $before['opensAt'], 'says when it opens');

        $during = $this->access($player, '18:00');
        $this->assertTrue($during['allowed']);
        $this->assertSame(CameraAccess::VIA_BOOKING, $during['via']);
        $this->assertSame(1, $during['angles']);
        $this->assertSame($this->at('19:00')->utc()->toIso8601String(), $during['until']);

        $this->assertTrue($this->access($player, '18:59')['allowed']);
        $this->assertFalse($this->access($player, '19:00')['allowed'], 'shut at the end of the slot');
    }

    public function test_back_to_back_slots_run_as_one_stretch(): void
    {
        $player = $this->member();
        $this->book($player, '18:00', '19:00');
        $this->book($player, '19:00', '20:00');

        $this->assertSame($this->at('20:00')->utc()->toIso8601String(), $this->access($player, '18:30')['until']);
    }

    public function test_cancelled_held_and_desk_walk_in_bookings_unlock_nothing(): void
    {
        $player = $this->member();
        $this->book($player, '18:00', '19:00', ['status' => 'CANCELLED']);
        $this->book($player, '18:00', '19:00', ['status' => 'PENDING', 'payment_status' => 'unpaid']);
        // A walk-in's user_id is the venue owner who typed it in, never a player.
        $this->book($player, '18:00', '19:00', ['channel' => 'offline']);

        $this->assertFalse($this->access($player, '18:30')['allowed']);
    }

    public function test_status_case_does_not_matter(): void
    {
        $player = $this->member();
        $this->book($player, '18:00', '19:00', ['status' => 'confirmed']);

        $this->assertTrue($this->access($player, '18:30')['allowed']);
    }

    public function test_a_slot_past_midnight_still_counts_after_midnight(): void
    {
        $player = $this->member();
        $this->book($player, '23:00', '01:00', ['slot_date' => '2026-10-11 00:00:00']);

        $this->assertTrue($this->access($player, '00:30')['allowed']);
        $this->assertFalse($this->access($player, '01:00')['allowed']);
    }

    public function test_control_sets_grace_minutes_and_booked_camera_count(): void
    {
        AppSetting::set(PlatformRules::storageKey('creation.camera_booking_grace_minutes'), '10');
        AppSetting::set(PlatformRules::storageKey('creation.camera_booking_angles'), '2');
        $player = $this->member();
        $this->book($player, '18:00', '19:00');

        $this->assertTrue($this->access($player, '17:51')['allowed']);
        $this->assertSame(2, $this->access($player, '18:30')['angles']);
        $this->assertTrue($this->access($player, '19:09')['allowed']);
        $this->assertFalse($this->access($player, '19:10')['allowed']);
    }

    public function test_pro_and_hero_have_cameras_any_time(): void
    {
        foreach (['pro', 'hero'] as $code) {
            $member = $this->member();
            $this->paidSubscription($member, $code);
            $access = $this->access($member, '03:00');
            $this->assertTrue($access['allowed'], $code);
            $this->assertSame(CameraAccess::VIA_PLAN, $access['via']);
            $this->assertSame(2, $access['angles']);
        }
    }

    public function test_switching_the_booking_rule_off_leaves_plans_only(): void
    {
        AppSetting::set(PlatformRules::storageKey('creation.camera_booking_gate'), '0');
        $player = $this->member();
        $this->book($player, '18:00', '19:00');

        $this->assertFalse($this->access($player, '18:30')['allowed']);
    }

    public function test_free_scorer_adds_a_camera_only_inside_the_booked_slot(): void
    {
        $scorer = $this->player();
        $match = $this->matchFor($scorer);
        $this->book($scorer, '18:00', '19:00');

        Carbon::setTestNow($this->at('17:30'));
        $this->asMember($scorer)->postJson("/api/matches/{$match->id}/devices", ['role' => MatchDevice::ROLE_LBW])
            ->assertForbidden()
            ->assertJsonPath('code', 'camera_locked')
            ->assertJsonPath('error', 'Cameras open at 6:00 PM, when your slot starts.');

        Carbon::setTestNow($this->at('18:10'));
        MemberEntitlements::flush();
        $pair = $this->asMember($scorer)->postJson("/api/matches/{$match->id}/devices", ['role' => MatchDevice::ROLE_LBW])->assertOk();
        // The slot includes one camera: a second angle is the plan's job.
        $this->asMember($scorer)->postJson("/api/matches/{$match->id}/devices", ['role' => MatchDevice::ROLE_BOWLER])
            ->assertForbidden()
            ->assertJsonPath('code', 'limit_reached');

        $claim = $this->postJson('/api/match-devices/claim', ['token' => $pair->json('data.token')])->assertOk();
        $claim->assertJsonPath('data.cameraAccess.allowed', true);
        $claim->assertJsonPath('data.cameraAccess.via', 'booking');

        Carbon::setTestNow($this->at('19:05'));
        $this->postJson('/api/match-devices/heartbeat', ['sessionToken' => $claim->json('data.sessionToken')])
            ->assertOk()
            ->assertJsonPath('data.cameraAccess.allowed', false);
    }

    public function test_a_code_from_the_slot_cannot_be_claimed_after_it(): void
    {
        $scorer = $this->player();
        $match = $this->matchFor($scorer);
        $this->book($scorer, '18:00', '19:00');

        Carbon::setTestNow($this->at('18:58'));
        $pair = $this->asMember($scorer)->postJson("/api/matches/{$match->id}/devices", ['role' => MatchDevice::ROLE_LBW])->assertOk();

        Carbon::setTestNow($this->at('19:01'));
        $this->postJson('/api/match-devices/claim', ['token' => $pair->json('data.token')])
            ->assertForbidden()
            ->assertJsonPath('code', 'camera_locked');
    }

    public function test_the_camera_is_told_which_ball_the_match_uses(): void
    {
        $scorer = $this->player();
        $this->paidSubscription($scorer, 'pro');
        $match = $this->matchFor($scorer);
        // Older matches carry it inside the format; new ones at the top as well.
        $match->forceFill(['sport_state' => ['format' => ['kind' => 'cricket', 'overs' => 6, 'ball' => 'tennis']]])->save();
        MemberEntitlements::flush();

        $pair = $this->asMember($scorer)->postJson("/api/matches/{$match->id}/devices", ['role' => MatchDevice::ROLE_LBW])->assertOk();
        $this->postJson('/api/match-devices/claim', ['token' => $pair->json('data.token')])
            ->assertOk()
            ->assertJsonPath('data.ballType', 'tennis');
    }
}
