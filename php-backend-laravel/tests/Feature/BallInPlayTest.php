<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LiveMatch;
use App\Models\User;
use App\Support\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * BALL on the scorer's keypad: viewers see "ball in play" until the result lands, and the
 * signal never becomes a ball in the log.
 */
final class BallInPlayTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: LiveMatch, 1: callable, 2: User} */
    private function liveMatch(): array
    {
        $creator = User::create([
            'name' => 'Scorer', 'email' => 'scorer@haraan.test', 'password' => Hash::make('secret123'),
            'role' => 'user', 'status' => 'active', 'player_id' => 'HRNS1', 'is_guest' => false,
            'state' => 'Andhra Pradesh', 'district' => 'YSR Kadapa', 'primary_sport' => 'Cricket',
            'sport_attributes' => ['role' => 'Batter', 'batting' => 'Right', 'bowling' => 'Right-arm medium'],
        ]);
        $m = LiveMatch::create([
            'title' => 'A v B', 'home' => 'ANT', 'away' => 'BEE', 'home_full' => 'Ants', 'away_full' => 'Bees',
            'home_score' => 0, 'away_score' => 0, 'status' => 'Scheduled', 'sport' => 'cricket',
            'competition' => '5 overs', 'user_id' => $creator->id,
            'home_squad' => [['id' => null, 'name' => 'Asha'], ['id' => null, 'name' => 'Arun']],
            'away_squad' => [['id' => null, 'name' => 'Bala'], ['id' => null, 'name' => 'Bhanu']],
        ]);
        $token = JwtService::issueForUser($creator, (string) (config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me')));
        $act = fn (array $body) => $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/matches/{$m->id}/score-action", $body);

        return [$m, $act, $creator];
    }

    private function inPlay(LiveMatch $m): bool
    {
        $json = $this->getJson("/api/live-matches/{$m->id}")->assertOk()->json();
        self::assertArrayHasKey('ballInPlay', $json);

        return (bool) $json['ballInPlay'];
    }

    public function test_ball_shows_in_play_until_the_result_and_is_never_logged(): void
    {
        [$m, $act] = $this->liveMatch();
        $act(['type' => 'start', 'innings' => 1, 'batting_team' => 1, 'striker_id' => 'Asha', 'non_striker_id' => 'Arun', 'bowler_id' => 'Bala'])->assertSuccessful();
        $logged = DB::table('match_actions')->where('match_id', $m->id)->count();

        $act(['type' => 'delivery'])->assertOk()->assertJsonPath('ballInPlay', true);
        self::assertTrue($this->inPlay($m));
        self::assertSame($logged, DB::table('match_actions')->where('match_id', $m->id)->count(), 'BALL is not a ball');

        $act(['type' => 'runs', 'value' => 4])->assertSuccessful();
        self::assertFalse($this->inPlay($m), 'the result ends the ball in play');
        self::assertSame('0.1', (string) $m->fresh()->overs, 'one ball bowled, not two');
    }

    public function test_undo_after_ball_removes_the_last_real_delivery_not_the_signal(): void
    {
        [$m, $act] = $this->liveMatch();
        $act(['type' => 'start', 'innings' => 1, 'batting_team' => 1, 'striker_id' => 'Asha', 'non_striker_id' => 'Arun', 'bowler_id' => 'Bala'])->assertSuccessful();
        $act(['type' => 'runs', 'value' => 2])->assertSuccessful();
        $act(['type' => 'delivery'])->assertOk();
        $act(['type' => 'undo'])->assertSuccessful();

        self::assertSame('0.0', (string) $m->fresh()->overs, 'the 2 was undone');
        self::assertFalse($this->inPlay($m));
    }

    public function test_the_web_pulse_tracks_the_ball_and_the_page_shows_it(): void
    {
        [$m, $act] = $this->liveMatch();
        $act(['type' => 'start', 'innings' => 1, 'batting_team' => 1, 'striker_id' => 'Asha', 'non_striker_id' => 'Arun', 'bowler_id' => 'Bala'])->assertSuccessful();
        $v0 = $this->getJson("/api/live-matches/{$m->id}/pulse")->assertOk()->assertJsonPath('ballInPlay', false)->json('v');

        $act(['type' => 'delivery'])->assertOk();
        $pulse = $this->getJson("/api/live-matches/{$m->id}/pulse")->assertOk()->json();
        self::assertTrue($pulse['ballInPlay']);
        self::assertSame($v0, $pulse['v'], 'BALL alone must not reload the page');

        $this->get("/gamehub/actionboard/match/{$m->id}")->assertOk()
            ->assertSee('mdx-lastball is-bowling', false);

        $act(['type' => 'runs', 'value' => 1])->assertSuccessful();
        $v1 = $this->getJson("/api/live-matches/{$m->id}/pulse")->json('v');
        self::assertNotSame($v0, $v1, 'a ball moves the version');

        // A page loaded after that ball holds v1; the undo must move it off v1.
        $act(['type' => 'undo'])->assertSuccessful();
        self::assertNotSame($v1, $this->getJson("/api/live-matches/{$m->id}/pulse")->json('v'), 'an undo moves it too');
    }

    public function test_the_heartbeat_tells_the_camera_how_long_to_keep_sent_clips(): void
    {
        [$m, , $creator] = $this->liveMatch();
        $device = \App\Models\MatchDevice::create([
            'match_id' => $m->id, 'role' => \App\Models\MatchDevice::ROLE_LBW,
            'pair_token' => \App\Models\MatchDevice::freshToken(), 'token_expires_at' => now(),
            'status' => \App\Models\MatchDevice::STATUS_CONNECTED, 'created_by' => $creator->id,
        ]);
        $session = \App\Models\MatchDevice::freshSessionToken();
        $device->update(['session_token' => $session]);
        $beat = fn () => $this->postJson('/api/match-devices/heartbeat', ['sessionToken' => $session])->assertOk()->json('data.clipKeepHours');

        self::assertSame(24, $beat(), 'the default is a day');

        // The admin changes it in /control; the next heartbeat carries the new value.
        \App\Support\PlatformRules::save(['creation.camera_clip_keep_hours' => 6]);
        self::assertSame(6, $beat());

        $quality = fn () => $this->postJson('/api/match-devices/heartbeat', ['sessionToken' => $session])->json('data.videoQuality');
        self::assertSame('1080p60', $quality(), '60 fps by default');
        \App\Support\PlatformRules::save(['creation.camera_video_quality' => '720p60']);
        self::assertSame('720p60', $quality());
    }

    public function test_a_paired_camera_is_cued_per_ball_and_told_about_dead_balls(): void
    {
        [$m, $act, $creator] = $this->liveMatch();
        $device = \App\Models\MatchDevice::create([
            'match_id' => $m->id, 'role' => \App\Models\MatchDevice::ROLE_LBW,
            'pair_token' => \App\Models\MatchDevice::freshToken(), 'token_expires_at' => now(),
            'status' => \App\Models\MatchDevice::STATUS_CONNECTED, 'created_by' => $creator->id,
        ]);
        $session = \App\Models\MatchDevice::freshSessionToken();
        $device->update(['session_token' => $session]);
        $cue = fn () => $this->postJson('/api/match-devices/cue', ['sessionToken' => $session])->assertOk()->json('data');

        self::assertSame(['seq' => 0, 'inPlay' => false, 'cancelled' => false], $cue());

        $act(['type' => 'start', 'innings' => 1, 'batting_team' => 1, 'striker_id' => 'Asha', 'non_striker_id' => 'Arun', 'bowler_id' => 'Bala'])->assertSuccessful();
        $act(['type' => 'delivery'])->assertOk()->assertJsonPath('ballSeq', 1);
        self::assertSame(['seq' => 1, 'inPlay' => true, 'cancelled' => false], $cue());

        $act(['type' => 'runs', 'value' => 1])->assertSuccessful();
        self::assertSame(['seq' => 1, 'inPlay' => false, 'cancelled' => false], $cue(), 'a result keeps the clip');

        $act(['type' => 'delivery'])->assertOk()->assertJsonPath('ballSeq', 2);
        $act(['type' => 'delivery_cancel'])->assertOk();
        self::assertSame(['seq' => 2, 'inPlay' => false, 'cancelled' => true], $cue(), 'a dead ball discards it');

        // REVIEW reads the clips list: it says which ball is the latest, and each clip
        // says which ball it was armed by.
        DB::table('match_device_clips')->insert([
            'match_id' => $m->id, 'device_id' => $device->id, 'role' => $device->role,
            'path' => 'match-clips/x.mp4', 'bytes' => 10, 'duration_ms' => 4000, 'ball_seq' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $token = \App\Support\JwtService::issueForUser($creator, (string) (config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me')));
        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson("/api/matches/{$m->id}/clips")->assertOk()
            ->assertJsonPath('meta.lastBallSeq', 2)
            ->assertJsonPath('meta.lastBallCancelled', true)
            ->assertJsonPath('data.0.ballSeq', 1);

        // A revoked camera learns nothing.
        $device->update(['status' => \App\Models\MatchDevice::STATUS_REVOKED, 'session_token' => null]);
        $this->postJson('/api/match-devices/cue', ['sessionToken' => $session])->assertStatus(401);
    }

    public function test_dead_ball_cancels_and_a_non_live_match_refuses(): void
    {
        [$m, $act] = $this->liveMatch();
        $act(['type' => 'delivery'])->assertStatus(422);

        $act(['type' => 'start', 'innings' => 1, 'batting_team' => 1, 'striker_id' => 'Asha', 'non_striker_id' => 'Arun', 'bowler_id' => 'Bala'])->assertSuccessful();
        $act(['type' => 'delivery'])->assertOk();
        $act(['type' => 'delivery_cancel'])->assertOk()->assertJsonPath('ballInPlay', false);
        self::assertFalse($this->inPlay($m));
    }
}
