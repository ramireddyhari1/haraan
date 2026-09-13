<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\LiveMatch;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Seed ONE realistic in-progress cricket match, for store screenshots and demos.
 *
 * The point is the ball-by-ball log. `LiveMatchController` replays `match_actions`
 * to build the scorecard, the commentary feed, the fall of wickets and the
 * partnership — so a match faked purely in the summary columns renders a hero with
 * empty tabs behind it. Everything here is written as real deliveries and the
 * summary columns are then derived from that same log, so the two can't disagree.
 *
 * Names are invented local club sides. Deliberately NOT real teams or players:
 * these frames end up in a public store listing, where a real player's name or a
 * franchise's initials is someone else's trademark.
 *
 * Local only. It refuses to run in production — the whole point is to keep
 * fixtures out of the live feed.
 */
final class SeedCricketMatch extends Command
{
    protected $signature = 'demo:seed-cricket-match
        {--user= : Creator user id. Defaults to the first user.}
        {--fresh : Delete a previously seeded demo match first}';

    protected $description = 'Seed one realistic live cricket match (real ball log) for screenshots.';

    private const HOME = 'KDK';
    private const AWAY = 'NLW';
    private const HOME_FULL = 'Kadapa Kings';
    private const AWAY_FULL = 'Nellore Warriors';

    /** Invented club players — see the class note on why these aren't real names. */
    private const BATTERS = [
        ['id' => 'DEMO_AR', 'name' => 'Arjun Mehta'],
        ['id' => 'DEMO_RT', 'name' => 'Ravi Teja'],
        ['id' => 'DEMO_KN', 'name' => 'Karthik Nair'],
        ['id' => 'DEMO_SV', 'name' => 'Sandeep Varma'],
    ];

    private const BOWLERS = [
        ['id' => 'DEMO_IQ', 'name' => 'Imran Qadir'],
        ['id' => 'DEMO_SP', 'name' => 'Suresh Pillai'],
    ];

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to seed demo data in production.');

