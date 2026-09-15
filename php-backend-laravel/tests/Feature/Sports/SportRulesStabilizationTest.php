<?php

declare(strict_types=1);

namespace Tests\Feature\Sports;

use App\Models\LiveMatch;
use App\Models\MatchEvent;
use App\Models\User;
use App\Services\CareerBattingService;
use App\Services\MatchEventRecorder;
use App\Services\Scoring\EventGuard;
use App\Services\Scoring\TennisMachine;
use App\Support\SportRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The September 2026 rules pass, sport by sport. Every expectation is counted by hand from
 * the events recorded in the test. Rules that change what an event is WORTH are versioned
 * (sport_state.rules_version) and each such test also proves a version-1 match still reads
 * the way it did.
 */
final class SportRulesStabilizationTest extends TestCase
{
    use RefreshDatabase;

    private MatchEventRecorder $recorder;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->recorder = app(MatchEventRecorder::class);
        $this->owner = User::create([
            'name' => 'Owner', 'email' => 'rules2@haraan.test',
            'password' => Hash::make('secret123'), 'role' => 'user', 'status' => 'active',
            'player_id' => 'HRNRULE2', 'is_guest' => false,
        ]);
    }

    private function match(string $sport, array $state = [], array $attrs = []): LiveMatch
    {
        return LiveMatch::create(array_merge([
            'title' => 'Rules', 'home' => 'HHH', 'away' => 'KKJ',
            'home_score' => 0, 'away_score' => 0, 'status' => 'Live',
            'sport' => $sport, 'user_id' => $this->owner->id,
            'sport_state' => $state === [] ? null : $state,
        ], $attrs));
    }

    private function ev(LiveMatch $m, array $attrs): MatchEvent
    {
        return $this->recorder->record($m, $attrs, $this->owner);
    }

    private function point(LiveMatch $m, string $side, string $detail = '', ?string $who = null): void
    {
        $this->ev($m, ['kind' => 'point', 'side' => $side, 'detail' => $detail ?: null, 'player_name' => $who]);
    }

    private function state(LiveMatch $m): array
    {
        return (array) $m->fresh()->sport_state;
    }

    // ------------------------------------------------------------------ kabaddi

    private function kabaddiV2(): LiveMatch
    {
        return $this->match('kabaddi', ['rules' => ['mat' => true], 'rules_version' => 2]);
    }

    public function test_kabaddi_v2_an_empty_do_or_die_raid_loses_the_raider(): void
    {
        $m = $this->kabaddiV2();
        // Home raids empty twice (away raids in between), so home's third raid is do-or-die.
        $this->ev($m, ['kind' => 'raid', 'side' => 'home']);
        $this->ev($m, ['kind' => 'raid', 'side' => 'away']);
        $this->ev($m, ['kind' => 'raid', 'side' => 'home']);
        $this->ev($m, ['kind' => 'raid', 'side' => 'away']);
        $this->ev($m, ['kind' => 'raid', 'side' => 'home']); // do-or-die, empty

        $m->refresh();
        self::assertSame(0, $m->home_score);
        self::assertSame(1, $m->away_score, 'a failed do-or-die is a point to the defence');
        self::assertSame([6, 7], $this->state($m)['mat']['on'], 'the raider is out');
    }

    public function test_kabaddi_v1_keeps_its_old_reading_of_the_same_raids(): void
    {
        $m = $this->match('kabaddi', ['rules' => ['mat' => true]]);
        foreach (['home', 'away', 'home', 'away', 'home'] as $side) {
            $this->ev($m, ['kind' => 'raid', 'side' => $side]);
        }

        $m->refresh();
        self::assertSame(0, $m->away_score, 'version-1 results are never rewritten');
    }

    public function test_kabaddi_v2_bonus_needs_six_defenders(): void
    {
        $m = $this->kabaddiV2();
        // Home touches two out: away down to 5 on the mat.
        $this->point($m, 'home', 'raid:2', 'Pawan');
        // Away raid empty so home raids next.
        $this->ev($m, ['kind' => 'raid', 'side' => 'away']);
        // Home claims touch + bonus against 5 defenders: the bonus does not count.
        $this->point($m, 'home', 'raid:1:b', 'Pawan');

        $m->refresh();
        self::assertSame(3, $m->home_score, '2 + 1 touch, no bonus against five defenders');
    }

    public function test_kabaddi_v2_a_bonus_point_revives_no_one(): void
    {
        $m = $this->kabaddiV2();
        // Away tackles home's raider: home 6 on the mat.
        $this->point($m, 'away', 'tackle', 'Surjeet');
        // Away raid empty. Home raid: bonus only (7 defenders on) — bonus counts, no revival.
        $this->ev($m, ['kind' => 'raid', 'side' => 'away']);
        $this->point($m, 'home', 'bonus', 'Pawan');

        $state = $this->state($m);
        self::assertSame(1, $m->fresh()->home_score);
        self::assertSame(6, $state['mat']['on'][0], 'still one out — a bonus is not a touch');
    }

    public function test_kabaddi_v1_bonus_still_revives(): void
    {
        $m = $this->match('kabaddi', ['rules' => ['mat' => true]]);
        $this->point($m, 'away', 'tackle', 'Surjeet');
        $this->ev($m, ['kind' => 'raid', 'side' => 'away']);
        $this->point($m, 'home', 'bonus', 'Pawan');

        self::assertSame(7, $this->state($m)['mat']['on'][0]);
    }

    public function test_new_non_cricket_matches_are_stamped_with_the_current_rules_version(): void
    {
        $scorer = User::create([
            'name' => 'Kab', 'email' => 'kab@haraan.test', 'password' => Hash::make('secret123'),
            'role' => 'user', 'status' => 'active', 'player_id' => 'HRNKAB1', 'is_guest' => false,
            'state' => 'Andhra Pradesh', 'district' => 'YSR Kadapa', 'primary_sport' => 'Football',
            'sport_attributes' => ['position' => 'Midfielder', 'foot' => 'Right'],
        ]);
        $token = \App\Support\JwtService::issueForUser($scorer, (string) (config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me')));

        $this->withHeader('Authorization', 'Bearer ' . $token)->postJson('/api/matches', [
            'matchType' => 'casual', 'playersPerSide' => 7, 'teamA' => 'Home FC', 'teamB' => 'Away FC',
            'sport' => 'football', 'venue' => 'Village ground', 'locality' => 'Keerthipalle',
            'latitude' => 14.42, 'longitude' => 78.22,
            'format' => ['kind' => 'football', 'halves' => 2, 'halfLengthMin' => 25],
        ])->assertSuccessful();

        $m = LiveMatch::query()->latest('id')->first();
        self::assertSame(SportRules::CURRENT_VERSION, SportRules::version((array) $m->sport_state));

        // A client patch can't downgrade it, and turning on a rule doesn't wipe the version.
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/matches/{$m->id}/sport-state", ['state' => ['rules_version' => 1, 'rules' => ['mat' => true]]])
            ->assertOk();
        self::assertSame(SportRules::CURRENT_VERSION, SportRules::version((array) $m->fresh()->sport_state));
    }

    // --------------------------------------------------------------- basketball

    public function test_basketball_team_fouls_carry_into_overtime(): void
    {
        $m = $this->match('basketball');
        // Four quarters, then two home fouls in Q4 and overtime begins.
        foreach (range(1, 3) as $_) {
            $this->ev($m, ['kind' => 'period']);
        }
        $this->ev($m, ['kind' => 'foul', 'side' => 'home', 'player_name' => 'Arjun']);
        $this->ev($m, ['kind' => 'foul', 'side' => 'home', 'player_name' => 'Ravi']);
        $this->ev($m, ['kind' => 'period']); // into OT

        self::assertSame([2, 0], $this->state($m)['team_fouls']);
    }

    public function test_basketball_team_fouls_still_reset_each_quarter(): void
    {
        $m = $this->match('basketball');
        $this->ev($m, ['kind' => 'foul', 'side' => 'home', 'player_name' => 'Arjun']);
        $this->ev($m, ['kind' => 'period']);

        self::assertSame([0, 0], $this->state($m)['team_fouls']);
    }

    // ------------------------------------------------------------------- tennis

    public function test_tennis_no_ad_the_deciding_point_takes_the_game(): void
    {
        $t = new TennisMachine(['noAd' => true]);
        $t->setServer('home');
        foreach (['home', 'away', 'home', 'away', 'home', 'away'] as $side) {
            $t->point($side); // 40–40
        }
        self::assertSame(1, $t->breakPoints(), 'the deciding point is a break point for the receiver');
        $r = $t->point('away');
        self::assertSame('game', $r['closes']);
        self::assertSame([0, 1], [$t->gamesHome, $t->gamesAway]);
    }

    public function test_tennis_with_advantage_40_40_needs_two_clear(): void
    {
        $t = new TennisMachine([]);
        foreach (['home', 'away', 'home', 'away', 'home', 'away'] as $side) {
            $t->point($side);
        }
        self::assertNull($t->point('away')['closes']);
    }

    public function test_tennis_final_set_super_tiebreak_to_ten(): void
    {
        $t = new TennisMachine(['bestOf' => 3, 'finalSet' => 'super_tiebreak']);
        $t->setServer('home');
        $winGame = function (string $side) use ($t): void {
            for ($i = 0; $i < 4; $i++) {
                $t->point($side);
            }
        };
        for ($g = 0; $g < 6; $g++) {
            $winGame('home');
        }
        for ($g = 0; $g < 6; $g++) {
            $winGame('away');
        }
        self::assertTrue($t->superTiebreak, 'one set all → match tie-break');

        for ($i = 0; $i < 9; $i++) {
            $t->point('home');
        }
        self::assertFalse($t->decided(), '9 points is not 10');
        $t->point('home');
        self::assertTrue($t->decided());
        self::assertSame([2, 1], [$t->setsHome, $t->setsAway]);
    }

    // ------------------------------------------------------------ table tennis

    public function test_table_tennis_allows_one_timeout_per_match_not_per_game(): void
    {
        $m = $this->match('table_tennis', ['format' => ['bestOf' => 3]]);
        $this->ev($m, ['kind' => 'timeout', 'side' => 'home']);
        for ($i = 0; $i < 11; $i++) {
            $this->point($m, 'home');
        }

        $guard = app(EventGuard::class);
        self::assertNotNull($guard->refusal($m->fresh(), ['kind' => 'timeout', 'side' => 'home']));
        self::assertNull($guard->refusal($m->fresh(), ['kind' => 'timeout', 'side' => 'away']));
    }

    // -------------------------------------------------------------- volleyball

    public function test_volleyball_third_timeout_in_a_set_is_refused(): void
    {
        $m = $this->match('volleyball');
        $this->ev($m, ['kind' => 'timeout', 'side' => 'away']);
        $this->ev($m, ['kind' => 'timeout', 'side' => 'away']);

        self::assertNotNull(app(EventGuard::class)->refusal($m->fresh(), ['kind' => 'timeout', 'side' => 'away']));
    }

    // --------------------------------------------------------------- badminton

    public function test_a_decided_badminton_match_refuses_more_points(): void
    {
        $m = $this->match('badminton', ['format' => ['bestOf' => 1]]);
        for ($i = 0; $i < 21; $i++) {
            $this->point($m, 'home');
        }

        $refusal = app(EventGuard::class)->refusal($m->fresh(), ['kind' => 'point', 'side' => 'away']);
        self::assertNotNull($refusal);
        self::assertStringContainsString('decided', $refusal);
    }

    // ---------------------------------------------------------------- football

    public function test_football_a_second_yellow_is_a_sending_off(): void
    {
        $m = $this->match('football');
        $this->ev($m, ['kind' => 'yellow', 'side' => 'home', 'player_name' => 'Rahul', 'minute' => 10]);
        $this->ev($m, ['kind' => 'yellow', 'side' => 'home', 'player_name' => 'Rahul', 'minute' => 60]);

        $refusal = app(EventGuard::class)->refusal($m->fresh(), ['kind' => 'goal', 'side' => 'home', 'player_name' => 'rahul']);
        self::assertNotNull($refusal, 'a sent-off player cannot score');
        self::assertNull(app(EventGuard::class)->refusal($m->fresh(), ['kind' => 'goal', 'side' => 'home', 'player_name' => 'Imran']));
    }

    public function test_the_guard_answers_over_http_with_the_reason(): void
    {
        $scorer = User::create([
            'name' => 'Scorer', 'email' => 'guard@haraan.test', 'password' => Hash::make('secret123'),
            'role' => 'user', 'status' => 'active', 'player_id' => 'HRNGUARD', 'is_guest' => false,
            'state' => 'Andhra Pradesh', 'district' => 'YSR Kadapa', 'primary_sport' => 'Football',
            'sport_attributes' => ['position' => 'Midfielder', 'foot' => 'Right'],
        ]);
        $m = LiveMatch::create([
            'title' => 'F', 'home' => 'HHH', 'away' => 'KKJ', 'home_score' => 0, 'away_score' => 0,
            'status' => 'Live', 'sport' => 'football', 'user_id' => $scorer->id,
        ]);
        $this->recorder->record($m, ['kind' => 'red', 'side' => 'away', 'player_name' => 'Imran'], $scorer);
        $token = \App\Support\JwtService::issueForUser($scorer, (string) (config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me')));

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/matches/{$m->id}/events", ['kind' => 'goal', 'side' => 'away', 'player_name' => 'Imran'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'rule_violation');
        self::assertSame(0, $m->fresh()->away_score);
    }

    // -------------------------------------------------------------- basketball guard

    public function test_a_fouled_out_basketball_player_cannot_score(): void
    {
        $m = $this->match('basketball');
        for ($i = 0; $i < 5; $i++) {
            $this->ev($m, ['kind' => 'foul', 'side' => 'home', 'player_name' => 'Arjun']);
        }

        self::assertNotNull(app(EventGuard::class)->refusal($m->fresh(), ['kind' => 'point', 'side' => 'home', 'player_name' => 'Arjun', 'detail' => '2']));
    }

    // ------------------------------------------------------------------ cricket

    private function cricketMatch(): LiveMatch
    {
        return $this->match('cricket', [], [
            'home_squad' => [['id' => 'A1', 'name' => 'Asha'], ['id' => 'A2', 'name' => 'Arun'], ['id' => 'A3', 'name' => 'Anil']],
            'away_squad' => [['id' => 'B1', 'name' => 'Bala'], ['id' => 'B2', 'name' => 'Bhanu']],
        ]);
    }

    private function action(LiveMatch $m, string $type, array $payload = []): void
    {
        DB::table('match_actions')->insert([
            'match_id' => $m->id, 'innings' => 1, 'over_number' => 0, 'ball_number' => 0,
            'action_type' => $type, 'payload' => json_encode($payload), 'version' => 1,
            'created_by' => $this->owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_cricket_a_run_out_is_not_the_bowlers_wicket(): void
    {
        $m = $this->cricketMatch();
        $this->action($m, 'start', ['batting_team' => 1, 'striker_id' => 'A1', 'non_striker_id' => 'A2', 'bowler_id' => 'B1']);
        $this->action($m, 'wicket', ['dismissal' => 'runout', 'fielder_id' => 'B2', 'new_batsman_id' => 'A3']);
        $this->action($m, 'wicket', ['dismissal' => 'bowled', 'new_batsman_id' => null]);

        $inn = CareerBattingService::replayMatch($m)[0];
        self::assertSame(1, $inn['bowling']['B1']['wickets'], 'bowled counts, run out does not');
        self::assertSame(1, $inn['fielding']['B2']['run_outs']);
    }

    public function test_cricket_a_non_striker_run_out_removes_the_non_striker(): void
    {
        $m = $this->cricketMatch();
        $this->action($m, 'start', ['batting_team' => 1, 'striker_id' => 'A1', 'non_striker_id' => 'A2', 'bowler_id' => 'B1']);
        $this->action($m, 'wicket', ['dismissal' => 'runout', 'out_role' => 'non_striker', 'new_batsman_id' => 'A3']);
        $this->action($m, 'runs', ['value' => 4]);

        $inn = CareerBattingService::replayMatch($m)[0];
        self::assertTrue($inn['batting']['A2']['out'], 'the non-striker is out');
        self::assertFalse($inn['batting']['A1']['out']);
        self::assertSame(4, $inn['batting']['A1']['runs'], 'the striker kept the strike');
    }

    public function test_cricket_retired_hurt_is_not_out(): void
    {
        $m = $this->cricketMatch();
        $this->action($m, 'start', ['batting_team' => 1, 'striker_id' => 'A1', 'non_striker_id' => 'A2', 'bowler_id' => 'B1']);
        $this->action($m, 'runs', ['value' => 2]);
        $this->action($m, 'wicket', ['dismissal' => 'retired_hurt', 'new_batsman_id' => 'A3']);

        $inn = CareerBattingService::replayMatch($m)[0];
        self::assertFalse($inn['batting']['A1']['out']);
        self::assertSame(0, $inn['bowling']['B1']['wickets']);
    }

    public function test_cricket_dismissal_text_names_the_fielder(): void
    {
        self::assertSame('c Bhanu b Bala', \App\Support\CricketRules::dismissalText(['dismissal' => 'caught'], 'Bala', 'Bhanu'));
        self::assertSame('c & b Bala', \App\Support\CricketRules::dismissalText(['dismissal' => 'caught'], 'Bala', 'Bala'));
        self::assertSame('run out (Bhanu)', \App\Support\CricketRules::dismissalText(['dismissal' => 'Run out'], 'Bala', 'Bhanu'));
    }
}
