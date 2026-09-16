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
        \App\Models\MemberSubscription::query()->firstOrCreate(
            ['user_id' => $this->owner->id, 'provider' => \App\Models\MemberSubscription::PROVIDER_ADMIN],
            ['plan_id' => \App\Models\MemberPlan::query()->where('code', 'hero')->value('id'), 'status' => \App\Models\MemberSubscription::STATUS_ACTIVE],
        );
        $token = \App\Support\JwtService::issueForUser($this->owner, (string) config('app.jwt_secret', env('JWT_SECRET', 'change_me')));

        return $this->withHeader('Authorization', 'Bearer ' . $token)
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