            return self::FAILURE;
        }

        $creator = $this->option('user')
            ? User::find((int) $this->option('user'))
            : User::query()->orderBy('id')->first();

        if ($creator === null) {
            $this->error('No user to own the match.');

            return self::FAILURE;
        }

        $players = $this->ensurePlayers();

        if ($this->option('fresh')) {
            LiveMatch::query()->where('home', self::HOME)->where('away', self::AWAY)->get()
                ->each(fn (LiveMatch $m) => $m->delete());
            $this->line('removed previous demo match');
        }

        $squadHome = array_map(fn (array $p): array => ['id' => $p['id'], 'name' => $p['name']], self::BATTERS);
        $squadAway = array_map(fn (array $p): array => ['id' => $p['id'], 'name' => $p['name']], self::BOWLERS);

        $match = LiveMatch::create([
            'user_id'    => $creator->id,
            'sport'      => 'cricket',
            'home'       => self::HOME,
            'away'       => self::AWAY,
            'home_full'  => self::HOME_FULL,
            'away_full'  => self::AWAY_FULL,
            'home_emblem' => 'action1',
            'away_emblem' => 'action3',
            'status'     => 'Live',
            'competition' => '20 Over Match',
            'match_type' => '20 Over Match',
            'venue'      => 'Municipal Ground, Kadapa',
            'district'   => 'YSR Kadapa',
            'state'      => 'Andhra Pradesh',
            'locality'   => 'Kadapa',
            'visibility' => LiveMatch::VIS_FEATURED,
            'decision'   => self::HOME_FULL . ' • Bat',
            'home_squad' => $squadHome,
            'away_squad' => $squadAway,
            'latitude'   => 14.4673,
            'longitude'  => 78.8242,
            'is_ranked'  => false,
            'is_private' => false,
        ]);

        [$log, $state] = $this->buildInnings();
        $this->writeActions($match, $creator, $log);

        // Summary columns derived from the SAME log the tabs replay, so the hero and
        // the scorecard can never contradict each other.
        $match->update([
            'home_score'  => $state['runs'],
            'away_score'  => 0,
            'score_text'  => $state['runs'] . '/' . $state['wickets'],
            'overs'       => $state['overs'],
            'crr'         => $state['crr'],
            'batters'     => $state['batters'],
            'bowler'      => $state['bowler'],
            'over_summary' => $state['overSummary'],
        ]);

        $this->info(sprintf(
            'Seeded match #%d — %s %s/%s (%s ov) vs %s, %d deliveries logged.',
            $match->id, self::HOME, $state['runs'], $state['wickets'], $state['overs'], self::AWAY, count($log)
        ));

        return self::SUCCESS;
    }

    /** Create the demo players once; reuse them on re-seed so ids stay stable. */
    private function ensurePlayers(): array
    {
        $out = [];
        foreach (array_merge(self::BATTERS, self::BOWLERS) as $p) {
            $out[$p['id']] = User::firstOrCreate(
                ['player_id' => $p['id']],
                [
                    'name'     => $p['name'],
                    'email'    => strtolower(str_replace(' ', '.', $p['name'])) . '@demo.haraan.invalid',
                    'password' => bcrypt(bin2hex(random_bytes(8))),
                ]
            );
        }

        return $out;
    }

    /**
     * The innings itself, as deliveries. Shaped to exercise every commentary state the
     * feed can render: boundaries, a six, dot balls, a wide, and two dismissals — the
     * second of which brings a new batter in, so the wicket card and the "walks in"
     * row appear back to back the way they do in a real game.
     *
     * @return array{0: array<int, array{type: string, payload: array}>, 1: array}
     */
    private function buildInnings(): array
    {
        $b = fn (string $id): string => $id;
        $log = [];

        $log[] = ['type' => 'start', 'payload' => [
            'batting_team'    => 1,
            'striker_id'      => $b('DEMO_AR'),
            'non_striker_id'  => $b('DEMO_RT'),
            'bowler_id'       => $b('DEMO_IQ'),
        ]];

        // Over 1 — Imran Qadir. 11 from it, opens with a boundary.
        foreach ([4, 1, 0, 2, 0, 4] as $r) {
            $log[] = ['type' => 'runs', 'payload' => ['value' => $r]];
        }

        // Over 2 — Suresh Pillai. A six, then the first wicket.
        $log[] = ['type' => 'change_bowler', 'payload' => ['bowler_id' => $b('DEMO_SP')]];
        foreach ([1, 6, 0] as $r) {
            $log[] = ['type' => 'runs', 'payload' => ['value' => $r]];
        }
        $log[] = ['type' => 'wicket', 'payload' => [
            'dismissal'       => 'bowled',
            'new_batsman_id'  => $b('DEMO_KN'),
        ]];
        foreach ([1, 2] as $r) {
            $log[] = ['type' => 'runs', 'payload' => ['value' => $r]];
        }

        // Over 3 — Imran back. A wide, a four, and the second wicket, caught.
        $log[] = ['type' => 'change_bowler', 'payload' => ['bowler_id' => $b('DEMO_IQ')]];
        $log[] = ['type' => 'runs', 'payload' => ['value' => 1]];
        $log[] = ['type' => 'wide', 'payload' => ['value' => 1]];
        foreach ([4, 0, 1] as $r) {
            $log[] = ['type' => 'runs', 'payload' => ['value' => $r]];
        }
        $log[] = ['type' => 'wicket', 'payload' => [
            'dismissal'       => 'caught',
            'new_batsman_id'  => $b('DEMO_SV'),
        ]];
        $log[] = ['type' => 'runs', 'payload' => ['value' => 2]];

        // Over 4 — Suresh. In progress, so the feed has a live over to show.
        $log[] = ['type' => 'change_bowler', 'payload' => ['bowler_id' => $b('DEMO_SP')]];
        foreach ([1, 4, 0, 6] as $r) {
            $log[] = ['type' => 'runs', 'payload' => ['value' => $r]];
        }

        return [$log, $this->derive($log)];
    }

    /**
     * Replay the log the same way the API does, so the summary columns agree with it.
     * Deliberately a second implementation rather than a shared helper: if this ever
     * drifts from the controller's replay, the seeded match shows it immediately.
     */
    private function derive(array $log): array
    {
        $runs = 0; $wickets = 0; $legal = 0;
        $overs = []; $current = []; $currentRuns = 0; $overNo = 1;
        $bowlerRuns = 0; $bowlerWkts = 0; $bowlerBalls = 0;
        $strikerRuns = 0; $strikerBalls = 0; $nonStrikerRuns = 0; $nonStrikerBalls = 0;
        $strikerName = 'Arjun Mehta'; $nonStrikerName = 'Ravi Teja';
        $bowlerName = 'Suresh Pillai';
        $queue = ['Karthik Nair', 'Sandeep Varma'];

        foreach ($log as $a) {
            $t = $a['type'];
            if ($t === 'start' || $t === 'change_batsman') { continue; }
            if ($t === 'change_bowler') {
                if ($legal > 0) { $bowlerRuns = 0; $bowlerWkts = 0; $bowlerBalls = 0; }
                continue;
            }

            if ($t === 'wide') {
                $runs += 1; $bowlerRuns += 1; $current[] = 'wd'; $currentRuns += 1;
                continue;
            }

            if ($t === 'wicket') {
                $wickets++; $bowlerWkts++; $legal++; $bowlerBalls++; $strikerBalls++;
                $current[] = 'W';
                $strikerName = array_shift($queue) ?: $strikerName;
                $strikerRuns = 0; $strikerBalls = 0;
            } else {
                $r = (int) $a['payload']['value'];
                $runs += $r; $bowlerRuns += $r; $legal++; $bowlerBalls++;
                $strikerRuns += $r; $strikerBalls++;
                $current[] = (string) $r; $currentRuns += $r;
                if ($r % 2 === 1) {
                    [$strikerName, $nonStrikerName] = [$nonStrikerName, $strikerName];
                    [$strikerRuns, $nonStrikerRuns] = [$nonStrikerRuns, $strikerRuns];
                    [$strikerBalls, $nonStrikerBalls] = [$nonStrikerBalls, $strikerBalls];
                }
            }

            if ($legal % 6 === 0 && $legal > 0 && count($current) > 0) {
                $overs[] = ['over' => (string) $overNo, 'batting' => self::HOME, 'balls' => $current, 'runs' => $currentRuns];
                $overNo++; $current = []; $currentRuns = 0;
                [$strikerName, $nonStrikerName] = [$nonStrikerName, $strikerName];
                [$strikerRuns, $nonStrikerRuns] = [$nonStrikerRuns, $strikerRuns];
                [$strikerBalls, $nonStrikerBalls] = [$nonStrikerBalls, $strikerBalls];
            }
        }

        if (count($current) > 0) {
            $overs[] = ['over' => (string) $overNo, 'batting' => self::HOME, 'balls' => $current, 'runs' => $currentRuns];
        }

        $oversText = intdiv($legal, 6) . '.' . ($legal % 6);
        $crr = $legal > 0 ? number_format($runs / ($legal / 6), 2) : '0.00';

        return [
            'runs' => $runs,
            'wickets' => $wickets,
            'overs' => $oversText,
            'crr' => $crr,
            'batters' => [
                ['name' => $strikerName, 'runs' => $strikerRuns, 'balls' => $strikerBalls],
                ['name' => $nonStrikerName, 'runs' => $nonStrikerRuns, 'balls' => $nonStrikerBalls],
            ],
            'bowler' => [
                'name' => $bowlerName,
                'figures' => $bowlerWkts . '-' . $bowlerRuns,
                'overs' => intdiv($bowlerBalls, 6) . '.' . ($bowlerBalls % 6),
            ],
            'overSummary' => array_slice($overs, -3),
        ];
    }

    /** Persist the log with the over/ball coordinates the table expects. */
    private function writeActions(LiveMatch $match, User $creator, array $log): void
    {
        $legal = 0;
        $rows = [];
        foreach ($log as $a) {
            $isBall = in_array($a['type'], ['runs', 'wicket', 'bye', 'legbye'], true);
            $rows[] = [
                'match_id'    => $match->id,
                'innings'     => 1,
                'over_number' => intdiv($legal, 6) + 1,
                'ball_number' => ($legal % 6) + 1,
                'action_type' => $a['type'],
                'payload'     => json_encode($a['payload']),
                'version'     => 1,
                'created_by'  => $creator->id,
                'created_at'  => now(),
                'updated_at'  => now(),
            ];
            if ($isBall) { $legal++; }
        }
        DB::table('match_actions')->insert($rows);
    }
}
