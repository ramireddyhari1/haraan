<?php

declare(strict_types=1);

namespace Tests\Feature\Stats;

use App\Models\LiveMatch;
use App\Models\PlayerCareerBatting;
use App\Models\PlayerMatchStat;
use App\Models\PlayerSportCareer;
use App\Models\User;
use App\Services\MatchCompletion;
use App\Services\MatchEventRecorder;
use App\Services\Stats\LeaderboardRankService;
use App\Services\Stats\MatchPlayerStatsService;
use App\Support\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * event log → player → match stats → insights → career, end to end, with nothing invented.
 */
final class PlayerStatsPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $pid, string $sport = 'Football', array $extra = []): User
    {
        $attrs = match ($sport) {
            'Cricket' => ['role' => 'All-rounder', 'batting' => 'Right', 'bowling' => 'Right-arm medium'],
            default => ['position' => 'Midfielder', 'foot' => 'Right'],
        };

        return User::create(array_merge([
            'name' => "Player {$pid}", 'email' => strtolower($pid) . '@haraan.test',
            'password' => Hash::make('secret123'), 'role' => 'user', 'status' => 'active',
            'player_id' => $pid, 'is_guest' => false, 'state' => 'Andhra Pradesh', 'district' => 'YSR Kadapa',
            'primary_sport' => $sport, 'sport_attributes' => $attrs,
        ], $extra));
    }

    private function token(User $u): string
    {
        return JwtService::issueForUser($u, (string) (config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me')));
    }

    // ------------------------------------------------------------------ football

    private function footballMatch(User $creator): LiveMatch
    {
        return LiveMatch::create([
            'title' => 'Derby', 'home' => 'HOM', 'away' => 'AWY', 'home_full' => 'Home FC', 'away_full' => 'Away FC',
            'home_score' => 0, 'away_score' => 0, 'status' => 'Live', 'sport' => 'football',
            'user_id' => $creator->id,
            'home_squad' => [['id' => 'HRNF1', 'name' => 'Rahul'], ['id' => 'HRNF2', 'name' => 'Karthik']],
            'away_squad' => [['id' => 'HRNF3', 'name' => 'Imran'], ['id' => null, 'name' => 'Guest Gopi']],
        ]);
    }

    public function test_finishing_a_football_match_builds_real_player_rows_and_careers(): void
    {
        $creator = $this->user('HRNF1');
        $this->user('HRNF2');
        $this->user('HRNF3');
        $m = $this->footballMatch($creator);
        $rec = app(MatchEventRecorder::class);

        $rec->record($m, ['kind' => 'goal', 'side' => 'home', 'player_name' => 'Rahul', 'related_name' => 'Karthik', 'minute' => 10], $creator);
        $rec->record($m, ['kind' => 'goal', 'side' => 'home', 'player_name' => 'rahul', 'minute' => 30], $creator);
        $rec->record($m, ['kind' => 'goal', 'side' => 'away', 'player_name' => 'Guest Gopi', 'minute' => 50], $creator);
        $rec->record($m, ['kind' => 'yellow', 'side' => 'away', 'player_name' => 'Imran', 'minute' => 60], $creator);

        $this->withHeader('Authorization', 'Bearer ' . $this->token($creator))
            ->postJson("/api/matches/{$m->id}/complete")
            ->assertOk();

        $m->refresh();
        self::assertNotNull($m->completed_at);

        $rahul = PlayerMatchStat::query()->where('match_id', $m->id)->where('player_id', 'HRNF1')->first();
        self::assertSame(2, $rahul->stats['goals'], 'names join case-insensitively on the same side');
        self::assertSame('win', $rahul->result);
        self::assertSame(1, PlayerMatchStat::query()->where('player_id', 'HRNF2')->first()->stats['assists']);
        self::assertSame(1, PlayerMatchStat::query()->where('player_id', 'HRNF3')->first()->stats['yellow_cards']);

        $guest = PlayerMatchStat::query()->where('match_id', $m->id)->whereNull('player_id')->first();
        self::assertSame('Guest Gopi', $guest->player_name);
        self::assertSame(1, $guest->stats['goals']);

        $career = PlayerSportCareer::query()->where('player_id', 'HRNF1')->where('sport', 'football')->first();
        self::assertSame(1, $career->matches);
        self::assertSame(1, $career->wins);
        self::assertSame(2, $career->totals['goals']);
        self::assertSame(1, PlayerSportCareer::query()->where('player_id', 'HRNF3')->first()->losses);
        self::assertSame(0, PlayerSportCareer::query()->whereNull('player_id')->count(), 'guests never get careers');
    }

    public function test_only_the_creator_can_finish_a_match(): void
    {
        $creator = $this->user('HRNF1');
        $stranger = $this->user('HRNX9');
        $m = $this->footballMatch($creator);

        $this->withHeader('Authorization', 'Bearer ' . $this->token($stranger))
            ->postJson("/api/matches/{$m->id}/complete")
            ->assertStatus(403);
        self::assertNull($m->fresh()->completed_at);
    }

    public function test_the_insights_payload_carries_the_same_player_figures(): void
    {
        $creator = $this->user('HRNF1');
        $m = $this->footballMatch($creator);
        app(MatchEventRecorder::class)->record($m, ['kind' => 'goal', 'side' => 'home', 'player_name' => 'Rahul', 'minute' => 5], $creator);

        $json = $this->getJson("/api/live-matches/{$m->id}/insights")->assertOk()->json();
        $rahul = collect($json['playerStats'])->firstWhere('playerId', 'HRNF1');
        self::assertSame(1, $rahul['stats']['goals']);

        $this->getJson("/api/live-matches/{$m->id}/player-stats")->assertOk()
            ->assertJsonPath('sport', 'football')
            ->assertJsonPath('finished', false);
    }

    // ------------------------------------------------------------------- kabaddi / basketball

    public function test_kabaddi_and_basketball_rows_match_their_boards(): void
    {
        $creator = $this->user('HRNK1');
        $k = LiveMatch::create([
            'title' => 'K', 'home' => 'KDW', 'away' => 'KNT', 'home_score' => 0, 'away_score' => 0,
            'status' => 'Live', 'sport' => 'kabaddi', 'user_id' => $creator->id,
            'home_squad' => [['id' => 'HRNK1', 'name' => 'Suresh']], 'away_squad' => [['id' => null, 'name' => 'Ravi']],
        ]);
        $rec = app(MatchEventRecorder::class);
        $rec->record($k, ['kind' => 'point', 'side' => 'home', 'detail' => 'raid', 'player_name' => 'Suresh'], $creator);
        $rec->record($k, ['kind' => 'point', 'side' => 'home', 'detail' => 'raid', 'player_name' => 'Suresh'], $creator);
        $rec->record($k, ['kind' => 'point', 'side' => 'away', 'detail' => 'tackle', 'player_name' => 'Ravi'], $creator);

        $rows = collect(app(MatchPlayerStatsService::class)->forMatch($k->fresh()));
        self::assertSame(2, (array) $rows->firstWhere('playerId', 'HRNK1')['stats'] === [] ? 0 : ((array) $rows->firstWhere('playerId', 'HRNK1')['stats'])['points']);
        self::assertSame(1, ((array) $rows->firstWhere('name', 'Ravi')['stats'])['tackle_points']);

        $b = LiveMatch::create([
            'title' => 'B', 'home' => 'HOO', 'away' => 'PS', 'home_score' => 0, 'away_score' => 0,
            'status' => 'Live', 'sport' => 'basketball', 'user_id' => $creator->id,
            'home_squad' => [['id' => 'HRNK1', 'name' => 'Arjun']], 'away_squad' => [],
        ]);
        $rec->record($b, ['kind' => 'point', 'side' => 'home', 'detail' => '3', 'player_name' => 'Arjun'], $creator);
        $rec->record($b, ['kind' => 'rebound', 'side' => 'home', 'player_name' => 'Arjun'], $creator);
        $arjun = (array) collect(app(MatchPlayerStatsService::class)->forMatch($b->fresh()))->firstWhere('playerId', 'HRNK1')['stats'];
        self::assertSame(3, $arjun['points']);
        self::assertSame(1, $arjun['three_pointers']);
        self::assertSame(1, $arjun['rebounds']);
    }

    // ---------------------------------------------------------------------- cricket

    public function test_a_cricket_chase_that_finishes_on_the_field_completes_and_builds_careers(): void
    {
        $creator = $this->user('HRNC0', 'Cricket');
        foreach (['HRNA1', 'HRNA2', 'HRNB1', 'HRNB2'] as $pid) {
            $this->user($pid, 'Cricket');
        }
        $m = LiveMatch::create([
            'title' => 'Ants vs Bees', 'home' => 'ANT', 'away' => 'BEE', 'home_full' => 'Ants', 'away_full' => 'Bees',
            'home_score' => 0, 'away_score' => 0, 'status' => 'Scheduled', 'sport' => 'cricket',
            'competition' => '2 overs', 'user_id' => $creator->id,
            'home_squad' => [['id' => 'HRNA1', 'name' => 'Asha'], ['id' => 'HRNA2', 'name' => 'Arun'], ['id' => null, 'name' => 'Rohit Sharma']],
            'away_squad' => [['id' => 'HRNB1', 'name' => 'Bala'], ['id' => 'HRNB2', 'name' => 'Bhanu'], ['id' => null, 'name' => 'Guest']],
        ]);
        $h = ['Authorization' => 'Bearer ' . $this->token($creator)];
        $act = fn (array $body) => $this->withHeaders($h)->postJson("/api/matches/{$m->id}/score-action", $body)->assertSuccessful();

        $act(['type' => 'start', 'innings' => 1, 'batting_team' => 1, 'striker_id' => 'HRNA1', 'non_striker_id' => 'HRNA2', 'bowler_id' => 'HRNB1']);
        $act(['type' => 'runs', 'value' => 6]);
        $act(['type' => 'runs', 'value' => 6]);
        $act(['type' => 'start', 'innings' => 2, 'batting_team' => 2, 'striker_id' => 'HRNB1', 'non_striker_id' => 'HRNB2', 'bowler_id' => 'HRNA1']);
        $act(['type' => 'runs', 'value' => 6]);
        $act(['type' => 'runs', 'value' => 6]);
        $act(['type' => 'runs', 'value' => 1]);

        $m->refresh();
        self::assertStringContainsString('won by', strtolower($m->status), 'the result line is kept');
        self::assertNotNull($m->completed_at, 'a result written on the field finishes the match');

        self::assertSame(13, (int) User::query()->where('player_id', 'HRNB1')->value('career_runs'));
        self::assertSame(12, PlayerCareerBatting::query()->where('player_id', 'HRNA1')->value('runs'));
        self::assertSame('win', PlayerMatchStat::query()->where('player_id', 'HRNB1')->value('result'));
        self::assertSame(0, PlayerMatchStat::query()->whereIn('player_name', ['Travis Head', 'Pat Cummins', 'Suryakumar Yadav'])->count(),
            'no invented IPL players, ever');
        self::assertSame(1, (int) PlayerSportCareer::query()->where('player_id', 'HRNB1')->where('sport', 'cricket')->value('wins'));
    }

    public function test_undoing_the_winning_ball_reopens_the_match_and_removes_it_from_careers(): void
    {
        $creator = $this->user('HRNC0', 'Cricket');
        $this->user('HRNB1', 'Cricket');
        $m = LiveMatch::create([
            'title' => 'U', 'home' => 'ANT', 'away' => 'BEE', 'home_full' => 'Ants', 'away_full' => 'Bees',
            'home_score' => 0, 'away_score' => 0, 'status' => 'Scheduled', 'sport' => 'cricket',
            'competition' => '1 over', 'user_id' => $creator->id,
            'home_squad' => [['id' => 'HRNA1', 'name' => 'Asha'], ['id' => 'HRNA2', 'name' => 'Arun']],
            'away_squad' => [['id' => 'HRNB1', 'name' => 'Bala'], ['id' => 'HRNB2', 'name' => 'Bhanu']],
        ]);
        $h = ['Authorization' => 'Bearer ' . $this->token($creator)];
        $act = fn (array $body) => $this->withHeaders($h)->postJson("/api/matches/{$m->id}/score-action", $body)->assertSuccessful();

        $act(['type' => 'start', 'innings' => 1, 'batting_team' => 1, 'striker_id' => 'HRNA1', 'non_striker_id' => 'HRNA2', 'bowler_id' => 'HRNB1']);
        $act(['type' => 'runs', 'value' => 2]);
        $act(['type' => 'start', 'innings' => 2, 'batting_team' => 2, 'striker_id' => 'HRNB1', 'non_striker_id' => 'HRNB2', 'bowler_id' => 'HRNA1']);
        $act(['type' => 'runs', 'value' => 4]);
        self::assertNotNull($m->fresh()->completed_at);
        self::assertSame(4, (int) User::query()->where('player_id', 'HRNB1')->value('career_runs'));

        $act(['type' => 'undo']);

        $m->refresh();
        self::assertNull($m->completed_at, 'the win was undone');
        self::assertSame(0, PlayerMatchStat::query()->where('match_id', $m->id)->count());
        self::assertSame(0, (int) User::query()->where('player_id', 'HRNB1')->value('career_runs'));
    }

    // ------------------------------------------------------------------- rankings

    public function test_rankings_are_written_in_bulk_and_only_where_they_move(): void
    {
        $a = $this->user('HRNR1', 'Cricket', ['career_runs' => 50]);
        $b = $this->user('HRNR2', 'Cricket', ['career_runs' => 80]);
        $c = $this->user('HRNR3', 'Cricket', ['career_runs' => 10, 'district' => 'Chittoor']);

        $service = app(LeaderboardRankService::class);
        self::assertGreaterThan(0, $service->recalculate());

        self::assertSame(1, (int) $b->fresh()->rank_country);
        self::assertSame(2, (int) $a->fresh()->rank_country);
        self::assertSame(1, (int) $c->fresh()->rank_district, 'Chittoor has one player');
        self::assertSame(0, $service->recalculate(), 'nothing moved, nothing written');
    }
}
