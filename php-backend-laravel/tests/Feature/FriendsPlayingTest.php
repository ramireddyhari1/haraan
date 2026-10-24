<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LiveMatch;
use App\Models\User;
use App\Support\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * "Your people are playing" on ActionBoard cards: friends by follow, contacts by hashed phone.
 */
class FriendsPlayingTest extends TestCase
{
    use RefreshDatabase;

    private function player(string $username, array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => ucfirst($username),
            'email' => $username.'@haraan.test',
            'password' => Hash::make('secret123'),
            'role' => 'user',
            'status' => 'active',
            'username' => $username,
            'player_id' => 'HRN'.strtoupper(substr(md5($username), 0, 7)),
            'is_guest' => false,
            'district' => 'YSR Kadapa',
        ], $overrides));
    }

    private function token(User $u): string
    {
        return JwtService::issueForUser($u, (string) (config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me')));
    }

    private function match(User $creator, array $home, array $away): LiveMatch
    {
        return LiveMatch::create([
            'title' => 'A v B', 'home' => 'ANT', 'away' => 'BEE', 'home_full' => 'Ants', 'away_full' => 'Bees',
            'home_score' => 0, 'away_score' => 0, 'status' => 'Live', 'sport' => 'cricket',
            'competition' => '5 overs', 'user_id' => $creator->id, 'district' => 'YSR Kadapa',
            'home_squad' => $home, 'away_squad' => $away,
        ]);
    }

    private function row(array $json, LiveMatch $m): array
    {
        return collect($json['data'])->firstWhere('id', (string) $m->id);
    }

    public function test_feed_names_the_friends_playing_and_lists_linked_players(): void
    {
        $me = $this->player('rohit');
        $friend = $this->player('virat');
        $fan = $this->player('shubman');
        $stranger = $this->player('jadeja');
        $me->follow($friend);
        $fan->follow($me);

        $m = $this->match($stranger,
            [['id' => $friend->player_id, 'name' => 'Virat'], ['id' => null, 'name' => 'Typed In']],
            [['id' => $fan->player_id, 'name' => 'Shubman'], ['id' => $stranger->player_id, 'name' => 'Jaddu']],
        );

        $row = $this->row($this->withHeader('Authorization', 'Bearer '.$this->token($me))
            ->getJson('/api/live-matches')->assertOk()->json(), $m);

        $this->assertSame(2, $row['friendsCount']);
        // The person you chose to follow leads.
        $this->assertSame($friend->player_id, $row['friends'][0]['playerId']);
        $this->assertSame('following', $row['friends'][0]['relation']);
        $this->assertSame('follows_you', $row['friends'][1]['relation']);
        // Every linked account, for the on-device contacts match — never the typed-in name.
        $this->assertEqualsCanonicalizing(
            [$friend->player_id, $fan->player_id, $stranger->player_id],
            $row['playerIds'],
        );
    }

    public function test_guests_and_blocked_players_get_no_friends(): void
    {
        $me = $this->player('rohit');
        $friend = $this->player('virat');
        $me->follow($friend);
        $m = $this->match($friend, [['id' => $friend->player_id, 'name' => 'Virat']], []);

        $guestRow = $this->row($this->getJson('/api/live-matches')->assertOk()->json(), $m);
        $this->assertSame([], $guestRow['friends']);
        $this->assertSame([], $guestRow['playerIds']);

        DB::table('player_blocks')->insert(['blocker_id' => $friend->id, 'blocked_id' => $me->id]);
        $row = $this->row($this->withHeader('Authorization', 'Bearer '.$this->token($me))
            ->getJson('/api/live-matches')->assertOk()->json(), $m);
        $this->assertSame([], $row['friends']);
        $this->assertSame([], $row['playerIds']);
    }

    public function test_contacts_match_by_hashed_last_ten_digits_and_skips_self(): void
    {
        $me = $this->player('rohit', ['phone' => '+91 98765 43210']);
        $pal = $this->player('virat', ['phone' => '+919000011111']);
        $this->player('nobody', ['phone' => '+919000022222', 'is_guest' => true]);

        $hash = fn (string $n) => hash('sha256', substr(preg_replace('/\D+/', '', $n), -10));

        $res = $this->withHeader('Authorization', 'Bearer '.$this->token($me))
            ->postJson('/api/players/contacts/match', ['hashes' => [
                $hash('09000011111'),     // the same number as saved with a trunk 0
                $hash('9876543210'),      // my own number
                $hash('9000022222'),      // a guest
                $hash('9123456789'),      // not on Haraan
            ]])->assertOk()->json('data');

        $this->assertCount(1, $res);
        $this->assertSame($pal->player_id, $res[0]['playerId']);
        $this->assertSame($hash('9000011111'), $res[0]['hash']);
    }

    public function test_contacts_match_needs_sign_in_and_real_hashes(): void
    {
        $this->postJson('/api/players/contacts/match', ['hashes' => [str_repeat('a', 64)]])->assertStatus(401);

        $me = $this->player('rohit');
        $this->withHeader('Authorization', 'Bearer '.$this->token($me))
            ->postJson('/api/players/contacts/match', ['hashes' => ['9876543210']])
            ->assertStatus(422);
    }
}
