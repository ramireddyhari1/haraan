<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LiveMatch;
use App\Models\User;
use App\Support\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A private match's share code is its invitation to PLAY: whoever holds it can put
 * themselves into a squad without the owner approving a request.
 */
final class JoinPrivateMatchByCodeTest extends TestCase
{
    use RefreshDatabase;

    private function player(string $name, string $pid): User
    {
        return User::create([
            'name' => $name,
            'email' => strtolower($pid) . '@haraan.test',
            'password' => Hash::make('secret-password'),
            'role' => 'USER',
            'status' => 'ACTIVE',
            'is_guest' => false,
            'player_id' => $pid,
            'state' => 'Andhra Pradesh',
            'district' => 'YSR Kadapa',
            'primary_sport' => 'Football',
            'sport_attributes' => ['position' => 'Forward', 'foot' => 'Right'],
        ]);
    }

    private function auth(User $user): array
    {
        $secret = (string) (config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me'));

        return ['Authorization' => 'Bearer ' . JwtService::issueForUser($user->fresh(), $secret)];
    }

    private function match(User $owner, array $attrs = []): LiveMatch
    {
        return LiveMatch::create(array_merge([
            'title' => 'Friendly', 'home' => 'HHH', 'away' => 'KKJ',
            'home_score' => 0, 'away_score' => 0, 'status' => 'Scheduled',
            'sport' => 'football', 'user_id' => $owner->id,
            'is_private' => true, 'join_code' => 'HRN-7K2Q',
            'home_squad' => [['id' => null, 'name' => 'Ravi']],
            'away_squad' => [],
        ], $attrs));
    }

    public function test_preview_shows_both_sides_and_whether_you_are_in(): void
    {
        $owner = $this->player('Owner', 'OWN001');
        $me = $this->player('Sai', 'SAI001');
        $m = $this->match($owner);

        $this->withHeaders($this->auth($me))->getJson('/api/matches/join-by-code/hrn-7k2q')
            ->assertOk()
            ->assertJsonPath('data.matchId', (string) $m->id)
            ->assertJsonPath('data.home', 'HHH')
            ->assertJsonPath('data.homeCount', 1)
            ->assertJsonPath('data.mySide', null);
    }

    public function test_joining_adds_you_to_the_side_you_pick_and_opens_the_match_to_you(): void
    {
        $owner = $this->player('Owner', 'OWN001');
        $me = $this->player('Sai', 'SAI001');
        $m = $this->match($owner);

        $this->withHeaders($this->auth($me))
            ->postJson('/api/matches/join-by-code', ['code' => 'HRN-7K2Q', 'side' => 'away'])
            ->assertStatus(201)
            ->assertJsonPath('data.mySide', 'away');

        $m->refresh();
        $this->assertSame([['id' => 'SAI001', 'name' => 'Sai']], $m->away_squad);
        $this->assertTrue($m->isVisibleTo($me->fresh()));

        // Idempotent: a second tap never adds a second copy.
        $this->withHeaders($this->auth($me))
            ->postJson('/api/matches/join-by-code', ['code' => 'HRN-7K2Q', 'side' => 'home'])
            ->assertOk()
            ->assertJsonPath('data.mySide', 'away');
        $this->assertCount(1, $m->fresh()->away_squad);
        $this->assertCount(1, $m->fresh()->home_squad);
    }

    public function test_joining_claims_the_guest_slot_the_scorer_already_typed(): void
    {
        $owner = $this->player('Owner', 'OWN001');
        $ravi = $this->player('ravi', 'RAV001');
        $m = $this->match($owner);

        $this->withHeaders($this->auth($ravi))
            ->postJson('/api/matches/join-by-code', ['code' => 'HRN-7K2Q', 'side' => 'home'])
            ->assertStatus(201);

        $this->assertSame([['id' => 'RAV001', 'name' => 'Ravi']], $m->fresh()->home_squad);
    }

    public function test_wrong_code_finished_match_and_own_match_are_refused(): void
    {
        $owner = $this->player('Owner', 'OWN001');
        $me = $this->player('Sai', 'SAI001');
        $this->match($owner);
        $this->match($owner, ['join_code' => 'HRN-DONE', 'completed_at' => now()]);

        $this->withHeaders($this->auth($me))
            ->postJson('/api/matches/join-by-code', ['code' => 'HRN-ZZZZ', 'side' => 'home'])
            ->assertNotFound();
        $this->withHeaders($this->auth($me))
            ->postJson('/api/matches/join-by-code', ['code' => 'HRN-DONE', 'side' => 'home'])
            ->assertStatus(422);
        $this->withHeaders($this->auth($owner))
            ->postJson('/api/matches/join-by-code', ['code' => 'HRN-7K2Q', 'side' => 'home'])
            ->assertStatus(422);
    }

    public function test_a_public_match_cannot_be_joined_by_code(): void
    {
        $owner = $this->player('Owner', 'OWN001');
        $me = $this->player('Sai', 'SAI001');
        $this->match($owner, ['is_private' => false, 'join_code' => 'HRN-PUBL']);

        $this->withHeaders($this->auth($me))
            ->postJson('/api/matches/join-by-code', ['code' => 'HRN-PUBL', 'side' => 'home'])
            ->assertNotFound();
    }
}
