<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LiveMatch;
use App\Models\User;
use App\Services\CricketInsights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The shots section counts only what the scorer named, from the fixed list — never a
 * guess, never an unknown key.
 */
final class CricketShotTypesTest extends TestCase
{
    use RefreshDatabase;

    public function test_named_strokes_are_tallied_and_unknown_ones_are_ignored(): void
    {
        $creator = User::create([
            'name' => 'Scorer', 'email' => 'scorer@haraan.test', 'password' => Hash::make('secret123'),
            'role' => 'user', 'status' => 'active', 'player_id' => 'HRNS0', 'is_guest' => false,
        ]);
        $m = LiveMatch::create([
            'title' => 'Ants vs Bees', 'home' => 'ANT', 'away' => 'BEE',
            'home_score' => 0, 'away_score' => 0, 'status' => 'Live', 'sport' => 'cricket', 'user_id' => $creator->id,
            'home_squad' => [['id' => 'A1', 'name' => 'Asha'], ['id' => 'A2', 'name' => 'Arun']],
            'away_squad' => [['id' => 'B1', 'name' => 'Bala']],
        ]);
        $log = [
            ['start', ['batting_team' => 1, 'striker_id' => 'A1', 'non_striker_id' => 'A2', 'bowler_id' => 'B1']],
            ['runs', ['value' => 4, 'shot' => 'cover_drive', 'zone' => 1]],
            ['runs', ['value' => 6, 'shot' => 'pull']],
            ['runs', ['value' => 4, 'shot' => 'COVER_DRIVE']],
            ['runs', ['value' => 4, 'shot' => 'helicopter_of_doom']],
            ['runs', ['value' => 4]],
            ['runs', ['value' => 0, 'shot' => 'cut']],
        ];
        foreach ($log as [$type, $payload]) {
            DB::table('match_actions')->insert([
                'match_id' => $m->id, 'action_type' => $type, 'payload' => json_encode($payload),
                'created_by' => $creator->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $inn = app(CricketInsights::class)->facts($m)['innings'][0];

        self::assertSame([
            ['type' => 'cover_drive', 'label' => 'Cover drive', 'shots' => 2, 'runs' => 8, 'fours' => 2, 'sixes' => 0],
            ['type' => 'pull', 'label' => 'Pull', 'shots' => 1, 'runs' => 6, 'fours' => 0, 'sixes' => 1],
        ], $inn['shotTypes'], 'unknown keys, unnamed boundaries and a dot ball add nothing');
        self::assertSame('cover_drive', $inn['shots'][0]['shot'], 'the plotted shot carries its stroke');
    }

    public function test_the_match_says_whether_its_scorer_is_asked_for_the_shot(): void
    {
        $this->artisan('migrate');
        $creator = User::create([
            'name' => 'Scorer', 'email' => 'scorer2@haraan.test', 'password' => Hash::make('secret123'),
            'role' => 'user', 'status' => 'active', 'player_id' => 'HRNS1', 'is_guest' => false,
        ]);
        $m = LiveMatch::create([
            'title' => 'T', 'home' => 'ANT', 'away' => 'BEE', 'home_score' => 0, 'away_score' => 0,
            'status' => 'Live', 'sport' => 'cricket', 'user_id' => $creator->id,
        ]);

        $this->getJson("/api/live-matches/{$m->id}")->assertOk()->assertJsonPath('shotTypes', false);
        self::assertTrue(DB::table('member_features')->where('key', 'matches.shot_types')->exists(),
            'the feature is in the /control catalogue');
    }
}
