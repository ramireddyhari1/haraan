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
 * Each partnership says who made what inside it, and where the innings stood when it began
 * and ended — replayed from the ball log, never apportioned from the stand's total.
 */
final class CricketPartnershipSplitTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_stand_is_split_between_its_two_batters_and_carries_its_start_and_end(): void
    {
        $creator = User::create([
            'name' => 'Scorer', 'email' => 'scorer@haraan.test', 'password' => Hash::make('secret123'),
            'role' => 'user', 'status' => 'active', 'player_id' => 'HRNS0', 'is_guest' => false,
        ]);
        $m = LiveMatch::create([
            'title' => 'Ants vs Bees', 'home' => 'ANT', 'away' => 'BEE', 'home_full' => 'Ants', 'away_full' => 'Bees',
            'home_score' => 0, 'away_score' => 0, 'status' => 'Live', 'sport' => 'cricket', 'user_id' => $creator->id,
            'home_squad' => [['id' => 'A1', 'name' => 'Asha'], ['id' => 'A2', 'name' => 'Arun'], ['id' => 'A3', 'name' => 'Anil']],
            'away_squad' => [['id' => 'B1', 'name' => 'Bala']],
        ]);

        $log = [
            ['start', ['batting_team' => 1, 'striker_id' => 'A1', 'non_striker_id' => 'A2', 'bowler_id' => 'B1']],
            ['runs', ['value' => 4]],   // Asha 4 (1)
            ['runs', ['value' => 1]],   // Asha 5 (2), strike to Arun
            ['wide', ['value' => 1]],   // extra: the stand's, nobody's
            ['runs', ['value' => 6]],   // Arun 6 (1)
            ['runs', ['value' => 0]],   // Arun 6 (2)
            ['wicket', ['dismissal' => 'bowled', 'new_batsman_id' => 'A3']], // Arun out, 12/1 after 5 balls
            ['runs', ['value' => 2]],   // Anil 2 (1) — second stand, from 12/1
        ];
        foreach ($log as [$type, $payload]) {
            DB::table('match_actions')->insert([
                'match_id' => $m->id, 'action_type' => $type, 'payload' => json_encode($payload),
                'created_by' => $creator->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $stands = app(CricketInsights::class)->facts($m)['innings'][0]['partnerships'];

        self::assertCount(2, $stands);
        [$first, $second] = $stands;

        self::assertSame(12, $first['runs'], 'the stand counts the wide');
        self::assertSame(['name' => 'Asha', 'runs' => 5, 'balls' => 2], $first['split'][0]);
        self::assertSame(['name' => 'Arun', 'runs' => 6, 'balls' => 3], $first['split'][1], 'the wicket ball was faced too');
        self::assertSame(['runs' => 0, 'wickets' => 0, 'overs' => '0.0'], $first['start']);
        self::assertSame(['runs' => 12, 'wickets' => 1, 'overs' => '0.5'], $first['end']);

        self::assertTrue($second['unbroken']);
        self::assertSame(['runs' => 12, 'wickets' => 1, 'overs' => '0.5'], $second['start']);
        self::assertSame(['runs' => 14, 'wickets' => 1, 'overs' => '1.0'], $second['end']);
        self::assertSame('Anil', $second['split'][0]['name']);
        self::assertSame(2, $second['split'][0]['runs']);
    }
}
