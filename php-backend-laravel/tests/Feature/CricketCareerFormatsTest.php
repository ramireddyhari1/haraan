<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The profile's cricket career, cut by format. A match stores its overs, not a format
 * name, so these pin the bands (Gully ≤6, T10 7–10, T20 11–20, One Day 21–50, Test 51+)
 * and that each format's line is built from that format's matches only.
 */
class CricketCareerFormatsTest extends TestCase
{
    use RefreshDatabase;

    private const PID = 'HRNFMT001';

    private function makePlayer(): User
    {
        $u = new User;
        $u->forceFill([
            'name' => 'Format Tester', 'email' => 'fmt@haraan.test', 'password' => Hash::make('secret123'),
            'status' => 'active', 'role' => 'user', 'is_guest' => false,
            'player_id' => self::PID, 'primary_sport' => 'Cricket',
            'district' => 'YSR Kadapa', 'state' => 'Andhra Pradesh',
        ])->save();

        return $u;
    }

    /** One finished cricket match of [overs] with this player's line in it. */
    private function match(?int $overs, string $competition, array $bat, array $bowl, int $catches = 0): void
    {
        $state = $overs === null ? null : json_encode(['format' => ['kind' => 'cricket', 'overs' => $overs, 'ball' => 'tennis']]);
        $id = DB::table('live_matches')->insertGetId([
            'title' => 'AA vs BB', 'home' => 'AA', 'away' => 'BB', 'status' => 'Completed',
            'sport' => 'cricket', 'competition' => $competition, 'match_type' => 'casual',
            'sport_state' => $state,
            'home_squad' => json_encode([['id' => self::PID, 'name' => 'Format Tester']]),
            'away_squad' => json_encode([]),
            'completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('player_match_stats')->insert([
            'match_id' => $id, 'player_id' => self::PID, 'player_name' => 'Format Tester',
            'sport' => 'cricket', 'side' => 'home', 'played' => true, 'result' => 'win',
            'runs' => $bat['runs'], 'balls' => $bat['balls'], 'wickets' => $bowl['wickets'],
            'stats' => json_encode([
                'batting' => ['innings' => [$bat]],
                'bowling' => ['innings' => [$bowl]],
                'fielding' => ['catches' => $catches, 'run_outs' => 0, 'stumpings' => 0],
            ]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function formats(): array
    {
        $sports = $this->getJson('/api/players/'.self::PID)->assertOk()->json('career_book.sports')
            ?? $this->getJson('/api/players/'.self::PID)->json('data.career_book.sports');
        $cricket = collect($sports)->firstWhere('key', 'cricket');
        $this->assertNotNull($cricket);

        return $cricket['formats'];
    }

    public function test_matches_are_split_into_formats_by_their_overs(): void
    {
        $this->makePlayer();
        $this->match(6, '6 Over Match', ['runs' => 34, 'balls' => 18, 'fours' => 3, 'sixes' => 2, 'out' => true], ['wickets' => 2, 'runs' => 14, 'balls' => 12], 1);
        $this->match(5, '5 Over Match', ['runs' => 10, 'balls' => 8, 'fours' => 1, 'sixes' => 0, 'out' => false], ['wickets' => 1, 'runs' => 9, 'balls' => 6]);
        $this->match(20, '20 Over Match', ['runs' => 61, 'balls' => 40, 'fours' => 6, 'sixes' => 3, 'out' => true], ['wickets' => 3, 'runs' => 25, 'balls' => 24]);
        // An older match that only names its length in the title.
        $this->match(null, '50 Over Match', ['runs' => 5, 'balls' => 9, 'fours' => 0, 'sixes' => 0, 'out' => true], ['wickets' => 0, 'runs' => 30, 'balls' => 36]);

        $formats = collect($this->formats())->keyBy('key');

        $this->assertSame(['gully', 't20', 'odi'], $formats->keys()->all(), 'only formats played, in band order');

        $gully = $formats['gully'];
        $this->assertSame('Gully', $gully['label']);
        $this->assertSame(2, $gully['matches']);
        $head = array_column($gully['headline'], 'value', 'label');
        $this->assertSame(['Matches' => '2', 'Runs' => '44', 'Wickets' => '3'], $head);
        $bat = array_column(collect($gully['groups'])->firstWhere('title', 'Batting')['stats'], 'value', 'label');
        $this->assertSame('34', $bat['Highest']);
        $this->assertSame('44', $bat['Average'], '44 runs, one dismissal');
        $this->assertNotNull(collect($gully['groups'])->firstWhere('title', 'Fielding'));

        $t20 = array_column(collect($formats['t20']['groups'])->firstWhere('title', 'Batting')['stats'], 'value', 'label');
        $this->assertSame('1 / 0', $t20['50s / 100s']);
        $this->assertNull(collect($formats['t20']['groups'])->firstWhere('title', 'Fielding'));

        $this->assertSame('One Day', $formats['odi']['label']);
    }

    public function test_a_player_with_no_finished_cricket_has_no_formats(): void
    {
        $this->makePlayer();

        $this->assertSame([], $this->formats());
    }
}
