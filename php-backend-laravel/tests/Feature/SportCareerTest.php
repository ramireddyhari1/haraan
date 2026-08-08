<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LiveMatch;
use App\Models\MatchEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Career figures phrased in the player's own sport.
 *
 * The contract these tests defend: **a profile never shows a stat the sport doesn't
 * have.** `users.career_runs` / `career_wickets` are written by the cricket ball-log
 * replay, so a footballer's profile was advertising batting figures that could only
 * ever read zero — and a badminton player's too. Where no per-player record exists
 * (badminton points are logged per SIDE), the answer is fewer cells, never a filler
 * number.
 */
class SportCareerTest extends TestCase
{
    use RefreshDatabase;

    private function player(string $sport, string $playerId, string $email): User
    {
        $attributes = match ($sport) {
            'Football' => ['position' => 'Midfielder', 'foot' => 'Right'],
            'Badminton' => ['format' => 'Singles', 'hand' => 'Right'],
            default => ['role' => 'All-rounder', 'batting' => 'Right', 'bowling' => 'Right-arm medium'],
        };

        return User::create([
            'name' => $playerId, 'email' => $email,
            'password' => Hash::make('secret123'), 'role' => 'user', 'status' => 'active',
            'player_id' => $playerId, 'is_guest' => false,
            'state' => 'Andhra Pradesh', 'district' => 'YSR Kadapa',
            'primary_sport' => $sport,
            'sport_attributes' => $attributes,
            // Cricket columns are populated for EVERY player by the legacy pipeline.
            // That is precisely why they must not be shown to non-cricketers.
            'career_matches' => 12, 'career_runs' => 340, 'career_wickets' => 7,
        ]);
    }

    private function token(User $user): string
    {
        return \App\Support\JwtService::issue(
            ['sub' => $user->id],
            (string) config('app.jwt_secret', env('JWT_SECRET', 'change_me')),
        );
    }

    /** @return array<string, mixed> */
    private function sportCareer(User $user): array
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token($user))
            ->getJson('/api/players/me')
            ->assertOk()
            ->json('sport_career');
    }

    public function test_a_cricketer_still_sees_runs_and_wickets(): void
    {
        $career = $this->sportCareer($this->player('Cricket', 'HRNCRIC', 'c@haraan.test'));

        self::assertSame('cricket', $career['sport']);
        self::assertSame(340, $career['runs']);
        self::assertSame(7, $career['wickets']);
    }

    public function test_a_footballer_sees_goals_and_assists_never_runs_or_wickets(): void
    {
        $user = $this->player('Football', 'HRNFOOT', 'f@haraan.test');

        $match = LiveMatch::create([
            'title' => 'Turf A', 'home' => 'AAA', 'away' => 'BBB',
            'sport' => 'football', 'status' => 'completed',
            'home_score' => 2, 'away_score' => 1,
            'home_squad' => [['id' => 'HRNFOOT', 'name' => 'HRNFOOT']],
            'away_squad' => [],
        ]);

        foreach ([MatchEvent::GOAL, MatchEvent::GOAL, MatchEvent::ASSIST, MatchEvent::OWN_GOAL] as $i => $kind) {
            MatchEvent::create([
                'live_match_id' => $match->id, 'sport' => 'football',
                'sequence' => $i + 1, 'side' => 'home', 'kind' => $kind,
                'player_id' => $user->id, 'player_name' => $user->name,
            ]);
        }

        $career = $this->sportCareer($user);

        self::assertSame('football', $career['sport']);
        self::assertSame(2, $career['goals'], 'own goal must not count toward the scorer tally');
        self::assertSame(1, $career['assists']);
        self::assertSame(1, $career['matches'], 'counted from squad membership, not career_matches');
        // The cricket columns are populated on this user and must not leak through.
        self::assertArrayNotHasKey('runs', $career);
        self::assertArrayNotHasKey('wickets', $career);
    }

    public function test_a_badminton_player_gets_matches_only_rather_than_an_invented_stat(): void
    {
        $user = $this->player('Badminton', 'HRNBADM', 'b@haraan.test');

        LiveMatch::create([
            'title' => 'Court 2', 'home' => 'Hari', 'away' => 'Imran',
            'sport' => 'badminton', 'status' => 'completed',
            'home_score' => 2, 'away_score' => 0,
            'home_squad' => [['id' => 'HRNBADM', 'name' => 'HRNBADM']],
            'away_squad' => [],
        ]);

        $career = $this->sportCareer($user);

        self::assertSame('badminton', $career['sport']);
        self::assertSame(1, $career['matches']);
        // Badminton points are recorded per side. There is no honest per-player
        // number, so there must be no key pretending otherwise.
        self::assertArrayNotHasKey('runs', $career);
        self::assertArrayNotHasKey('wickets', $career);
        self::assertArrayNotHasKey('goals', $career);
    }

    /** Older app builds read `career.runs`; that key must not move or change shape. */
    public function test_the_legacy_career_block_is_untouched(): void
    {
        $career = $this->withHeader('Authorization', 'Bearer ' . $this->token($this->player('Football', 'HRNLEG', 'l@haraan.test')))
            ->getJson('/api/players/me')
            ->assertOk()
            ->json('career');

        self::assertSame(340, $career['runs']);
        self::assertSame(7, $career['wickets']);
    }
}
