<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Local-only: demo rows for ActionBoard's District and State tabs (app + web).
 *
 *   php artisan db:seed --class=LocalLeaderboardSeeder
 *
 * The boards are read live from match_xp_ledger for the CURRENT month, so this writes
 * ranked ledger rows for this month across cricket / football / badminton:
 *   - 14 demo players in NTR (the local accounts' district) + the existing NTR accounts,
 *     with player@haraan.test placed mid-table so the "you" row is visible;
 *   - 16 demo players in 7 other Andhra Pradesh districts, so the State board and the
 *     "district rank N of M" line have something to rank against.
 * It also sets users.ranked_xp and career runs/wickets so the District summary card
 * (top batter / top bowler / district rank) fills in.
 *
 * Demo ledger rows use match_id >= 900000 (no such matches exist); re-running deletes
 * and rewrites only those rows, so it is idempotent and safe next to real ledger data.
 */
class LocalLeaderboardSeeder extends Seeder
{
    private const MATCH_BASE = 900000;

    /** [name, district, monthly XP target, career runs, career wickets] */
    private const PLAYERS = [
        // NTR
        ['Venkatesh Naidu',   'NTR', 2840, 1260, 18],
        ['Harsha Vardhan',    'NTR', 2610, 980, 41],
        ['Pavan Kalyan R',    'NTR', 2395, 1410, 6],
        ['Srinivas Reddy',    'NTR', 2120, 640, 52],
        ['Mahesh Chowdary',   'NTR', 1985, 870, 22],
        ['Teja Sai',          'NTR', 1730, 520, 30],
        ['Lokesh Varma',      'NTR', 1390, 455, 12],
        ['Nikhil Prasad',     'NTR', 1240, 390, 27],
        ['Ganesh Babu',       'NTR', 1105, 310, 9],
        ['Rohit Siddharth',   'NTR', 960, 280, 15],
        ['Aditya Kumar',      'NTR', 820, 205, 4],
        ['Sandeep Rao',       'NTR', 640, 160, 11],
        ['Vamsi Krishna',     'NTR', 470, 120, 3],
        ['Charan Tej',        'NTR', 310, 64, 2],
        // Rest of Andhra Pradesh
        ['Karthik Raju',      'Visakhapatnam', 3120, 1540, 20],
        ['Bharath Simha',     'Visakhapatnam', 2270, 760, 38],
        ['Prudhvi Raj',       'Guntur', 2980, 1120, 47],
        ['Yaswanth Reddy',    'Guntur', 1820, 690, 14],
        ['Dinesh Kumar',      'Guntur', 905, 300, 8],
        ['Sai Charan',        'Krishna', 2450, 1010, 25],
        ['Manoj Varma',       'Krishna', 1310, 430, 19],
        ['Raghu Ram',         'East Godavari', 2060, 820, 33],
        ['Naveen Chandra',    'East Godavari', 1150, 350, 10],
        ['Abhishek Naidu',    'Nellore', 1890, 740, 29],
        ['Jagadeesh P',       'Nellore', 720, 180, 6],
        ['Hemanth Kumar',     'Kurnool', 1640, 590, 21],
        ['Sravan Teja',       'Kurnool', 560, 140, 5],
        ['Chaitanya Reddy',   'Chittoor', 2190, 930, 16],
        ['Vinay Sagar',       'Chittoor', 1020, 330, 24],
        ['Rakesh Goud',       'Chittoor', 415, 90, 7],
    ];

    /** Existing local NTR accounts that should also appear on the board. */
    private const EXISTING = [
        'player@haraan.test'      => [1520, 610, 17],   // the viewer — mid-table
        'scorer@haraan.test'      => [1060, 340, 13],
        'ravi.demo@haraan.test'   => [890, 260, 9],
        'kiran.demo@haraan.test'  => [700, 150, 12],
        'suresh.demo@haraan.test' => [380, 95, 4],
    ];

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->error('LocalLeaderboardSeeder only runs locally.');

            return;
        }

        $state = 'Andhra Pradesh';
        $month = Carbon::now()->format('Y-m');
        $monthStart = Carbon::now()->startOfMonth();

        DB::table('match_xp_ledger')->where('match_id', '>=', self::MATCH_BASE)->delete();

        $players = [];
        foreach (self::PLAYERS as $i => [$name, $district, $xp, $runs, $wkts]) {
            $u = $this->demoUser($i + 1, $name, $district, $state);
            $players[] = [$u, $xp, $runs, $wkts];
        }
        foreach (self::EXISTING as $email => [$xp, $runs, $wkts]) {
            $u = User::where('email', $email)->first();
            if ($u !== null && ! empty($u->player_id)) {
                $players[] = [$u, $xp, $runs, $wkts];
            }
        }

        $matchId = self::MATCH_BASE;
        $rows = [];
        $now = now();
        foreach ($players as $p => [$u, $target, $runs, $wkts]) {
            // Split the month's XP over 4–12 matches; mostly cricket so the
            // cricket-filtered board is the fullest, with some football/badminton.
            $n = 4 + ($p % 9);
            $left = $target;
            for ($k = 0; $k < $n; $k++) {
                $xp = $k === $n - 1 ? $left : (int) round($target / $n * (0.7 + (($p + $k) % 7) / 10));
                $xp = max(10, min($xp, $left - 10 * ($n - $k - 1)));
                $left -= $xp;
                $sport = match (($p + $k) % 6) { 4 => 'football', 5 => 'badminton', default => 'cricket' };
                $at = $monthStart->copy()->addHours(($p * 7 + $k * 31) % max(1, (int) $monthStart->diffInHours($now)));
                $rows[] = [
                    'player_id' => $u->player_id,
                    'match_id' => ++$matchId,
                    'sport' => $sport,
                    'xp' => $xp,
                    'base_xp' => $xp,
                    'trust_level' => 'high',
                    'trust_multiplier' => 1.0,
                    'diversity_multiplier' => 1.0,
                    'is_ranked' => true,
                    'won' => ($p + $k) % 2 === 0,
                    'mom' => ($p + $k) % 5 === 0,
                    'opponent_key' => 'demo-'.(($p + $k) % 11),
                    'season_month' => $month,
                    'awarded_at' => $at,
                    'created_at' => $at,
                    'updated_at' => $at,
                ];
            }

            $u->forceFill([
                'ranked_xp' => max((int) $u->ranked_xp, $target),
                'career_runs' => max((int) $u->career_runs, $runs),
                'career_wickets' => max((int) $u->career_wickets, $wkts),
            ])->save();
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('match_xp_ledger')->insert($chunk);
        }

        $this->command?->info(sprintf(
            'Leaderboards: %d players, %d ranked ledger rows for %s (NTR + 7 AP districts).',
            count($players), count($rows), $month,
        ));
    }

    private function demoUser(int $n, string $name, string $district, string $state): User
    {
        $email = sprintf('lb%02d.demo@haraan.test', $n);
        $u = User::firstOrNew(['email' => $email]);
        $u->forceFill([
            'name' => $name,
            'status' => 'active',
            'role' => 'user',
            'password' => $u->exists ? $u->password : Hash::make('player1234'),
            'player_id' => sprintf('HRNLB%03d', $n),
            'username' => strtolower(str_replace(' ', '_', $name)).'_lb',
            'is_guest' => false,
            'district' => $district,
            'state' => $state,
        ])->save();

        return $u->fresh();
    }
}
