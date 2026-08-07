<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LiveMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Per-sport match format at creation.
 *
 * The contract these tests defend: **a match is created in its own sport's terms.**
 * Before this, the create wizard only ever asked cricket's question (overs), so a
 * football match was persisted carrying "20 overs / tennis ball", titled itself a
 * "20 Over Match", and its scorer opened on a hardcoded 45-minute half that no
 * gully game plays. Badminton singles could not be created at all — the
 * players-per-side floor was 2.
 */
class CreateMatchFormatTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Creating a match sits behind `actionboard.profile`, which demands a COMPLETE
     * profile — including the attributes [User::SPORT_REQUIRED_ATTRS] lists for that
     * player's primary sport. A wrong-sport attribute bag is a 403, not a 422.
     */
    private function player(string $sport = 'Cricket'): User
    {
        $attributes = match ($sport) {
            'Football' => ['position' => 'Midfielder', 'foot' => 'Right'],
            'Badminton' => ['format' => 'Singles', 'hand' => 'Right'],
            default => ['role' => 'All-rounder', 'batting' => 'Right', 'bowling' => 'Right-arm medium'],
        };

        return User::create([
            'name' => 'Creator', 'email' => 'creator@haraan.test',
            'password' => Hash::make('secret123'), 'role' => 'user', 'status' => 'active',
            'player_id' => 'HRNCREATE', 'is_guest' => false,
            'state' => 'Andhra Pradesh', 'district' => 'YSR Kadapa',
            'primary_sport' => $sport,
            'sport_attributes' => $attributes,
        ]);
    }

    private function token(User $user): string
    {
        return \App\Support\JwtService::issue(
            ['sub' => $user->id],
            (string) config('app.jwt_secret', env('JWT_SECRET', 'change_me')),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'matchType' => 'casual',
            'playersPerSide' => 11,
            'teamA' => 'Keerthipalle XI',
            'teamB' => 'Pulivendula XI',
            'venue' => 'Village ground',
            'locality' => 'Keerthipalle',
            'latitude' => 14.42,
            'longitude' => 78.22,
        ], $overrides);
    }

    private function create(User $user, array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token($user))
            ->postJson('/api/matches', $payload);
    }

    public function test_a_football_match_stores_its_halves_and_never_carries_overs(): void
    {
        $user = $this->player('Football');

        $response = $this->create($user, $this->payload([
            'sport' => 'football',
            'playersPerSide' => 7,
            'format' => ['kind' => 'football', 'halves' => 2, 'halfLengthMin' => 25],
        ]));

        $response->assertCreated();

        $match = LiveMatch::query()->latest('id')->firstOrFail();

        self::assertSame('football', $match->sport);
        self::assertSame(['kind' => 'football', 'halves' => 2, 'halfLengthMin' => 25], $match->sport_state['format']);
        // The line the card and header read. Was unconditionally "{overs} Over Match".
        self::assertSame('2 x 25 min', $match->competition);
    }

    public function test_a_badminton_singles_match_can_be_created_with_one_player_a_side(): void
    {
        $user = $this->player('Badminton');

        $response = $this->create($user, $this->payload([
            'sport' => 'badminton',
            // The old floor of min:2 made this exact request a 422 — singles was
            // literally inexpressible.
            'playersPerSide' => 1,
            'teamA' => 'Hari',
            'teamB' => 'Imran',
            'format' => ['kind' => 'badminton', 'bestOf' => 3, 'pointsTo' => 21, 'doubles' => false],
        ]));

        $response->assertCreated();

        $match = LiveMatch::query()->latest('id')->firstOrFail();

        self::assertSame('badminton', $match->sport);
        self::assertSame(3, $match->sport_state['format']['bestOf']);
        self::assertFalse($match->sport_state['format']['doubles']);
        self::assertSame('Singles - best of 3 to 21', $match->competition);
    }

    public function test_a_cricket_match_still_requires_overs_and_reads_as_an_over_match(): void
    {
        $user = $this->player('Cricket');

        $this->create($user, $this->payload([
            'sport' => 'cricket',
            'overs' => 6,
            'ball' => 'tennis',
            'format' => ['kind' => 'cricket', 'overs' => 6, 'ball' => 'tennis'],
        ]))->assertCreated();

        $match = LiveMatch::query()->latest('id')->firstOrFail();

        self::assertSame('6 Over Match', $match->competition);
        self::assertSame(6, $match->sport_state['format']['overs']);
    }

    public function test_cricket_without_overs_is_rejected(): void
    {
        $user = $this->player('Cricket');

        $this->create($user, $this->payload(['sport' => 'cricket']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('overs');
    }

    /**
     * Older app builds send no `format` at all. They must keep working — and keep
     * behaving exactly as they did — rather than 422 on an upgraded server.
     */
    public function test_a_legacy_client_sending_only_overs_still_creates_a_cricket_match(): void
    {
        $user = $this->player('Cricket');

        $this->create($user, $this->payload([
            'sport' => 'cricket',
            'overs' => 20,
            'ball' => 'tennis',
        ]))->assertCreated();

        $match = LiveMatch::query()->latest('id')->firstOrFail();

        self::assertSame('20 Over Match', $match->competition);
        self::assertNull($match->sport_state);
    }
}
