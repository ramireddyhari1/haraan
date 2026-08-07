<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The ActionBoard social graph: find a player by handle, follow them, see it stick.
 */
class PlayerFollowTest extends TestCase
{
    use RefreshDatabase;

    private function player(string $username, array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => ucfirst($username),
            'email' => $username . '@haraan.test',
            'password' => Hash::make('secret123'),
            'role' => 'user',
            'status' => 'active',
            'username' => $username,
            'player_id' => 'HRN' . strtoupper(substr(md5($username), 0, 7)),
            'is_guest' => false,
            'district' => 'YSR Kadapa',
            'ranked_xp' => 500,
            'career_matches' => 12,
        ], $overrides));
    }

    public function test_following_is_one_directional_and_idempotent(): void
    {
        $me = $this->player('rohit');
        $them = $this->player('virat');

        $this->assertTrue($me->follow($them));
        $this->assertTrue($me->follow($them), 'Following twice must be a no-op, not a duplicate.');

        $this->assertSame(1, $me->following()->count());
        $this->assertSame(1, $them->followers()->count());
        $this->assertTrue($me->isFollowing($them));

        // One-directional: they do not follow back automatically.
        $this->assertFalse($them->isFollowing($me));
        $this->assertSame(0, $them->following()->count());
    }

    public function test_you_cannot_follow_yourself_or_a_guest(): void
    {
        $me = $this->player('rohit');
        $guest = $this->player('ghost', ['is_guest' => true]);

        $this->assertFalse($me->follow($me));
        $this->assertFalse($me->follow($guest));
        $this->assertSame(0, $me->following()->count());
    }

    public function test_unfollowing_removes_the_edge(): void
    {
        $me = $this->player('rohit');
        $them = $this->player('virat');

        $me->follow($them);
        $me->unfollow($them);

        $this->assertFalse($me->isFollowing($them));
        $this->assertSame(0, $them->followers()->count());
    }

    public function test_deleting_a_player_removes_their_edges(): void
    {
        $me = $this->player('rohit');
        $them = $this->player('virat');
        $me->follow($them);

        $them->delete();

        $this->assertSame(0, $me->following()->count());
        $this->assertDatabaseCount('player_follows', 0);
    }

    // ------------------------------------------------------------------- API

    /** Same payload shape EnsureJwtAuthenticated decodes — `sub` is the user id. */
    private function token(User $user): string
    {
        return \App\Support\JwtService::issue(
            ['sub' => $user->id],
            (string) config('app.jwt_secret', env('JWT_SECRET', 'change_me')),
        );
    }

    public function test_search_reports_whether_the_viewer_already_follows_each_result(): void
    {
        $me = $this->player('rohit');
        $virat = $this->player('virat');
        $this->player('viraaj');

        $me->follow($virat);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token($me))
            ->getJson('/api/players/find?q=vir')
            ->assertOk();

        $results = collect($response->json('results'));

        $this->assertGreaterThanOrEqual(2, $results->count());
        $this->assertTrue($results->firstWhere('username', 'virat')['is_following']);
        $this->assertFalse($results->firstWhere('username', 'viraaj')['is_following']);

        // Social signal on the card, so a result never reads as a bare name.
        $this->assertSame(12, $results->first()['matches']);
        $this->assertArrayHasKey('xp', $results->first());
    }

    public function test_search_never_returns_the_viewer(): void
    {
        $me = $this->player('virat');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token($me))
            ->getJson('/api/players/find?q=virat')
            ->assertOk();

        $this->assertEmpty($response->json('results'));
    }

    public function test_follow_endpoint_returns_the_settled_state(): void
    {
        $me = $this->player('rohit');
        $them = $this->player('virat');

        $this->withHeader('Authorization', 'Bearer ' . $this->token($me))
            ->postJson('/api/players/' . $them->player_id . '/follow')
            ->assertOk()
            ->assertJson(['is_following' => true, 'followers_count' => 1]);

        // Idempotent over HTTP too — a double tap settles, it does not 500.
        $this->withHeader('Authorization', 'Bearer ' . $this->token($me))
            ->postJson('/api/players/' . $them->player_id . '/follow')
            ->assertOk()
            ->assertJson(['is_following' => true, 'followers_count' => 1]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token($me))
            ->postJson('/api/players/' . $them->player_id . '/unfollow')
            ->assertOk()
            ->assertJson(['is_following' => false, 'followers_count' => 0]);
    }

    public function test_a_player_can_be_followed_by_handle_as_well_as_id(): void
    {
        $me = $this->player('rohit');
        $them = $this->player('virat');

        $this->withHeader('Authorization', 'Bearer ' . $this->token($me))
            ->postJson('/api/players/@virat/follow')
            ->assertOk()
            ->assertJson(['is_following' => true]);

        $this->assertTrue($me->fresh()->isFollowing($them));
    }

    public function test_following_yourself_is_refused_with_a_reason(): void
    {
        $me = $this->player('rohit');

        $this->withHeader('Authorization', 'Bearer ' . $this->token($me))
            ->postJson('/api/players/' . $me->player_id . '/follow')
            ->assertStatus(422)
            ->assertJsonPath('error', 'You cannot follow yourself');
    }

    public function test_an_unknown_player_is_a_404_not_a_crash(): void
    {
        $me = $this->player('rohit');

        $this->withHeader('Authorization', 'Bearer ' . $this->token($me))
            ->postJson('/api/players/HRNNOPE/follow')
            ->assertStatus(404);
    }

    public function test_follow_requires_auth(): void
    {
        $them = $this->player('virat');

        $this->postJson('/api/players/' . $them->player_id . '/follow')->assertStatus(401);
    }

    public function test_the_follower_and_following_lists_read_back(): void
    {
        $me = $this->player('rohit');
        $a = $this->player('virat');
        $b = $this->player('bumrah');

        $me->follow($a);
        $me->follow($b);
        $a->follow($me);

        $token = $this->token($me);

        $following = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/players/' . $me->player_id . '/following')->assertOk();
        $this->assertCount(2, $following->json('results'));

        $followers = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/players/' . $me->player_id . '/followers')->assertOk();
        $this->assertCount(1, $followers->json('results'));
        $this->assertSame('virat', $followers->json('results.0.username'));

        // The viewer's own follow state is resolved for list rows too.
        $this->assertTrue($followers->json('results.0.is_following'));
    }

    public function test_players_who_opted_out_of_discovery_stay_unsearchable(): void
    {
        $me = $this->player('rohit');
        $this->player('hidden', ['privacy_discoverable' => false]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token($me))
            ->getJson('/api/players/find?q=hidden')->assertOk();

        $this->assertEmpty($response->json('results'));
    }
}
