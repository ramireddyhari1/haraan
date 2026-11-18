<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\JwtService;
use App\Support\PlatformRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Membership\MembershipFixtures;
use Tests\TestCase;

/**
 * "Players to follow" cards on the ActionBoard Live tab and Home feed: /control's rules decide
 * who shows and in what order, and every card carries real runs, wickets and follow state.
 */
class PlayerSpotlightTest extends TestCase
{
    use MembershipFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureRazorpay();
        // Photos are checked on disk now, so each test player's photo has to really exist.
        \Illuminate\Support\Facades\Storage::fake('public');
    }

    private function player(string $username, int $runs = 0, int $wickets = 0, array $overrides = []): User
    {
        $u = User::create(array_merge([
            'name' => ucfirst($username),
            'email' => $username.'@haraan.test',
            'password' => Hash::make('secret123'),
            'role' => 'user',
            'status' => 'active',
            'username' => $username,
            'player_id' => 'HRN'.strtoupper(substr(md5($username), 0, 7)),
            'is_guest' => false,
            'avatar' => '/storage/avatars/'.$username.'.jpg',
        ], $overrides));
        $u->forceFill(['career_runs' => $runs, 'career_wickets' => $wickets, 'career_matches' => 5])->save();
        if (is_string($u->avatar) && str_starts_with($u->avatar, '/storage/')) {
            \Illuminate\Support\Facades\Storage::disk('public')->put(substr($u->avatar, strlen('/storage/')), 'jpg');
        }

        return $u;
    }

    private function token(User $u): string
    {
        return JwtService::issueForUser($u, (string) (config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me')));
    }

    private function ids($response): array
    {
        return array_column($response->json('players'), 'username');
    }

    public function test_orders_by_runs_plus_wickets_and_carries_stats(): void
    {
        $this->player('batter', runs: 900);
        $this->player('bowler', runs: 100, wickets: 50); // 100 + 50×20 = 1100
        $this->player('rookie', runs: 10);

        $res = $this->getJson('/api/players/spotlight')->assertOk();

        $this->assertSame(['bowler', 'batter', 'rookie'], $this->ids($res));
        $this->assertSame(100, $res->json('players.0.runs'));
        $this->assertSame(50, $res->json('players.0.wickets'));
        $this->assertNull($res->json('players.0.is_following'));
        $this->assertSame('Players to follow', $res->json('title'));
    }

    public function test_control_rules_pin_filter_and_switch_off(): void
    {
        $this->player('star', runs: 5000);
        $this->player('nophoto', runs: 4000, overrides: ['avatar' => null]);
        $this->player('hidden', runs: 3000, overrides: ['privacy_discoverable' => false]);
        $pinned = $this->player('pinned', runs: 1);

        PlatformRules::save([
            'player_cards.pinned' => '@pinned',
            'player_cards.title' => 'Top players',
            'player_cards.home_enabled' => false,
        ]);

        $res = $this->getJson('/api/players/spotlight')->assertOk();

        $this->assertSame(['pinned', 'star'], $this->ids($res));
        $this->assertSame('Top players', $res->json('title'));
        $this->assertFalse($res->json('home_enabled'));
        $this->assertTrue($res->json('live_enabled'));

        PlatformRules::save(['player_cards.photo_only' => false]);
        $this->assertSame(['pinned', 'star', 'nophoto'], $this->ids($this->getJson('/api/players/spotlight')));
    }

    /**
     * A photo path whose file is gone (deleted, never synced) is not a photo: the player drops
     * out of a photo-only row, and when they still show their card carries no avatar, so the
     * app draws its default picture instead of a broken tile.
     */
    public function test_a_photo_whose_file_is_missing_counts_as_no_photo(): void
    {
        $this->player('real', runs: 50);
        $ghost = $this->player('ghost', runs: 900);
        \Illuminate\Support\Facades\Storage::disk('public')->delete('avatars/ghost.jpg');

        $this->assertSame(['real'], $this->ids($this->getJson('/api/players/spotlight')));

        PlatformRules::save(['player_cards.photo_only' => false]);
        $res = $this->getJson('/api/players/spotlight')->assertOk();
        $this->assertSame(['ghost', 'real'], $this->ids($res));
        $this->assertNull($res->json('players.0.avatar'));
        $this->assertSame('/storage/avatars/real.jpg', $res->json('players.1.avatar'));
        $this->assertSame($ghost->player_id, $res->json('players.0.player_id'));
    }

    /** The two numbers under the name speak the player's own sport. */
    public function test_card_stats_follow_the_players_sport(): void
    {
        PlatformRules::save(['player_cards.photo_only' => false, 'player_cards.min_matches' => 0, 'player_cards.order' => 'matches']);
        $this->player('bat', runs: 120, wickets: 3, overrides: ['primary_sport' => 'Cricket']);
        $striker = $this->player('striker', overrides: ['primary_sport' => 'Football']);
        $shuttler = $this->player('shuttler', overrides: ['primary_sport' => 'Badminton']);
        \App\Models\PlayerSportCareer::create(['player_id' => $striker->player_id, 'sport' => 'football', 'matches' => 4, 'wins' => 2, 'losses' => 2, 'draws' => 0, 'totals' => ['goals' => 5, 'assists' => 2], 'bests' => []]);
        \App\Models\PlayerSportCareer::create(['player_id' => $shuttler->player_id, 'sport' => 'badminton', 'matches' => 3, 'wins' => 1, 'losses' => 2, 'draws' => 0, 'totals' => [], 'bests' => []]);

        $byName = collect($this->getJson('/api/players/spotlight')->assertOk()->json('players'))->keyBy('username');

        $this->assertSame([['label' => 'R', 'value' => 120], ['label' => 'W', 'value' => 3]], $byName['bat']['stats']);
        $this->assertSame([['label' => 'G', 'value' => 5], ['label' => 'A', 'value' => 2]], $byName['striker']['stats']);
        $this->assertSame([['label' => 'Won', 'value' => 1], ['label' => 'Lost', 'value' => 2]], $byName['shuttler']['stats']);
    }

    public function test_viewer_never_sees_self_or_players_already_followed(): void
    {
        $me = $this->player('me', runs: 9999);
        $friend = $this->player('friend', runs: 500);
        $this->player('stranger', runs: 400);
        $me->follow($friend);

        $res = $this->withHeader('Authorization', 'Bearer '.$this->token($me))
            ->getJson('/api/players/spotlight')->assertOk();

        $this->assertSame(['stranger'], $this->ids($res));
        $this->assertFalse($res->json('players.0.is_following'));
    }

    public function test_pro_and_hero_members_carry_their_badge(): void
    {
        $pro = $this->player('pro', runs: 300);
        $hero = $this->player('hero', runs: 200);
        $this->player('free', runs: 100);
        $this->paidSubscription($pro, 'pro');
        $this->paidSubscription($hero, 'hero');
        app(\App\Services\Membership\MemberEntitlements::class)->forget();

        $badges = array_column($this->getJson('/api/players/spotlight')->assertOk()->json('players'), 'member_badge', 'username');

        $this->assertSame(['pro' => 'pro', 'hero' => 'hero', 'free' => null], $badges);
    }
}
