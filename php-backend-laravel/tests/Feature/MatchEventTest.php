<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LiveMatch;
use App\Models\MatchEvent;
use App\Models\User;
use App\Services\MatchEventRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Football / badminton scoring.
 *
 * The contract these tests defend: **the client never sends a score.** It posts
 * what happened, and the server derives the scoreline by counting events — so a
 * dropped request, a double-tap or an offline phone can never leave a scoreboard
 * that disagrees with its own timeline.
 */
class MatchEventTest extends TestCase
{
    use RefreshDatabase;

    private MatchEventRecorder $recorder;
    private User $scorer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recorder = app(MatchEventRecorder::class);

        $this->scorer = $this->player('Scorer', 'scorer@haraan.test', 'HRNSCORE');
    }

    /**
     * Scoring sits behind `actionboard.profile`, same as cricket's /score-action, so
     * a scorer needs a COMPLETE profile — name, state, district, primary_sport and
     * that sport's required attributes.
     */
    private function player(string $name, string $email, string $playerId): User
    {
        return User::create([
            'name' => $name, 'email' => $email,
            'password' => Hash::make('secret123'), 'role' => 'user', 'status' => 'active',
            'player_id' => $playerId, 'is_guest' => false,
            'state' => 'Andhra Pradesh', 'district' => 'YSR Kadapa',
            'primary_sport' => 'Football',
            'sport_attributes' => ['position' => 'Midfielder', 'foot' => 'Right'],
        ]);
    }

    private function match(string $sport = 'football'): LiveMatch
    {
        return LiveMatch::create([
            'title' => 'Turf A friendly', 'home' => 'HHH', 'away' => 'KKJ',
            'home_score' => 0, 'away_score' => 0, 'status' => 'live',
            'sport' => $sport, 'user_id' => $this->scorer->id,
        ]);
    }

    private function goal(LiveMatch $m, string $side, int $minute, string $who, ?string $assist = null): MatchEvent
    {
        return $this->recorder->record($m, [
            'kind' => MatchEvent::GOAL, 'side' => $side, 'minute' => $minute,
            'player_name' => $who, 'related_name' => $assist,
        ], $this->scorer);
    }

    // ------------------------------------------------------------ scoreline

    public function test_the_score_is_derived_from_goals(): void
    {
        $m = $this->match();

        $this->goal($m, 'home', 12, 'Rahul');
        $this->goal($m, 'away', 34, 'Imran');
        $this->goal($m, 'home', 67, 'Rahul');

        $m->refresh();

        $this->assertSame(2, (int) $m->home_score);
        $this->assertSame(1, (int) $m->away_score);
        $this->assertSame('2 - 1', $m->score_text);
    }

    /** The classic scoreboard bug: an own goal credits the OTHER side. */
    public function test_an_own_goal_credits_the_opposition(): void
    {
        $m = $this->match();

        $this->recorder->record($m, [
            'kind' => MatchEvent::OWN_GOAL, 'side' => 'home', 'minute' => 20, 'player_name' => 'Suresh',
        ]);

        $m->refresh();

        $this->assertSame(0, (int) $m->home_score);
        $this->assertSame(1, (int) $m->away_score, 'A home own goal is an away goal.');
    }

    public function test_cards_and_subs_never_move_the_score(): void
    {
        $m = $this->match();
        $this->goal($m, 'home', 10, 'Rahul');

        foreach ([MatchEvent::YELLOW, MatchEvent::RED, MatchEvent::SUB] as $kind) {
            $this->recorder->record($m, ['kind' => $kind, 'side' => 'away', 'minute' => 40, 'player_name' => 'X']);
        }

        $m->refresh();
        $this->assertSame(1, (int) $m->home_score);
        $this->assertSame(0, (int) $m->away_score);
    }

    /** A timeline row must render without replaying the feed. */
    public function test_each_event_stores_the_score_as_it_stood_after_it(): void
    {
        $m = $this->match();

        $this->goal($m, 'home', 12, 'Rahul');
        $this->goal($m, 'away', 34, 'Imran');
        $this->goal($m, 'home', 67, 'Rahul');

        $events = MatchEvent::where('live_match_id', $m->id)->inOrder()->get();

        $this->assertSame([1, 0], [$events[0]->home_score, $events[0]->away_score]);
        $this->assertSame([1, 1], [$events[1]->home_score, $events[1]->away_score]);
        $this->assertSame([2, 1], [$events[2]->home_score, $events[2]->away_score]);
    }

    /** Two goals in the same minute must keep the order they happened in. */
    public function test_ordering_is_by_sequence_not_minute(): void
    {
        $m = $this->match();

        $this->goal($m, 'home', 67, 'First');
        $this->goal($m, 'away', 67, 'Second');

        $names = MatchEvent::where('live_match_id', $m->id)->inOrder()->pluck('player_name')->all();

        $this->assertSame(['First', 'Second'], $names);
    }

    public function test_undo_removes_the_last_event_and_restores_the_score(): void
    {
        $m = $this->match();

        $this->goal($m, 'home', 12, 'Rahul');
        $this->goal($m, 'away', 34, 'Imran');

        $this->recorder->undoLast($m);
        $m->refresh();

        $this->assertSame(1, (int) $m->home_score);
        $this->assertSame(0, (int) $m->away_score);
        $this->assertSame(1, MatchEvent::where('live_match_id', $m->id)->count());
    }

    public function test_undo_on_an_empty_match_is_a_no_op(): void
    {
        $this->assertNull($this->recorder->undoLast($this->match()));
    }

    /**
     * The "−" beside a team's tally must not reach across and delete the other
     * team's goal just because it happened to be recorded last.
     */
    public function test_undo_for_a_side_removes_that_sides_goal_not_the_latest(): void
    {
        $m = $this->match();

        $this->goal($m, 'home', 12, 'Rahul');
        $this->goal($m, 'away', 80, 'Imran');   // most recent overall

        $this->recorder->undoLast($m, 'home');
        $m->refresh();

        $this->assertSame(0, (int) $m->home_score);
        $this->assertSame(1, (int) $m->away_score, "Away's goal must survive.");
    }

    /** An own goal raised the other side's score, so undoing there must remove it. */
    public function test_undo_for_a_side_can_remove_an_opposition_own_goal(): void
    {
        $m = $this->match();

        $this->recorder->record($m, [
            'kind' => MatchEvent::OWN_GOAL, 'side' => 'away', 'minute' => 20, 'player_name' => 'Suresh',
        ]);
        $m->refresh();
        $this->assertSame(1, (int) $m->home_score);

        $this->recorder->undoLast($m, 'home');

        $this->assertSame(0, (int) $m->fresh()->home_score);
    }

    public function test_undo_for_a_side_with_nothing_to_undo_is_a_no_op(): void
    {
        $m = $this->match();
        $this->goal($m, 'away', 10, 'Imran');

        $this->assertNull($this->recorder->undoLast($m, 'home'));
        $this->assertSame(1, (int) $m->fresh()->away_score);
    }

    // ------------------------------------------------------------- payload

    /** A scorer appears once with all their minutes, the way a scoreboard reads. */
    public function test_the_scorer_line_groups_a_players_goals(): void
    {
        $m = $this->match();

        $this->goal($m, 'home', 12, 'Rahul');
        $this->goal($m, 'home', 45, 'Rahul');
        $this->goal($m, 'home', 67, 'Imran');

        $payload = $this->recorder->footballPayload($m->fresh());

        $this->assertCount(2, $payload['home_scorers']);
        $this->assertSame('Rahul', $payload['home_scorers'][0]['name']);
        $this->assertSame(["12'", "45'"], $payload['home_scorers'][0]['minutes']);
        $this->assertSame([], $payload['away_scorers']);
    }

    public function test_the_headline_reads_without_the_client_knowing_the_rules(): void
    {
        $m = $this->match();

        $withAssist = $this->goal($m, 'home', 12, 'Rahul', 'Kiran');
        $this->assertSame('Rahul scored, assisted by Kiran', $withAssist->headline());

        $card = $this->recorder->record($m, [
            'kind' => MatchEvent::YELLOW, 'side' => 'away', 'minute' => 30, 'player_name' => 'Imran',
        ]);
        $this->assertSame('Imran booked', $card->headline());
    }

    // ----------------------------------------------------------------- API

    private function token(User $u): string
    {
        return \App\Support\JwtService::issue(
            ['sub' => $u->id],
            (string) config('app.jwt_secret', env('JWT_SECRET', 'change_me')),
        );
    }

    public function test_only_the_creator_can_record_an_event(): void
    {
        $m = $this->match();

        $other = $this->player('Someone', 'other@haraan.test', 'HRNOTHER');

        $this->withHeader('Authorization', 'Bearer ' . $this->token($other))
            ->postJson("/api/matches/{$m->id}/events", ['kind' => 'goal', 'side' => 'home'])
            ->assertStatus(403);

        $this->assertSame(0, MatchEvent::count());
    }

    /** Cricket must never gain a second way to move its score. */
    public function test_a_cricket_match_is_refused_by_the_event_endpoint(): void
    {
        $m = $this->match('cricket');

        $this->withHeader('Authorization', 'Bearer ' . $this->token($this->scorer))
            ->postJson("/api/matches/{$m->id}/events", ['kind' => 'goal', 'side' => 'home'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Cricket matches are scored through /score-action.');
    }

    public function test_recording_over_http_returns_the_derived_state(): void
    {
        $m = $this->match();

        $this->withHeader('Authorization', 'Bearer ' . $this->token($this->scorer))
            ->postJson("/api/matches/{$m->id}/events", [
                'kind' => 'goal', 'side' => 'home', 'minute' => 12, 'player_name' => 'Rahul',
            ])
            ->assertStatus(201)
            ->assertJson(['home_score' => 1, 'away_score' => 0, 'score_text' => '1 - 0']);
    }

    public function test_a_completed_match_is_locked(): void
    {
        $m = $this->match();
        $m->update(['status' => 'completed']);

        $this->withHeader('Authorization', 'Bearer ' . $this->token($this->scorer))
            ->postJson("/api/matches/{$m->id}/events", ['kind' => 'goal', 'side' => 'home'])
            ->assertStatus(422);
    }

    /** A partial update must not wipe the rest of the sport state. */
    public function test_sport_state_is_merged_not_replaced(): void
    {
        $m = $this->match();
        $m->forceFill(['sport_state' => ['half' => 1, 'clock_min' => 20]])->save();

        $this->withHeader('Authorization', 'Bearer ' . $this->token($this->scorer))
            ->postJson("/api/matches/{$m->id}/sport-state", ['state' => ['clock_min' => 67]])
            ->assertOk();

        $state = $m->fresh()->sport_state;

        $this->assertSame(67, $state['clock_min']);
        $this->assertSame(1, $state['half'], 'The half must survive a clock-only update.');
    }

    public function test_deleting_a_match_takes_its_events_with_it(): void
    {
        $m = $this->match();
        $this->goal($m, 'home', 12, 'Rahul');

        $m->delete();

        $this->assertSame(0, MatchEvent::count());
    }
}
