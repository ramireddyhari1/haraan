<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Ad;
use App\Models\LiveMatch;
use App\Models\User;
use App\Services\MatchEventRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Web parity: every sport's match renders its own page — never cricket's overs and last ball
 * with blank numbers — across all four tabs.
 */
final class SportMatchPageTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::create([
            'name' => 'Owner', 'email' => 'web@haraan.test', 'password' => Hash::make('secret123'),
            'role' => 'user', 'status' => 'active', 'player_id' => 'HRNWEB', 'is_guest' => false,
        ]);
    }

    private function match(string $sport, array $events): LiveMatch
    {
        $m = LiveMatch::create([
            'title' => 'T', 'home' => 'HOM', 'away' => 'AWY', 'home_full' => 'Home Club', 'away_full' => 'Away Club',
            'home_score' => 0, 'away_score' => 0, 'status' => 'Live', 'sport' => $sport, 'user_id' => $this->owner->id,
            'home_squad' => [['id' => 'HRNWEB', 'name' => 'Webster']], 'away_squad' => [['id' => null, 'name' => 'Guesty']],
        ]);
        foreach ($events as $e) {
            app(MatchEventRecorder::class)->record($m, $e, $this->owner);
        }

        return $m->fresh();
    }

    /** @return array<string, array{0: string, 1: array<int, array<string, mixed>>, 2: string}> */
    public static function sports(): array
    {
        $point = fn (string $side, ?string $who = null, ?string $detail = null) => ['kind' => 'point', 'side' => $side, 'player_name' => $who, 'detail' => $detail];

        return [
            'football' => ['football', [['kind' => 'goal', 'side' => 'home', 'player_name' => 'Webster', 'minute' => 9]], 'Goals'],
            'basketball' => ['basketball', [$point('home', 'Webster', '3')], 'Points'],
            'kabaddi' => ['kabaddi', [$point('home', 'Webster', 'raid')], 'Points'],
            'volleyball' => ['volleyball', [$point('home', 'Webster')], 'Sets'],
            'tennis' => ['tennis', [$point('home', 'Webster', 'ace')], 'Sets'],
            'table_tennis' => ['table_tennis', [$point('away', 'Guesty')], 'Games'],
            'badminton' => ['badminton', [$point('home')], 'Games'],
        ];
    }

    #[DataProvider('sports')]
    public function test_every_sport_renders_its_own_page_on_every_tab(string $sport, array $events, string $unit): void
    {
        $m = $this->match($sport, $events);
        $base = "/gamehub/actionboard/match/{$m->id}";

        $summary = $this->get($base)->assertOk()->assertSee('Home Club')->assertSee($unit)->assertSee('Summary');
        self::assertStringNotContainsString('LAST BALL', $summary->getContent(), 'never the cricket hero');

        $this->get("{$base}?tab=timeline")->assertOk();
        $this->get("{$base}?tab=players")->assertOk()->assertSee('Webster');
        // A guest reaches the tab and is told insights are a member feature — the page still
        // renders; the figures don't.
        $this->get("{$base}?tab=insights")->assertOk()->assertSee('Sign in to see insights for this match.');
    }

    public function test_cricket_tab_urls_redirect_to_the_sport_page(): void
    {
        $m = $this->match('football', []);

        $this->get("/gamehub/actionboard/match/{$m->id}/scorecard")
            ->assertRedirect(route('site.gamehub.actionboard.match', ['id' => $m->id, 'tab' => 'players']));
        $this->get("/gamehub/actionboard/match/{$m->id}/commentary")
            ->assertRedirect(route('site.gamehub.actionboard.match', ['id' => $m->id, 'tab' => 'timeline']));
    }

    public function test_a_cricket_match_keeps_its_cricket_page(): void
    {
        $m = LiveMatch::create([
            'title' => 'C', 'home' => 'ANT', 'away' => 'BEE', 'home_score' => 0, 'away_score' => 0,
            'status' => 'Live', 'sport' => 'cricket', 'user_id' => $this->owner->id,
        ]);

        $this->get("/gamehub/actionboard/match/{$m->id}")->assertOk()->assertSee('LAST BALL');
    }

    public function test_the_live_board_ad_shows_and_counts_an_impression_on_the_web(): void
    {
        $ad = Ad::create([
            'sponsor' => 'Gully Gear', 'title' => 'Bats 20% off', 'cta_text' => 'Shop',
            'cta_url' => 'https://example.com', 'placement' => 'match_live', 'is_active' => true, 'sort_order' => 0,
        ]);
        $m = $this->match('basketball', []);

        $this->get("/gamehub/actionboard/match/{$m->id}")->assertOk()->assertSee('Bats 20% off')
            ->assertSee(route('site.ad.click', $ad->id), false);
        self::assertSame(1, $ad->fresh()->impressions_count);
    }
}
