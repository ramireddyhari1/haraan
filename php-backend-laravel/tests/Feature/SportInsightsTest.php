<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LiveMatch;
use App\Models\MatchEvent;
use App\Models\MemberPlan;
use App\Models\MemberSubscription;
use App\Models\User;
use App\Services\MatchEventRecorder;
use App\Support\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Per-sport Insights. Every expectation below was counted by hand from the events the test
 * records — the contract is that each figure on a sport's Insights tab is a count of what
 * the scorer tapped, in that sport's own terms, and nothing a scorer cannot tap appears.
 */
class SportInsightsTest extends TestCase
{
    use RefreshDatabase;

    private MatchEventRecorder $recorder;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->recorder = app(MatchEventRecorder::class);
        $this->owner = User::create([
            'name' => 'Owner', 'email' => 'owner@haraan.test',
            'password' => Hash::make('secret123'), 'role' => 'user', 'status' => 'active',
            'player_id' => 'HRNOWNER', 'is_guest' => false,
        ]);
    }

    private function match(string $sport, array $format = [], array $extra = []): LiveMatch
    {
        return LiveMatch::create(array_merge([
            'title' => 'Insights test', 'home' => 'HHH', 'away' => 'KKJ',
            'home_score' => 0, 'away_score' => 0, 'status' => 'Live',
            'sport' => $sport, 'user_id' => $this->owner->id,
            'sport_state' => $format === [] ? null : ['format' => $format],
        ], $extra));
    }

    private function ev(LiveMatch $m, array $attrs): void
    {
        $this->recorder->record($m, $attrs, $this->owner);
    }

    private function point(LiveMatch $m, string $side, ?string $who = null, string $detail = ''): void
    {
        $this->ev($m, ['kind' => MatchEvent::POINT, 'side' => $side, 'player_name' => $who, 'detail' => $detail ?: null]);
    }

    /**
     * Read as the match owner on a plan with every sport's advanced insights — these tests are
     * about the figures, and the member gate in front of them has its own suite
     * (Membership\MemberInsightSportsTest).
     */
    private function insights(LiveMatch $m): array
    {
        MemberSubscription::query()->firstOrCreate(
            ['user_id' => $this->owner->id, 'provider' => MemberSubscription::PROVIDER_ADMIN],
            ['plan_id' => MemberPlan::query()->where('code', 'hero')->value('id'), 'status' => MemberSubscription::STATUS_ACTIVE],
        );
        $token = JwtService::issueForUser($this->owner, (string) config('app.jwt_secret', env('JWT_SECRET', 'change_me')));

        return $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/live-matches/{$m->id}/insights")->assertOk()->json();
    }

    private function card(array $data, string $name): array
    {
        foreach ($data['players'] as $p) {
            if ($p['name'] === $name) {
                return $p;
            }
        }
        self::fail("No card for {$name}");
    }

    private function tagKeys(array $card): array
    {
        return array_column($card['tags'], 'key');
    }

    public function test_football_reads_the_order_of_goals_not_just_the_count(): void
    {
        $m = $this->match('football');
        $goal = fn (string $side, int $min, string $who, ?string $assist = null) => $this->ev($m, [
            'kind' => MatchEvent::GOAL, 'side' => $side, 'minute' => $min, 'player_name' => $who, 'related_name' => $assist,
        ]);
        $goal('home', 12, 'Rahul', 'Kiran');   // 1-0
        $goal('away', 30, 'Imran');            // 1-1
        $goal('away', 50, 'Imran');            // 1-2
        $goal('home', 70, 'Rahul');            // 2-2
        $goal('home', 85, 'Sai', 'Rahul');     // 3-2
        $this->ev($m, ['kind' => MatchEvent::YELLOW, 'side' => 'away', 'minute' => 60, 'player_name' => 'Imran']);
        $m->refresh()->update(['status' => 'Completed']);

        $d = $this->insights($m);

        self::assertSame('football', $d['sport']);
        self::assertSame(2, $d['flow']['lead_changes']);
        self::assertSame('home', $d['flow']['comeback']['side']);
        self::assertSame(1, $d['flow']['comeback']['deficit']);

        $rahul = $this->card($d, 'Rahul');
        self::assertSame(2, $rahul['headline']);
        self::assertSame(1, $rahul['assists']);
        self::assertSame(67, $rahul['share']);   // 2 of HHH's 3
        self::assertEqualsCanonicalizing(['brace', 'opener', 'equaliser', 'goal_and_assist'], $this->tagKeys($rahul));

        // The winner is the goal that took HHH one clear of KKJ's final two — Sai's, not the last Rahul one.
        self::assertContains('winner', $this->tagKeys($this->card($d, 'Sai')));
        self::assertNotContains('winner', $this->tagKeys($rahul));
        self::assertContains('booked', $this->tagKeys($this->card($d, 'Imran')));
        self::assertSame('Rahul', $d['players'][0]['name']);

        self::assertSame(['opener', 'equaliser', 'go_ahead', 'equaliser', 'winner'], array_column($d['team']['story'], 'moment'));
        self::assertContains('Possession', $d['untracked']);
    }

    public function test_basketball_counts_the_shot_mix_and_never_credits_unnamed_buckets(): void
    {
        $m = $this->match('basketball');
        foreach (['3', '3', '3', '2'] as $v) {
            $this->point($m, 'home', 'Arjun', $v);   // 11 pts, 3 threes
        }
        $this->point($m, 'home', null, '2');          // unnamed bucket still counts for the team
        $this->point($m, 'away', 'Dev', '1');
        $this->ev($m, ['kind' => MatchEvent::PERIOD, 'note' => 'Q2']);
        $this->point($m, 'away', 'Dev', '2');

        $d = $this->insights($m);
        $arjun = $this->card($d, 'Arjun');

        self::assertSame(11, $arjun['headline']);
        self::assertSame(85, $arjun['share']);       // 11 of 13
        self::assertEqualsCanonicalizing(['top_scorer', 'double_figures', 'sniper', 'hot_hand'], $this->tagKeys($arjun));
        self::assertSame(['three' => 9, 'two' => 2, 'free_throw' => 0], $arjun['mix']);
        self::assertSame(3, $d['team']['shot_mix']['home']['threes']);
        self::assertSame(1, $d['team']['shot_mix']['away']['free_throws']);
        self::assertCount(2, $d['team']['quarters']);
        self::assertSame(['label' => 'Q2', 'home' => 0, 'away' => 2], array_intersect_key($d['team']['quarters'][1], ['label' => 1, 'home' => 1, 'away' => 1]));
        self::assertCount(2, $d['players']);
        self::assertContains('Rebounds', $d['untracked']);
    }

    public function test_kabaddi_honours_are_real_thresholds_and_all_outs_belong_to_the_team(): void
    {
        $m = $this->match('kabaddi');
        for ($i = 0; $i < 7; $i++) {
            $this->point($m, 'home', 'Pawan', 'raid');
        }
        $this->point($m, 'home', 'Pawan', 'super_raid');   // +3 → 10 raid points
        $this->point($m, 'home', 'Pawan', 'all_out');      // team's, not Pawan's
        for ($i = 0; $i < 5; $i++) {
            $this->point($m, 'away', 'Fazel', 'tackle');
        }

        $d = $this->insights($m);
        $pawan = $this->card($d, 'Pawan');
        $fazel = $this->card($d, 'Fazel');

        self::assertSame(10, $pawan['headline']);
        self::assertSame('Raider', $pawan['role']);
        self::assertEqualsCanonicalizing(['super_10', 'super_raid', 'top_raider'], $this->tagKeys($pawan));
        self::assertSame('Defender', $fazel['role']);
        self::assertContains('high_5', $this->tagKeys($fazel));
        self::assertSame(1, $d['team']['split']['home']['all_out']);
        self::assertSame(83, $pawan['share']);   // 10 of HHH's 12 (all out included in the team total)
    }

    public function test_rally_sports_find_set_points_saved_and_deuce_from_the_rules(): void
    {
        $m = $this->match('table_tennis', ['bestOf' => 3, 'pointsTo' => 3]);
        $this->point($m, 'home', 'Mo');    // 1-0
        $this->point($m, 'home', 'Mo');    // 2-0  HHH on set point
        $this->point($m, 'away', 'Ana');   // 2-1  saved
        $this->point($m, 'away', 'Ana');   // 2-2  saved again
        $this->point($m, 'home', 'Mo');    // 3-2  at deuce
        $this->point($m, 'home', 'Mo');    // 4-2  at deuce, closes Set 1

        $d = $this->insights($m);

        self::assertSame(2, $d['team']['set_points_saved']['away']);
        self::assertSame(0, $d['team']['set_points_saved']['home']);
        self::assertTrue($d['team']['sets'][0]['deuce']);
        self::assertSame('home', $d['team']['sets'][0]['winner']);
        self::assertSame([4, 2], [$d['team']['sets'][0]['home'], $d['team']['sets'][0]['away']]);

        $mo = $this->card($d, 'Mo');
        self::assertContains('closer', $this->tagKeys($mo));
        self::assertContains('clutch', $this->tagKeys($mo));
        self::assertContains('Saved 2 game points', array_column($this->card($d, 'Ana')['tags'], 'label'));
        self::assertSame(0, $d['flow']['lead_changes']);   // HHH led, was caught, never trailed
        self::assertSame(1, $d['flow']['ties']);
    }

    public function test_badminton_calls_its_sets_games(): void
    {
        $m = $this->match('badminton', ['bestOf' => 3, 'pointsTo' => 2]);
        $this->point($m, 'home', 'Sindhu');
        $this->point($m, 'home', 'Sindhu');

        $d = $this->insights($m);
        self::assertSame('Game', $d['team']['set_noun']);
        self::assertSame('Game 1', $d['team']['sets'][0]['label']);
    }

    /**
     * The serve chain. Nobody recorded who served first, and nobody had to: the rally winner
     * serves the next rally, so every rally after the first has a server that is known exactly.
     * The one rally that cannot be known is counted as unknown, not assigned to a side.
     */
    public function test_badminton_derives_who_served_every_rally_from_the_rally_winner(): void
    {
        $m = $this->match('badminton', ['bestOf' => 1, 'pointsTo' => 3]);
        $this->point($m, 'home', 'Sindhu');    // 1-0  server unknown
        $this->point($m, 'home', 'Sindhu');    // 2-0  HHH served (won the last), held
        $this->point($m, 'away', 'Momota');    // 2-1  HHH served, KKJ broke — and saved a game point
        $this->point($m, 'away', 'Momota');    // 2-2  KKJ served, held — saved another
        $this->point($m, 'home', 'Sindhu');    // 3-2  KKJ served, HHH broke, at deuce
        $this->point($m, 'home', 'Sindhu');    // 4-2  HHH served, held, closes Game 1

        $serve = $this->insights($m)['team']['serve'];

        self::assertTrue($serve['known']);
        self::assertFalse($serve['recorded']);          // derived, not told
        self::assertSame(5, $serve['rallies']);         // six rallies, one unknowable
        self::assertSame(1, $serve['unknown']);
        self::assertSame([3, 2, 67], [$serve['home']['played'], $serve['home']['won'], $serve['home']['pct']]);
        self::assertSame([2, 1, 50], [$serve['away']['played'], $serve['away']['won'], $serve['away']['pct']]);
        self::assertSame(1, $serve['home']['breaks']);
        self::assertSame(1, $serve['away']['breaks']);
    }

    public function test_badminton_reads_game_points_deuce_and_runs_from_the_rules(): void
    {
        $m = $this->match('badminton', ['bestOf' => 1, 'pointsTo' => 3]);
        $this->point($m, 'home', 'Sindhu');
        $this->point($m, 'home', 'Sindhu');
        $this->point($m, 'away', 'Momota');
        $this->point($m, 'away', 'Momota');
        $this->point($m, 'home', 'Sindhu');
        $this->point($m, 'home', 'Sindhu');

        $d = $this->insights($m);
        $gp = $d['team']['pressure']['game_points'];

        // HHH were one rally from the game three times and took the third.
        self::assertSame(['for' => 3, 'converted' => 1, 'faced' => 0, 'saved' => 0], $gp['home']);
        self::assertSame(['for' => 0, 'converted' => 0, 'faced' => 3, 'saved' => 2], $gp['away']);
        self::assertSame(2, $d['team']['set_points_saved']['away']);
        self::assertSame(['home' => 2, 'away' => 0], $d['team']['pressure']['deuce']);
        self::assertTrue($d['team']['sets'][0]['deuce']);
        self::assertSame('home', $d['team']['sets'][0]['winner']);
        self::assertSame([4, 2], [$d['team']['sets'][0]['home'], $d['team']['sets'][0]['away']]);

        // Three pairs of rallies — nothing reached three unanswered.
        self::assertSame(['home' => 0, 'away' => 0], $d['team']['pressure']['runs3']);
        self::assertSame(2, $d['team']['pressure']['longest']['count']);

        $sindhu = $this->card($d, 'Sindhu');
        $momota = $this->card($d, 'Momota');
        self::assertSame(2, $sindhu['on_serve']);       // won two of the rallies she served
        self::assertSame(1, $sindhu['game_points_won']);
        self::assertSame(2, $sindhu['deuce']);
        self::assertSame(3, $sindhu['decisive']);       // 1 game point won + 0 saved + 2 at deuce
        self::assertSame(2, $momota['game_points_saved']);
        self::assertSame(1, $momota['on_serve']);
        self::assertContains('closer', $this->tagKeys($sindhu));
        self::assertContains('Saved 2 game points', array_column($momota['tags'], 'label'));
    }

    /**
     * The 11-point interval is a rule, so the half either side of it is countable. A 21–5 game
     * that was 11–0 at the break is the case the split exists to describe.
     */
    public function test_badminton_splits_a_game_at_the_eleven_point_interval(): void
    {
        $m = $this->match('badminton', ['bestOf' => 1, 'pointsTo' => 21]);
        for ($i = 0; $i < 11; $i++) {
            $this->point($m, 'home', 'Sindhu');   // 11-0, the interval
        }
        for ($i = 0; $i < 5; $i++) {
            $this->point($m, 'away', 'Momota');   // 11-5
        }
        for ($i = 0; $i < 10; $i++) {
            $this->point($m, 'home', 'Sindhu');   // 21-5
        }

        $d = $this->insights($m);
        $p = $d['team']['pressure'];

        self::assertSame(11, $p['interval']);
        // Fifteen rallies were played after either side reached 11; HHH won ten of them.
        self::assertSame(['home' => 10, 'away' => 5], $p['after_interval']);
        self::assertSame([10, 5], [$d['team']['sets'][0]['interval_home'], $d['team']['sets'][0]['interval_away']]);
        self::assertTrue($d['team']['sets'][0]['reached_interval']);

        // Runs of three or more, and the rally that stopped each of them.
        self::assertSame(['home' => 2, 'away' => 1], $p['runs3']);
        self::assertSame(['home' => 1, 'away' => 1], $p['responses']);
        self::assertSame(11, $p['longest']['count']);
        // Three runs, in order, summing to every rally played.
        self::assertSame([['home', 11], ['away', 5], ['home', 10]], array_map(
            fn (array $r): array => [$r['side'], $r['count']],
            $p['runs'],
        ));
        self::assertSame(26, array_sum(array_column($p['runs'], 'count')));

        $serve = $d['team']['serve'];
        self::assertSame(25, $serve['rallies']);
        self::assertSame([20, 19, 95], [$serve['home']['played'], $serve['home']['won'], $serve['home']['pct']]);
        self::assertSame(10, $serve['home']['best_streak']);
        self::assertSame([5, 4, 80], [$serve['away']['played'], $serve['away']['won'], $serve['away']['pct']]);
        self::assertSame(10, $this->card($d, 'Sindhu')['after_interval']);
    }

    /**
     * Aces and forced errors are optional taps. Untagged, badminton says so; tagged, the
     * figures appear and the "not recorded" line stops claiming they are missing.
     */
    public function test_badminton_names_aces_as_untracked_until_a_scorer_taps_one(): void
    {
        $plain = $this->match('badminton', ['bestOf' => 1, 'pointsTo' => 2]);
        $this->point($plain, 'home', 'Sindhu');
        $this->point($plain, 'home', 'Sindhu');
        $untracked = $this->insights($plain)['untracked'];
        self::assertContains('Aces', $untracked);
        self::assertContains('Smashes', $untracked);
        self::assertFalse($this->insights($plain)['team']['serve']['detailed']);

        $tagged = $this->match('badminton', ['bestOf' => 1, 'pointsTo' => 2]);
        $this->point($tagged, 'home', 'Sindhu', 'ace');
        $this->point($tagged, 'home', 'Sindhu', 'error');
        $d = $this->insights($tagged);

        self::assertNotContains('Aces', $d['untracked']);
        self::assertContains('Smashes', $d['untracked']);
        self::assertTrue($d['team']['serve']['detailed']);
        self::assertSame(1, $d['team']['serve']['home']['aces']);
        self::assertSame(1, $d['team']['serve']['home']['errors_forced']);
        self::assertSame(1, $this->card($d, 'Sindhu')['aces']);
        self::assertContains('ace', $this->tagKeys($this->card($d, 'Sindhu')));
    }

    /** A recorded `serve` event is believed over the rule, and makes the first rally knowable. */
    public function test_badminton_believes_a_recorded_first_server(): void
    {
        $m = $this->match('badminton', ['bestOf' => 1, 'pointsTo' => 3]);
        $this->ev($m, ['kind' => MatchEvent::SERVE, 'side' => 'away']);
        $this->point($m, 'home', 'Sindhu');    // KKJ served, HHH broke
        $this->point($m, 'home', 'Sindhu');    // HHH served, held

        $serve = $this->insights($m)['team']['serve'];

        self::assertTrue($serve['recorded']);
        self::assertSame('away', $serve['first_server']);
        self::assertSame(0, $serve['unknown']);
        self::assertSame(2, $serve['rallies']);
        self::assertSame(1, $serve['home']['breaks']);
        self::assertSame([1, 1], [$serve['home']['played'], $serve['home']['won']]);
        self::assertSame([1, 0], [$serve['away']['played'], $serve['away']['won']]);
    }

    /** Badminton's own reading must not leak into the sports that still share RallyInsights. */
    public function test_volleyball_still_reads_as_a_rally_sport_without_a_serve_section(): void
    {
        $m = $this->match('volleyball', ['bestOf' => 1, 'pointsTo' => 2]);
        $this->point($m, 'home', 'Mo');
        $this->point($m, 'home', 'Mo');

        $team = $this->insights($m)['team'];
        self::assertSame('Set', $team['set_noun']);
        self::assertArrayNotHasKey('serve', $team);
        self::assertArrayNotHasKey('pressure', $team);
    }

    public function test_tennis_counts_games_and_names_serve_as_untracked(): void
    {
        $m = $this->match('tennis', ['bestOf' => 3, 'gamesTo' => 6]);
        for ($i = 0; $i < 4; $i++) {
            $this->point($m, 'home', 'Sumit');
        }
        foreach (['home', 'away', 'home', 'away', 'home', 'away', 'away', 'away'] as $side) {
            $this->point($m, $side, $side === 'home' ? 'Sumit' : 'Yuki');   // 3-3 deuce, then away wins
        }

        $d = $this->insights($m);
        self::assertSame(['home' => 1, 'away' => 1], $d['team']['games']);
        self::assertSame(1, $d['team']['deuce_games']);
        self::assertSame(1, $d['team']['deuce_games_won']['away']);
        self::assertContains('Aces', $d['untracked']);
        self::assertContains('Breaks of serve', $d['untracked']);
    }

    public function test_a_private_match_is_not_reachable_by_id(): void
    {
        $m = $this->match('football', [], ['is_private' => true]);

        $this->getJson("/api/live-matches/{$m->id}/insights")->assertNotFound();
    }

    public function test_a_match_with_no_events_returns_an_empty_but_valid_payload(): void
    {
        $m = $this->match('volleyball');

        $d = $this->insights($m);
        self::assertSame(0, $d['moments']);
        self::assertSame([], $d['players']);
        self::assertNull($d['flow']['longest_run']);
    }
}
