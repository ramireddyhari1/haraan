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
 * Live scoring must write the batting side's "runs/wickets" line. It never did, so every
 * match scored from the app showed runs alone ("68", not "68/2") on the ActionBoard, the
 * website and the partner screens — the feeds all read `score_text` for wickets.
 */
final class CricketScoreLineTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_ball_and_undo_keep_the_score_line_with_wickets(): void
    {
        $creator = User::create([
            'name' => 'Scorer', 'email' => 'scorer@haraan.test', 'password' => Hash::make('secret123'),
            'role' => 'user', 'status' => 'active', 'player_id' => 'HRNS1', 'is_guest' => false,
            'state' => 'Andhra Pradesh', 'district' => 'NTR', 'primary_sport' => 'Cricket',
            'sport_attributes' => ['role' => 'Batter', 'batting' => 'Right', 'bowling' => 'Right-arm medium'],
        ]);
        $m = LiveMatch::create([
            'title' => 'A v B', 'home' => 'ANT', 'away' => 'BEE', 'home_full' => 'Ants', 'away_full' => 'Bees',
            'home_score' => 0, 'away_score' => 0, 'status' => 'Scheduled', 'sport' => 'cricket',
            'competition' => '5 Over Match', 'user_id' => $creator->id,
            'home_squad' => [['id' => 'A1', 'name' => 'Asha'], ['id' => 'A2', 'name' => 'Arun'], ['id' => 'A3', 'name' => 'Anil']],
            'away_squad' => [['id' => 'B1', 'name' => 'Bala'], ['id' => 'B2', 'name' => 'Bhanu']],
        ]);
        $token = JwtService::issueForUser($creator, (string) (config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me')));
        $act = fn (array $body) => $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/matches/{$m->id}/score-action", $body)->assertSuccessful();

        $act(['type' => 'start', 'innings' => 1, 'batting_team' => 1, 'striker_id' => 'A1', 'non_striker_id' => 'A2', 'bowler_id' => 'B1']);
        self::assertSame('0/0', $m->fresh()->score_text);

        $act(['type' => 'runs', 'value' => 4]);
        $act(['type' => 'wide', 'value' => 1]);
        $act(['type' => 'wicket', 'dismissal' => 'bowled', 'new_batsman_id' => 'A3']);
        self::assertSame('5/1', $m->fresh()->score_text, 'runs include the wide; the wicket counts');

        $act(['type' => 'undo']);
        self::assertSame('5/0', $m->fresh()->score_text, 'undoing the wicket takes it off the line');

        // The feed the app and website read now carries the wickets.
        $row = collect($this->getJson('/api/live-matches')->assertOk()->json('data'))->firstWhere('id', (string) $m->id);
        self::assertSame('5/0', $row['score1']);
    }
}
