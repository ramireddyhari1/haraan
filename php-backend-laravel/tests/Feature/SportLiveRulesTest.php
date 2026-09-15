<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LiveMatch;
use App\Models\MatchEvent;
use App\Models\User;
use App\Services\MatchEventRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The live-board rules for every sport that isn't cricket — the situations a viewer opens a
 * live match to see: who is serving, deuce, a tie-break, a break point, the kabaddi mat, a
 * basketball box score. Every expectation was counted by hand from the recorded events.
 */
class SportLiveRulesTest extends TestCase
{
    use RefreshDatabase;

    private MatchEventRecorder $recorder;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->recorder = app(MatchEventRecorder::class);
        $this->owner = User::create([
            'name' => 'Owner', 'email' => 'rules@haraan.test',
            'password' => Hash::make('secret123'), 'role' => 'user', 'status' => 'active',
            'player_id' => 'HRNRULES', 'is_guest' => false,
        ]);
    }

    private function match(string $sport, array $state = []): LiveMatch
    {
        return LiveMatch::create([
            'title' => 'Rules', 'home' => 'HHH', 'away' => 'KKJ',
            'home_score' => 0, 'away_score' => 0, 'status' => 'Live',
            'sport' => $sport, 'user_id' => $this->owner->id,
            'sport_state' => $state === [] ? null : $state,
        ]);
    }

    private function ev(LiveMatch $m, array $attrs): MatchEvent
    {
        return $this->recorder->record($m, $attrs, $this->owner);
    }

    private function point(LiveMatch $m, string $side, string $detail = '', ?string $who = null): MatchEvent
    {
        return $this->ev($m, ['kind' => 'point', 'side' => $side, 'detail' => $detail ?: null, 'player_name' => $who]);
    }

    private function points(LiveMatch $m, string $side, int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            $this->point($m, $side);
        }
    }

    private function board(LiveMatch $m): array
    {
        return $this->recorder->boardPayload($m->fresh());
    }

    /** Win one tennis game for a side from 0-0. */
    private function game(LiveMatch $m, string $side): void
    {
        $this->points($m, $side, 4);
    }

    // ── Tennis ─────────────────────────────────────────────────────────────────────

    public function test_tennis_has_no_server_until_the_scorer_names_one(): void
    {
        $m = $this->match('tennis');
        $this->point($m, 'away');

        $this->assertNull($this->board($m)['serving']);
        $this->assertSame(0, $this->board($m)['break_points']);
    }

    public function test_tennis_serve_holds_for_a_game_then_alternates(): void
    {
        $m = $this->match('tennis');
        $this->ev($m, ['kind' => 'serve', 'side' => 'home']);

        // The receiver winning points does NOT move the serve inside a game.
        $this->points($m, 'away', 2);
        $this->assertSame('home', $this->board($m)['serving']);

        $this->points($m, 'away', 2);   // away breaks: 0-1
        $b = $this->board($m);
        $this->assertSame([0, 1], $b['games']);
        $this->assertSame('away', $b['serving']);
    }

    public function test_break_points_are_counted_the_way_tennis_counts_them(): void
    {
        $m = $this->match('tennis');
        $this->ev($m, ['kind' => 'serve', 'side' => 'home']);
        $this->points($m, 'away', 3);       // 0-40

        $b = $this->board($m);
        $this->assertSame(['0', '40'], $b['points']);
        $this->assertSame(3, $b['break_points']);

        $this->points($m, 'home', 3);       // 40-40
        $b = $this->board($m);
        $this->assertTrue($b['deuce']);
        $this->assertSame(0, $b['break_points']);

        $this->point($m, 'away');           // advantage receiver
        $b = $this->board($m);
        $this->assertSame('away', $b['advantage']);
        $this->assertSame(1, $b['break_points']);

        $this->point($m, 'away');           // broken
        $feed = $this->board($m)['feed'];
        $this->assertContains('break', $feed[0]['tags']);
        $this->assertContains('break_point', $feed[0]['tags']);
    }

    public function test_six_all_goes_to_a_tiebreak_and_the_set_ends_seven_six(): void
    {
        $m = $this->match('tennis');
        $this->ev($m, ['kind' => 'serve', 'side' => 'home']);
        for ($i = 0; $i < 6; $i++) {
            $this->game($m, 'home');
            $this->game($m, 'away');
        }

        $b = $this->board($m);
        $this->assertTrue($b['tiebreak']);
        $this->assertSame([6, 6], $b['games']);
        // 12 games alternated from home, so home serves the first tie-break point.
        $this->assertSame('home', $b['serving']);

        $this->point($m, 'home');           // 1-0, then the serve passes after ONE point
        $this->assertSame('away', $this->board($m)['serving']);
        $this->assertSame(['1', '0'], $this->board($m)['points']);
        $this->point($m, 'away');           // 1-1, away serves its second
        $this->assertSame('away', $this->board($m)['serving']);
        $this->point($m, 'home');           // 2-1, back to home for two
        $this->assertSame('home', $this->board($m)['serving']);

        $this->points($m, 'home', 5);       // 7-1
        $b = $this->board($m);
        $this->assertFalse($b['tiebreak']);
        $this->assertSame([[7, 6]], $b['sets']);
        $this->assertSame([1], $b['tiebreaks']);
        // Away received first in the tie-break, so away opens set two.
        $this->assertSame('away', $b['serving']);
        $this->assertSame(1, $m->fresh()->home_score);
    }

    public function test_tennis_match_point_is_flagged(): void
    {
        $m = $this->match('tennis', ['format' => ['bestOf' => 1]]);
        $this->ev($m, ['kind' => 'serve', 'side' => 'home']);
        for ($i = 0; $i < 5; $i++) {
            $this->game($m, 'home');
        }
        $this->points($m, 'home', 3);       // 5-0, 40-0 on away's serve

        $this->assertSame('home', $this->board($m)['match_point']);
    }

    // ── Table tennis ───────────────────────────────────────────────────────────────

    public function test_table_tennis_serve_changes_every_two_points_and_every_point_at_ten_all(): void
    {
        $m = $this->match('table_tennis');
        $this->ev($m, ['kind' => 'serve', 'side' => 'home']);

        $this->assertSame('home', $this->board($m)['serving']);
        $this->assertSame(2, $this->board($m)['serves_left']);
        $this->point($m, 'away');
        $this->assertSame('home', $this->board($m)['serving']);
        $this->assertSame(1, $this->board($m)['serves_left']);
        $this->point($m, 'away');
        $this->assertSame('away', $this->board($m)['serving']);

        $this->points($m, 'home', 10);      // 10-2
        $this->points($m, 'away', 8);       // 10-10
        $b = $this->board($m);
        $this->assertTrue($b['deuce']);
        $this->assertSame(1, $b['serves_left']);
        $first = $b['serving'];
        $this->point($m, 'home');           // 11-10
        $this->assertNotSame($first, $this->board($m)['serving']);
        $this->assertSame('home', $this->board($m)['set_point']);
    }

    public function test_table_tennis_first_server_alternates_by_game(): void
    {
        $m = $this->match('table_tennis');
        $this->ev($m, ['kind' => 'serve', 'side' => 'home']);
        $this->points($m, 'home', 11);

        $b = $this->board($m);
        $this->assertSame([[11, 0]], $b['sets']);
        $this->assertSame('away', $b['serving']);
    }

    // ── Badminton ─────────────────────────────────────────────────────────────────

    public function test_badminton_serves_from_the_court_its_score_says(): void
    {
        $m = $this->match('badminton');
        $this->point($m, 'home');           // home 1 → odd → left
        $b = $this->board($m);
        $this->assertSame('home', $b['serving']);
        $this->assertSame('left', $b['service_court']);

        $this->point($m, 'home');           // 2 → right
        $this->assertSame('right', $this->board($m)['service_court']);
    }

    public function test_badminton_golden_point_at_twenty_nine_all(): void
    {
        $m = $this->match('badminton');
        $this->points($m, 'home', 20);
        $this->points($m, 'away', 20);
        $this->assertTrue($this->board($m)['deuce']);

        for ($i = 0; $i < 9; $i++) {
            $this->point($m, 'home');
            $this->point($m, 'away');
        }
        $b = $this->board($m);
        $this->assertSame([29, 29], $b['current']);
        $this->assertTrue($b['golden_point']);

        $this->point($m, 'away');
        $this->assertSame([[29, 30]], $this->board($m)['sets']);
    }

    // ── Volleyball ────────────────────────────────────────────────────────────────

    public function test_volleyball_rotates_on_a_side_out_and_counts_timeouts_per_set(): void
    {
        $m = $this->match('volleyball');
        $this->ev($m, ['kind' => 'serve', 'side' => 'home']);
        $this->point($m, 'home');           // hold, no rotation
        $this->assertSame([1, 1], $this->board($m)['rotation']);
        $this->point($m, 'away');           // side-out → away rotates
        $b = $this->board($m);
        $this->assertSame([1, 2], $b['rotation']);
        $this->assertSame('away', $b['serving']);
        $this->assertContains('side_out', $b['feed'][0]['tags']);

        $this->ev($m, ['kind' => 'timeout', 'side' => 'home']);
        $this->assertSame([1, 0], $this->board($m)['timeouts']);

        $this->points($m, 'home', 24);      // 25-1
        $b = $this->board($m);
        $this->assertSame([[25, 1]], $b['sets']);
        $this->assertSame([0, 0], $b['timeouts']);
        $this->assertSame([1, 1], $b['rotation']);
        // Home served first in set one, so away serves first in set two.
        $this->assertSame('away', $b['serving']);
    }

    // ── Kabaddi ───────────────────────────────────────────────────────────────────

    public function test_kabaddi_mat_all_out_is_derived_and_worth_two(): void
    {
        $m = $this->match('kabaddi', ['rules' => ['mat' => true]]);
        $this->point($m, 'home', 'raid:3');    // super raid, away 4 left
        $b = $this->board($m);
        $this->assertSame([7, 4], $b['mat']['on']);
        $this->assertContains('super_raid', $b['feed'][0]['tags']);

        $this->point($m, 'home', 'raid:4');    // mat cleared: 4 + all-out 2
        $b = $this->board($m);
        $this->assertSame(9, $m->fresh()->home_score);
        $this->assertSame([7, 7], $b['mat']['on']);
        $this->assertContains('all_out', $b['feed'][0]['tags']);
        $this->assertSame(6, $b['feed'][0]['value']);
    }

    public function test_kabaddi_super_tackle_with_three_or_fewer_defenders(): void
    {
        $m = $this->match('kabaddi', ['rules' => ['mat' => true]]);
        $this->point($m, 'home', 'raid:4');    // away down to 3
        $this->point($m, 'away', 'tackle');    // 3 defenders tackle home's raider

        $b = $this->board($m);
        $this->assertContains('super_tackle', $b['feed'][0]['tags']);
        $this->assertSame(2, $m->fresh()->away_score);
        // Only ONE player back for a super tackle: the extra point is a bonus.
        $this->assertSame([6, 4], $b['mat']['on']);
    }

    public function test_kabaddi_third_raid_after_two_empty_ones_is_do_or_die(): void
    {
        $m = $this->match('kabaddi', ['rules' => ['mat' => true]]);
        $this->ev($m, ['kind' => 'serve', 'side' => 'home']);
        $this->ev($m, ['kind' => 'raid', 'side' => 'home', 'detail' => 'empty']);
        $this->ev($m, ['kind' => 'raid', 'side' => 'away', 'detail' => 'empty']);
        $this->ev($m, ['kind' => 'raid', 'side' => 'home', 'detail' => 'empty']);
        $this->ev($m, ['kind' => 'raid', 'side' => 'away', 'detail' => 'empty']);

        $b = $this->board($m);
        $this->assertSame('home', $b['raiding']);
        $this->assertTrue($b['do_or_die']);

        $this->point($m, 'away', 'dod_out');
        $b = $this->board($m);
        $this->assertSame(1, $m->fresh()->away_score);
        $this->assertSame([6, 7], $b['mat']['on']);
        // Away has raided empty twice too, so its raid is now the do-or-die one.
        $this->assertSame('away', $b['raiding']);
        $this->assertTrue($b['do_or_die']);
    }

    public function test_kabaddi_without_mat_rules_scores_exactly_as_before(): void
    {
        $m = $this->match('kabaddi');
        $this->point($m, 'home', 'raid');
        $this->point($m, 'home', 'super_raid');
        $this->point($m, 'home', 'all_out');
        $this->point($m, 'away', 'tackle');

        $this->assertSame(6, $m->fresh()->home_score);
        $this->assertSame(1, $m->fresh()->away_score);
        $this->assertNull($this->board($m)['mat']);
    }

    // ── Basketball ────────────────────────────────────────────────────────────────

    public function test_basketball_box_score_team_fouls_and_assists(): void
    {
        $m = $this->match('basketball');
        $this->ev($m, ['kind' => 'point', 'side' => 'home', 'detail' => '3', 'player_name' => 'Arjun', 'related_name' => 'Ravi']);
        $this->ev($m, ['kind' => 'rebound', 'side' => 'home', 'player_name' => 'Ravi']);
        $this->ev($m, ['kind' => 'foul', 'side' => 'away', 'player_name' => 'Kiran']);
        $this->ev($m, ['kind' => 'foul', 'side' => 'away', 'player_name' => 'Kiran']);

        $b = $this->board($m);
        $this->assertSame([0, 2], $b['team_fouls']);
        $arjun = collect($b['box']['players'])->firstWhere('name', 'Arjun');
        $ravi = collect($b['box']['players'])->firstWhere('name', 'Ravi');
        $this->assertSame(3, $arjun['pts']);
        $this->assertSame(1, $arjun['fg3']);
        $this->assertSame(1, $ravi['ast']);
        $this->assertSame(1, $ravi['reb']);
        $this->assertContains('three', collect($b['feed'])->firstWhere('kind', 'point')['tags']);

        $this->ev($m, ['kind' => 'period']);
        $b = $this->board($m);
        $this->assertSame([0, 0], $b['team_fouls']);
        $this->assertSame('Q2', $b['period_label']);
        $this->assertSame(3, $m->fresh()->home_score);
    }

    // ── Shared plumbing ───────────────────────────────────────────────────────────

    public function test_undo_by_sequence_removes_exactly_that_row(): void
    {
        $m = $this->match('basketball');
        $this->point($m, 'home', '2');
        $rebound = $this->ev($m, ['kind' => 'rebound', 'side' => 'away', 'player_name' => 'Kiran']);
        $this->point($m, 'home', '3');

        $this->recorder->undoLast($m->fresh(), null, $rebound->sequence);

        $b = $this->board($m);
        $this->assertNull(collect($b['feed'])->firstWhere('kind', 'rebound'));
        $this->assertSame(5, $m->fresh()->home_score);
    }

    public function test_football_possession_needs_a_real_minute_of_tracking(): void
    {
        $m = $this->match('football', ['clock' => ['half' => 1, 'running' => true, 'base_sec' => 0, 'anchor_ms' => 0, 'half_length' => 45]]);

        Carbon::setTestNow(Carbon::parse('2026-09-15 10:00:00'));
        $this->ev($m, ['kind' => 'possession', 'side' => 'home']);
        Carbon::setTestNow(Carbon::parse('2026-09-15 10:00:20'));
        $this->ev($m, ['kind' => 'possession', 'side' => 'away']);
        Carbon::setTestNow(Carbon::parse('2026-09-15 10:00:30'));
        $this->ev($m, ['kind' => 'possession']);   // ball dead

        $this->assertNull($this->recorder->footballPayload($m->fresh())['possession']);

        Carbon::setTestNow(Carbon::parse('2026-09-15 10:01:00'));
        $this->ev($m, ['kind' => 'possession', 'side' => 'away']);
        Carbon::setTestNow(Carbon::parse('2026-09-15 10:01:50'));
        $this->ev($m, ['kind' => 'possession']);
        Carbon::setTestNow();

        // home 20s, away 10s + 50s = 60s → 25 / 75, and the dead-ball half minute counts for nobody.
        $p = $this->recorder->footballPayload($m->fresh())['possession'];
        $this->assertSame(25, $p['home']);
        $this->assertSame(75, $p['away']);
        $this->assertSame(80, $p['tracked_sec']);
    }

    public function test_football_goal_assists_are_listed(): void
    {
        $m = $this->match('football');
        $this->ev($m, ['kind' => 'goal', 'side' => 'home', 'minute' => 12, 'player_name' => 'Rahul', 'related_name' => 'Imran']);
        $this->ev($m, ['kind' => 'goal', 'side' => 'home', 'minute' => 40, 'player_name' => 'Rahul', 'related_name' => 'Imran']);

        $assists = $this->recorder->footballPayload($m->fresh())['assists'];
        $this->assertSame([['side' => 'home', 'name' => 'Imran', 'assists' => 2]], $assists);
    }
}
