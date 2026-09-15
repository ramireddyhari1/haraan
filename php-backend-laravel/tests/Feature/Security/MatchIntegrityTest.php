<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\LiveMatch;
use App\Models\MatchEvent;
use App\Models\ReputationEvent;
use App\Models\User;
use App\Services\MatchEventRecorder;
use App\Support\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The defences added after the 2026-09-15 kabaddi incident, and the result-trust gates.
 */
final class MatchIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $scorer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scorer = $this->player('HRNSCR');
    }

    private function player(string $pid): User
    {
        return User::create([
            'name' => "P {$pid}", 'email' => strtolower($pid) . '@haraan.test', 'password' => Hash::make('secret123'),
            'role' => 'user', 'status' => 'active', 'player_id' => $pid, 'is_guest' => false,
            'state' => 'Andhra Pradesh', 'district' => 'YSR Kadapa', 'primary_sport' => 'Football',
            'sport_attributes' => ['position' => 'Midfielder', 'foot' => 'Right'],
        ]);
    }

    private function auth(User $u): array
    {
        return ['Authorization' => 'Bearer ' . JwtService::issueForUser($u, (string) (config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me')))];
    }

    private function kabaddi(): LiveMatch
    {
        return LiveMatch::create([
            'title' => 'KDW vs KNT', 'home' => 'KDW', 'away' => 'KNT', 'home_score' => 0, 'away_score' => 0,
            'status' => 'Live', 'sport' => 'kabaddi', 'user_id' => $this->scorer->id,
        ]);
    }

    private function raid(LiveMatch $m, string $side = 'home'): MatchEvent
    {
        return app(MatchEventRecorder::class)->record($m, ['kind' => 'point', 'side' => $side, 'detail' => 'raid', 'player_name' => 'Suresh'], $this->scorer);
    }

    public function test_undo_hides_the_event_instead_of_deleting_it_and_restore_puts_it_back(): void
    {
        $m = $this->kabaddi();
        $this->raid($m);
        $e = $this->raid($m);
        self::assertSame(2, $m->fresh()->home_score);

        $this->withHeaders($this->auth($this->scorer))
            ->postJson("/api/matches/{$m->id}/events/undo", ['sequence' => $e->sequence])
            ->assertOk()->assertJsonPath('home_score', 1);

        $row = MatchEvent::query()->withoutGlobalScope(MatchEvent::ACTIVE_SCOPE)->find($e->id);
        self::assertNotNull($row, 'the row survives');
        self::assertNotNull($row->undone_at);
        self::assertSame($this->scorer->id, (int) $row->undone_by);

        $this->withHeaders($this->auth($this->scorer))
            ->getJson("/api/matches/{$m->id}/events/undone")
            ->assertOk()->assertJsonPath('data.0.id', $e->id);

        $this->withHeaders($this->auth($this->scorer))
            ->postJson("/api/matches/{$m->id}/events/{$e->id}/restore")
            ->assertOk()->assertJsonPath('home_score', 2);
    }

    public function test_an_undo_delivered_twice_removes_one_event_not_two(): void
    {
        $m = $this->kabaddi();
        $this->raid($m);
        $this->raid($m);
        $this->raid($m);

        $h = $this->auth($this->scorer);
        $this->withHeaders($h)->postJson("/api/matches/{$m->id}/events/undo")->assertOk();
        $this->withHeaders($h)->postJson("/api/matches/{$m->id}/events/undo")
            ->assertStatus(409)->assertJsonPath('code', 'undo_debounced');
        self::assertSame(2, $m->fresh()->home_score);

        // Once the window passes, a deliberate second undo works.
        Carbon::setTestNow(now()->addSeconds(MatchEventRecorder::UNDO_DEBOUNCE_SECONDS + 1));
        $this->withHeaders($h)->postJson("/api/matches/{$m->id}/events/undo")->assertOk();
        Carbon::setTestNow();
        self::assertSame(1, $m->fresh()->home_score);
    }

    public function test_undo_by_sequence_is_idempotent(): void
    {
        $m = $this->kabaddi();
        $this->raid($m);
        $e = $this->raid($m);

        $h = $this->auth($this->scorer);
        $this->withHeaders($h)->postJson("/api/matches/{$m->id}/events/undo", ['sequence' => $e->sequence])->assertOk();
        $this->withHeaders($h)->postJson("/api/matches/{$m->id}/events/undo", ['sequence' => $e->sequence])->assertStatus(422);
        self::assertSame(1, $m->fresh()->home_score);
    }

    public function test_a_retried_tap_with_the_same_client_id_scores_once(): void
    {
        $m = $this->kabaddi();
        $h = $this->auth($this->scorer);
        $body = ['kind' => 'point', 'side' => 'home', 'detail' => 'raid', 'client_event_id' => 'tap-7f3a'];

        $this->withHeaders($h)->postJson("/api/matches/{$m->id}/events", $body)->assertStatus(201);
        $this->withHeaders($h)->postJson("/api/matches/{$m->id}/events", $body)->assertStatus(201);

        self::assertSame(1, $m->fresh()->home_score);
        self::assertSame(1, MatchEvent::query()->where('live_match_id', $m->id)->count());
    }

    public function test_nobody_but_the_creator_can_undo_or_restore(): void
    {
        $m = $this->kabaddi();
        $e = $this->raid($m);
        $other = $this->player('HRNOTH');

        $this->withHeaders($this->auth($other))->postJson("/api/matches/{$m->id}/events/undo")->assertStatus(403);
        $this->withHeaders($this->auth($other))->postJson("/api/matches/{$m->id}/events/{$e->id}/restore")->assertStatus(403);
    }

    public function test_the_restore_command_is_a_dry_run_unless_told_to_apply(): void
    {
        $m = $this->kabaddi();
        $this->raid($m);
        $gone = $this->raid($m);
        $this->raid($m);
        // Simulate the pre-migration hard delete of the middle event (sequence 2).
        $backup = $gone->getAttributes();
        DB::table('match_events')->where('id', $gone->id)->delete();
        app(MatchEventRecorder::class)->resync($m->fresh());
        self::assertSame(2, $m->fresh()->home_score);

        $path = storage_path('framework/testing-event-backup.json');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, json_encode($backup));

        $this->artisan('matches:restore-event', ['match' => $m->id, '--from-json' => $path])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();
        self::assertSame(2, $m->fresh()->home_score, 'a dry run changes nothing');

        $this->artisan('matches:restore-event', ['match' => $m->id, '--from-json' => $path, '--apply' => true])
            ->assertSuccessful();
        self::assertSame(3, $m->fresh()->home_score);

        // Running it again is refused — the slot is taken.
        $this->artisan('matches:restore-event', ['match' => $m->id, '--from-json' => $path])->assertFailed();
        @unlink($path);
    }

    // --------------------------------------------------------- result trust gates

    private function finishedMatch(): LiveMatch
    {
        return LiveMatch::create([
            'title' => 'F', 'home' => 'HOM', 'away' => 'AWY', 'home_score' => 2, 'away_score' => 1,
            'status' => 'Completed', 'sport' => 'football', 'user_id' => $this->scorer->id,
            'verification_status' => 'pending',
            'home_squad' => [['id' => 'HRNH1', 'name' => 'Home One', 'isCaptain' => true], ['id' => 'HRNH2', 'name' => 'Home Two']],
            'away_squad' => [['id' => 'HRNA1', 'name' => 'Away One']],
        ]);
    }

    public function test_only_that_sides_captain_can_confirm_its_result(): void
    {
        $m = $this->finishedMatch();
        $captain = $this->player('HRNH1');
        $teammate = $this->player('HRNH2');
        $opponent = $this->player('HRNA1');
        $stranger = $this->player('HRNXXX');

        $this->withHeaders($this->auth($stranger))->postJson("/api/matches/{$m->id}/confirm", ['side' => 'home'])->assertStatus(403);
        $this->withHeaders($this->auth($opponent))->postJson("/api/matches/{$m->id}/confirm", ['side' => 'home'])->assertStatus(403);
        $this->withHeaders($this->auth($teammate))->postJson("/api/matches/{$m->id}/confirm", ['side' => 'home'])->assertStatus(403);
        $this->withHeaders($this->auth($captain))->postJson("/api/matches/{$m->id}/confirm", ['side' => 'home'])->assertOk();

        // No captain marked on the away side: any registered away player may confirm.
        $this->withHeaders($this->auth($opponent))->postJson("/api/matches/{$m->id}/confirm", ['side' => 'away'])->assertOk();
    }

    public function test_disputes_need_a_participant_a_target_in_the_match_and_only_once(): void
    {
        $m = $this->finishedMatch();
        $this->player('HRNH1');
        $opponent = $this->player('HRNA1');
        $stranger = $this->player('HRNXXX');

        $this->withHeaders($this->auth($stranger))
            ->postJson("/api/matches/{$m->id}/dispute", ['targetPlayerId' => 'HRNH1', 'type' => 'match_dispute'])
            ->assertStatus(403);
        $this->withHeaders($this->auth($opponent))
            ->postJson("/api/matches/{$m->id}/dispute", ['targetPlayerId' => 'HRNXXX', 'type' => 'match_dispute'])
            ->assertStatus(422);
        $this->withHeaders($this->auth($opponent))
            ->postJson("/api/matches/{$m->id}/dispute", ['targetPlayerId' => 'HRNA1', 'type' => 'match_dispute'])
            ->assertStatus(422);
        $this->withHeaders($this->auth($opponent))
            ->postJson("/api/matches/{$m->id}/dispute", ['targetPlayerId' => 'HRNH1', 'type' => 'match_dispute'])
            ->assertOk();
        $this->withHeaders($this->auth($opponent))
            ->postJson("/api/matches/{$m->id}/dispute", ['targetPlayerId' => 'HRNH1', 'type' => 'match_dispute'])
            ->assertStatus(409);

        self::assertSame(1, ReputationEvent::query()->where('player_id', 'HRNH1')->count());
    }

    public function test_captain_and_vice_captain_flags_are_stored_one_each(): void
    {
        $creator = $this->player('HRNCAP');
        $this->withHeaders($this->auth($creator))->postJson('/api/matches', [
            'matchType' => 'casual', 'playersPerSide' => 7, 'teamA' => 'Home FC', 'teamB' => 'Away FC',
            'sport' => 'football', 'venue' => 'Village ground', 'locality' => 'Keerthipalle',
            'latitude' => 14.42, 'longitude' => 78.22,
            'format' => ['kind' => 'football', 'halves' => 2, 'halfLengthMin' => 25],
            'squadA' => [
                ['name' => 'One', 'isCaptain' => true],
                ['name' => 'Two', 'isCaptain' => true, 'isViceCaptain' => true],
                ['name' => 'Three', 'isViceCaptain' => true],
            ],
        ])->assertSuccessful();

        $squad = LiveMatch::query()->latest('id')->first()->home_squad;
        self::assertTrue($squad[0]['isCaptain'] ?? false);
        self::assertArrayNotHasKey('isCaptain', $squad[1], 'a second captain is dropped');
        self::assertTrue($squad[1]['isViceCaptain'] ?? false);
        self::assertArrayNotHasKey('isViceCaptain', $squad[2], 'a second vice-captain is dropped');
    }

    public function test_the_web_match_pages_respect_privacy_and_never_leak_join_codes(): void
    {
        $private = LiveMatch::create([
            'title' => 'Secret', 'home' => 'AAA', 'away' => 'BBB', 'home_score' => 0, 'away_score' => 0,
            'status' => 'Live', 'sport' => 'football', 'user_id' => $this->scorer->id,
            'is_private' => true, 'join_code' => 'PRIV42',
        ]);

        $this->get("/gamehub/actionboard/match/{$private->id}")->assertNotFound();
        $this->get("/gamehub/actionboard/match/{$private->id}/json")->assertNotFound();

        $list = $this->get('/gamehub/actionboard/matches/json')->assertOk();
        self::assertStringNotContainsString('PRIV42', $list->getContent());
        self::assertStringNotContainsString('Secret', $list->getContent());
    }
}
