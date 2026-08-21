<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\LiveMatch;
use App\Models\User;
use App\Services\MatchEventRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Demo matches for the five sports that arrived without any: volleyball, basketball,
 * kabaddi, tennis and table tennis. Two each — one still being played, one finished —
 * so both halves of every detail screen can be looked at on a real device.
 *
 * The important property: **nothing here writes a scoreline.** Each match is seeded as
 * an event log — a list of points, with what each one was worth and who scored it — and
 * the score is then derived by the same SportScoreEngine a live scorer drives. So the
 * sets, the quarter splits, the serve indicator and the tennis point ladder on screen
 * are produced by production code, not by a fixture that agrees with itself. If a board
 * renders wrong against this data, the bug is real.
 *
 * Idempotent: each match is keyed by a stable join code and skipped if it already
 * exists. `--fresh` rebuilds them (events cascade on delete).
 */
final class SeedSportMatches extends Command
{
    protected $signature = 'demo:seed-sport-matches
        {--user= : Creator — user id, email or player id. Defaults to the newest match creator.}
        {--lat= : Where to put them. Defaults to where the creator last played.}
        {--lng= : Where to put them. Defaults to where the creator last played.}
        {--fresh : Delete and rebuild demo matches that already exist}';

    protected $description = 'Seed demo volleyball / basketball / kabaddi / tennis / table-tennis matches with real event logs.';

    /** Last-resort centre (YSR Kadapa) when neither the options nor the creator supply one. */
    private const LAT = 14.4673;
    private const LNG = 78.8242;

    private float $lat = self::LAT;
    private float $lng = self::LNG;

    public function handle(MatchEventRecorder $recorder): int
    {
        $creator = $this->resolveCreator();
        if ($creator === null) {
            $this->error('No users in this database — create one first.');

            return self::FAILURE;
        }

        // Seed them where this creator actually plays: a match dropped at a default
        // coordinate sorts to the bottom of a near-me feed and shows a nonsense
        // distance chip, which is exactly the thing being checked.
        [$this->lat, $this->lng] = $this->centre($creator);

        $this->info("Creator: {$creator->name} (id {$creator->id})");
        $this->info("Centre:  {$this->lat}, {$this->lng}");

        // Same seed every run: a re-seeded board looks like the one you screenshotted.
        mt_srand(20260820);

        $made = 0;
        foreach ($this->blueprints() as $bp) {
            $existing = LiveMatch::query()->where('join_code', $bp['code'])->first();
            if ($existing !== null) {
                if (! $this->option('fresh')) {
                    $this->line("  {$bp['code']} exists — skipped (use --fresh to rebuild)");

                    continue;
                }
                // Events cascade on delete, but SQLite only honours that with foreign
                // keys on — drop them explicitly so a rebuild can't leave orphans.
                DB::table('match_events')->where('live_match_id', $existing->id)->delete();
                $existing->delete();
            }

            $match = $this->createMatch($bp, $creator);
            $this->writeEvents($match, $bp['events']);
            $recorder->resync($match);

            // A finished match is closed AFTER the replay, so the engine sees a normal
            // log and the final scoreline is still the one its own arithmetic produced.
            if ($bp['status'] !== 'Live') {
                $match->forceFill([
                    'status' => $bp['status'],
                    'result' => $match->home_score > $match->away_score ? 'home' : 'away',
                ])->save();
            }

            $fresh = $match->fresh();
            $this->info(sprintf(
                '  %-11s #%-4d %-28s %s  (%d events)',
                $bp['sport'],
                $fresh->id,
                $fresh->home_full.' v '.$fresh->away_full,
                $fresh->score_text,
                count($bp['events']),
            ));
            $made++;
        }

        $this->newLine();
        $this->info($made.' match(es) seeded.');

        return self::SUCCESS;
    }

    /** id / email / player id, else whoever created the most recent match, else user 1. */
    private function resolveCreator(): ?User
    {
        $who = (string) ($this->option('user') ?? '');

        if ($who !== '') {
            return User::query()
                ->when(ctype_digit($who), fn ($q) => $q->orWhere('id', (int) $who))
                ->orWhere('email', $who)
                ->orWhere('player_id', strtoupper($who))
                ->first();
        }

        $lastCreatorId = LiveMatch::query()->whereNotNull('user_id')->orderByDesc('id')->value('user_id');

        return ($lastCreatorId ? User::query()->find($lastCreatorId) : null)
            ?? User::query()->orderBy('id')->first();
    }

    /**
     * Where to drop the demo matches: the options if given, else the creator's most
     * recent located match, else the district centre.
     *
     * @return array{0: float, 1: float}
     */
    private function centre(User $creator): array
    {
        $lat = $this->option('lat');
        $lng = $this->option('lng');
        if ($lat !== null && $lng !== null && $lat !== '' && $lng !== '') {
            return [(float) $lat, (float) $lng];
        }

        $last = LiveMatch::query()
            ->where('user_id', $creator->id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderByDesc('id')
            ->first();

        return $last !== null
            ? [(float) $last->latitude, (float) $last->longitude]
            : [self::LAT, self::LNG];
    }

    private function createMatch(array $bp, User $creator): LiveMatch
    {
        return LiveMatch::query()->create([
            'title' => $bp['home'].' vs '.$bp['away'],
            'sport' => $bp['sport'],
            // The creator's format, stored exactly where the create wizard puts it —
            // the engine reads pointsTo/bestOf/gamesTo out of here to know when a set ends.
            'sport_state' => ['format' => $bp['format']],
            'home' => $bp['homeShort'],
            'away' => $bp['awayShort'],
            'home_full' => $bp['home'],
            'away_full' => $bp['away'],
            'competition' => $bp['competition'],
            'venue' => $bp['venue'],
            'status' => 'Live',           // closed after the replay, for finished matches
            'time' => 'Today',
            'home_score' => 0,
            'away_score' => 0,
            // No sport here counts overs. Left blank so the feed card doesn't print
            // "(0.0)" beside a volleyball set score.
            'overs' => '',
            'crr' => '0.00',
            'batters' => [],
            'bowler' => [],
            'timeline' => [],
            'over_summary' => [],
            'home_squad' => $this->squad($bp['homePlayers']),
            'away_squad' => $this->squad($bp['awayPlayers']),
            'user_id' => $creator->id,

            'match_type' => 'casual',
            'base_xp' => 25,
            'trust_level' => 'low',
            'verification_status' => 'none',
            // Demo matches never earn anybody XP — a seeded event log must not move a
            // leaderboard that real players are ranked on.
            'is_ranked' => false,

            'is_private' => false,
            // Doubles as this seeder's idempotency key. Public matches carry one
            // harmlessly — visibility is decided by is_private, never by the code.
            'join_code' => $bp['code'],

            'open_to_join' => false,
            'slots_needed' => 0,

            'visibility' => LiveMatch::VIS_LOCAL,
            'district' => $creator->district ?: 'YSR Kadapa',
            'state' => $creator->state ?: 'Andhra Pradesh',
            'locality' => $bp['locality'],
            'latitude' => round($this->lat + $bp['jitter'][0], 7),
            'longitude' => round($this->lng + $bp['jitter'][1], 7),
        ]);
    }

    /** Squad entries in the shape MatchesController::normalizeSquad produces. */
    private function squad(array $names): array
    {
        return array_map(static fn (string $n): array => ['id' => null, 'name' => $n], $names);
    }

    /** @param  array<int, array<string, mixed>>  $events */
    private function writeEvents(LiveMatch $match, array $events): void
    {
        $now = now();
        $rows = [];
        foreach ($events as $i => $e) {
            $rows[] = [
                'live_match_id' => $match->id,
                'sport' => $match->sport,
                'sequence' => $i + 1,
                'minute' => $e['minute'] ?? null,
                'side' => $e['side'] ?? null,
                'kind' => $e['kind'],
                'player_name' => $e['player'] ?? null,
                'detail' => $e['detail'] ?? null,
                'note' => $e['note'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('match_events')->insert($chunk);
        }
    }

    // ─────────────────────────── the matches ───────────────────────────

    /** @return array<int, array<string, mixed>> */
    private function blueprints(): array
    {
        $vbHome = ['Naveen Kumar', 'Sai Charan', 'Praveen Reddy', 'Anil Babu', 'Rakesh V', 'Dinesh M'];
        $vbAway = ['Imran Khan', 'Vamsi Krishna', 'Yaswanth G', 'Karthik S', 'Mahesh B', 'Sandeep R'];
        $bbHome = ['Arjun Rao', 'Nikhil Teja', 'Rohan Das', 'Sharath K', 'Vivek N'];
        $bbAway = ['Daniel Raj', 'Manoj Pillai', 'Sathya P', 'Harsha V', 'Ganesh L'];
        $kbHome = ['Ravi Teja', 'Suresh Naidu', 'Pardhu Y', 'Bala Krishna', 'Naga Raju', 'Chandu K', 'Siva Sankar'];
        $kbAway = ['Jagan Mohan', 'Venkatesh P', 'Ashok Reddy', 'Prasad B', 'Kiran Kumar', 'Lokesh D', 'Srinu M'];

        return [
            // ── Volleyball: two sets down, third in play. Best of 5 to 25, so the
            //    fifth set would run to 15 — the rule the "first to" line reads live.
            [
                'code' => 'DEMOVOLLEY1',
                'sport' => 'volleyball',
                'home' => 'Kadapa Spikers', 'homeShort' => 'KDS',
                'away' => 'Proddatur Smashers', 'awayShort' => 'PDS',
                'homePlayers' => $vbHome, 'awayPlayers' => $vbAway,
                'format' => ['kind' => 'volleyball', 'bestOf' => 5, 'pointsTo' => 25],
                'competition' => 'Indoor 6s · best of 5 to 25',
                'venue' => 'Municipal Indoor Court, Kadapa',
                'locality' => 'Kadapa',
                'jitter' => [0.012, -0.008],
                'status' => 'Live',
                'events' => $this->rallyEvents([[25, 22], [23, 25]], [18, 16], $vbHome, $vbAway),
            ],
            // ── Volleyball, finished: beach rules, and it went the distance. Best of 3
            //    means set three is the decider, which runs to 15 — 15-12 is a real
            //    beach scoreline and a board that expects 21 will render it wrong.
            [
                'code' => 'DEMOVOLLEY2',
                'sport' => 'volleyball',
                'home' => 'Rayachoti Risers', 'homeShort' => 'RYR',
                'away' => 'Pulivendula Blockers', 'awayShort' => 'PVB',
                'homePlayers' => array_slice($vbHome, 0, 2), 'awayPlayers' => array_slice($vbAway, 0, 2),
                'format' => ['kind' => 'volleyball', 'bestOf' => 3, 'pointsTo' => 21],
                'competition' => 'Beach 2s · best of 3 to 21',
                'venue' => 'Sand Court, Rayachoti',
                'locality' => 'Rayachoti',
                'jitter' => [-0.021, 0.014],
                'status' => 'Completed',
                'events' => $this->rallyEvents(
                    [[21, 18], [19, 21], [15, 12]], null,
                    array_slice($vbHome, 0, 2), array_slice($vbAway, 0, 2),
                ),
            ],

            // ── Basketball: mid third quarter. Points carry their value (a three is a
            //    three), so the quarter line and the scorers panel are both sums of
            //    what was actually tapped.
            [
                'code' => 'DEMOBBALL1',
                'sport' => 'basketball',
                'home' => 'Kadapa Hoopers', 'homeShort' => 'KDH',
                'away' => 'Nellore Ballers', 'awayShort' => 'NLB',
                'homePlayers' => $bbHome, 'awayPlayers' => $bbAway,
                'format' => ['kind' => 'basketball', 'periods' => 4, 'periodLengthMin' => 10],
                'competition' => 'Full court 5s · 4 x 10 min',
                'venue' => 'YSR District Sports Complex',
                'locality' => 'Kadapa',
                'jitter' => [0.006, 0.019],
                'status' => 'Live',
                'events' => $this->periodEvents('basketball', [[22, 19], [18, 24], [9, 7]], $bbHome, $bbAway, 10),
            ],
            [
                'code' => 'DEMOBBALL2',
                'sport' => 'basketball',
                'home' => 'Proddatur Panthers', 'homeShort' => 'PDP',
                'away' => 'Mydukur Magic', 'awayShort' => 'MDM',
                'homePlayers' => $bbAway, 'awayPlayers' => $bbHome,
                'format' => ['kind' => 'basketball', 'periods' => 2, 'periodLengthMin' => 10],
                'competition' => '3x3 half court · 2 x 10 min',
                'venue' => 'Open Court, Proddatur',
                'locality' => 'Proddatur',
                'jitter' => [-0.009, -0.017],
                'status' => 'Completed',
                'events' => $this->periodEvents('basketball', [[16, 21], [22, 14]], $bbAway, $bbHome, 10),
            ],

            // ── Kabaddi: second half. Raids, tackles, bonus points and an all-out are
            //    different moves worth different amounts — the feed names each one.
            [
                'code' => 'DEMOKABAD1',
                'sport' => 'kabaddi',
                'home' => 'Kadapa Warriors', 'homeShort' => 'KDW',
                'away' => 'Kurnool Tigers', 'awayShort' => 'KNT',
                'homePlayers' => $kbHome, 'awayPlayers' => $kbAway,
                'format' => ['kind' => 'kabaddi', 'periods' => 2, 'periodLengthMin' => 20],
                'competition' => 'Standard 7s · 2 x 20 min',
                'venue' => 'Municipal Ground Mat, Kadapa',
                'locality' => 'Kadapa',
                'jitter' => [0.017, 0.005],
                'status' => 'Live',
                'events' => $this->periodEvents('kabaddi', [[24, 20], [11, 9]], $kbHome, $kbAway, 20),
            ],
            [
                'code' => 'DEMOKABAD2',
                'sport' => 'kabaddi',
                'home' => 'Badvel Bulls', 'homeShort' => 'BDB',
                'away' => 'Jammalamadugu Lions', 'awayShort' => 'JML',
                'homePlayers' => $kbAway, 'awayPlayers' => $kbHome,
                'format' => ['kind' => 'kabaddi', 'periods' => 2, 'periodLengthMin' => 10],
                'competition' => 'Short · 2 x 10 min',
                'venue' => 'School Ground, Badvel',
                'locality' => 'Badvel',
                'jitter' => [-0.014, 0.022],
                'status' => 'Completed',
                'events' => $this->periodEvents('kabaddi', [[18, 21], [19, 13]], $kbAway, $kbHome, 10),
            ],

            // ── Tennis: one set each way is the wrong shape for a demo — this one is a
            //    set up with a game in progress at 30-15, which is the only state that
            //    shows the point ladder doing its job.
            [
                'code' => 'DEMOTENNIS1',
                'sport' => 'tennis',
                'home' => 'Arjun Reddy', 'homeShort' => 'ARJ',
                'away' => 'Vikram Naidu', 'awayShort' => 'VIK',
                'homePlayers' => ['Arjun Reddy'], 'awayPlayers' => ['Vikram Naidu'],
                'format' => ['kind' => 'tennis', 'bestOf' => 3, 'gamesTo' => 6],
                'competition' => 'Singles · best of 3 sets',
                'venue' => 'Kadapa Tennis Club, Court 2',
                'locality' => 'Kadapa',
                'jitter' => [0.004, -0.023],
                'status' => 'Live',
                'events' => $this->tennisEvents([[6, 4]], [3, 2], [2, 1], 'Arjun Reddy', 'Vikram Naidu'),
            ],
            [
                'code' => 'DEMOTENNIS2',
                'sport' => 'tennis',
                'home' => 'Sneha Varma', 'homeShort' => 'SNE',
                'away' => 'Divya Rao', 'awayShort' => 'DIV',
                'homePlayers' => ['Sneha Varma'], 'awayPlayers' => ['Divya Rao'],
                'format' => ['kind' => 'tennis', 'bestOf' => 3, 'gamesTo' => 6],
                'competition' => 'Singles · best of 3 sets',
                'venue' => 'Kadapa Tennis Club, Court 1',
                'locality' => 'Kadapa',
                'jitter' => [0.024, 0.011],
                'status' => 'Completed',
                'events' => $this->tennisEvents([[6, 3], [4, 6], [6, 2]], null, null, 'Sneha Varma', 'Divya Rao'),
            ],

            // ── Table tennis: games to 11, best of 5, two games played and a third on.
            [
                'code' => 'DEMOTT1',
                'sport' => 'table_tennis',
                'home' => 'Sai Kiran', 'homeShort' => 'SAI',
                'away' => 'Rohit Varma', 'awayShort' => 'ROH',
                'homePlayers' => ['Sai Kiran'], 'awayPlayers' => ['Rohit Varma'],
                'format' => ['kind' => 'table_tennis', 'bestOf' => 5, 'pointsTo' => 11],
                'competition' => 'Singles · best of 5 to 11',
                'venue' => 'Indoor Stadium TT Hall, Kadapa',
                'locality' => 'Kadapa',
                'jitter' => [-0.006, 0.009],
                'status' => 'Live',
                'events' => $this->rallyEvents([[11, 8], [9, 11]], [7, 5], ['Sai Kiran'], ['Rohit Varma']),
            ],
            [
                'code' => 'DEMOTT2',
                'sport' => 'table_tennis',
                'home' => 'Pavan Teja', 'homeShort' => 'PAV',
                'away' => 'Akhil Sharma', 'awayShort' => 'AKH',
                'homePlayers' => ['Pavan Teja'], 'awayPlayers' => ['Akhil Sharma'],
                'format' => ['kind' => 'table_tennis', 'bestOf' => 5, 'pointsTo' => 11],
                'competition' => 'Singles · best of 5 to 11',
                'venue' => 'Youth Club, Proddatur',
                'locality' => 'Proddatur',
                'jitter' => [0.019, -0.013],
                'status' => 'Completed',
                'events' => $this->rallyEvents(
                    [[11, 7], [8, 11], [11, 9], [11, 6]], null,
                    ['Pavan Teja'], ['Akhil Sharma'],
                ),
            ],
        ];
    }

    // ─────────────────────────── event scripts ───────────────────────────

    /**
     * Volleyball / table tennis: a stream of rally points that REPLAYS into the given
     * sets.
     *
     * The one constraint that matters: the last point of a finished set belongs to the
     * side that won it. Otherwise the engine would close the set early and every later
     * point would land in the wrong one — the scoreline would still look right while
     * the set list underneath it was fiction.
     *
     * @param  array<int, array{0:int,1:int}>  $sets     finished sets, oldest first
     * @param  array{0:int,1:int}|null         $current  points in the set still being played
     * @return array<int, array<string, mixed>>
     */
    private function rallyEvents(array $sets, ?array $current, array $homePlayers, array $awayPlayers): array
    {
        $events = [];

        foreach ($sets as $set) {
            foreach ($this->rallyOrder($set[0], $set[1], true) as $side) {
                $events[] = $this->pointEvent($side, null, $homePlayers, $awayPlayers);
            }
        }

        if ($current !== null) {
            foreach ($this->rallyOrder($current[0], $current[1], false) as $side) {
                $events[] = $this->pointEvent($side, null, $homePlayers, $awayPlayers);
            }
        }

        return $events;
    }

    /**
     * The order the rallies fell in. Shuffled, because a real set isn't one side's
     * points then the other's — but with the winner's match point pinned last when the
     * set is finished.
     *
     * @return array<int, string>
     */
    private function rallyOrder(int $home, int $away, bool $closed): array
    {
        $winner = $home > $away ? 'home' : 'away';
        if ($closed) {
            $home > $away ? $home-- : $away--;
        }

        $order = array_merge(array_fill(0, $home, 'home'), array_fill(0, $away, 'away'));
        shuffle($order);

        if ($closed) {
            $order[] = $winner;
        }

        return $order;
    }

    /**
     * Basketball / kabaddi: points inside periods, with a `period` event closing each
     * one. The engine derives the quarter/half split from those markers, so the box
     * line on screen is a replay result rather than a stored number.
     *
     * @param  array<int, array{0:int,1:int}>  $periods  per-period totals, oldest first
     * @return array<int, array<string, mixed>>
     */
    private function periodEvents(
        string $sport,
        array $periods,
        array $homePlayers,
        array $awayPlayers,
        int $periodMinutes,
    ): array {
        $events = [];
        $minute = 0;

        foreach ($periods as $index => [$home, $away]) {
            if ($index > 0) {
                $events[] = [
                    'kind' => 'period',
                    'side' => null,
                    'minute' => $minute,
                    'note' => $sport === 'basketball' ? 'End of Q'.$index : 'Half time',
                ];
            }

            // Each side's points broken into the moves that produced them — a 22-point
            // quarter is threes, twos and free throws, not 22 identical taps.
            $bucket = [];
            foreach ($this->moves($sport, $home) as $detail) {
                $bucket[] = ['home', $detail];
            }
            foreach ($this->moves($sport, $away) as $detail) {
                $bucket[] = ['away', $detail];
            }
            shuffle($bucket);

            // Spread the period's scores across its clock, so a timeline reading the
            // minute never claims a 10-minute quarter took 22.
            $start = $index * $periodMinutes;
            $count = max(1, count($bucket));
            foreach (array_values($bucket) as $i => [$side, $detail]) {
                $minute = $start + (int) round((($i + 1) / ($count + 1)) * $periodMinutes);
                $events[] = $this->pointEvent($side, $detail, $homePlayers, $awayPlayers, $minute);
            }

            $minute = ($index + 1) * $periodMinutes;
        }

        return $events;
    }

    /**
     * Break a period total into the individual scoring moves that add up to it, using
     * only values the sport actually awards (SportRules decides what each is worth —
     * this just picks a plausible mix).
     *
     * @return array<int, string>  the `detail` of each point event
     */
    private function moves(string $sport, int $total): array
    {
        // [detail, value, weight] — weights make the mix look like the sport.
        $options = $sport === 'basketball'
            ? [['2', 2, 60], ['3', 3, 22], ['1', 1, 18]]
            : [['raid', 1, 42], ['tackle', 1, 30], ['bonus', 1, 16], ['all_out', 2, 9], ['super_raid', 3, 3]];

        $out = [];
        $left = $total;

        while ($left > 0) {
            $usable = array_values(array_filter($options, static fn (array $o): bool => $o[1] <= $left));
            $pick = $this->weighted($usable);
            $out[] = $pick[0];
            $left -= $pick[1];
        }

        return $out;
    }

    /**
     * Tennis: points that climb into games and games into sets, written as points
     * because that is all a tennis scorer ever taps.
     *
     * A game goes to whoever it belongs to in exactly four winning points, with the
     * loser's 0-2 points scattered before the last one — so no game can close early
     * and the set totals below come out exactly as asked.
     *
     * @param  array<int, array{0:int,1:int}>  $sets          finished sets, in games
     * @param  array{0:int,1:int}|null         $currentGames  games in the set in play
     * @param  array{0:int,1:int}|null         $currentPoints points in the game in play
     * @return array<int, array<string, mixed>>
     */
    private function tennisEvents(
        array $sets,
        ?array $currentGames,
        ?array $currentPoints,
        string $homeName,
        string $awayName,
    ): array {
        $events = [];
        $push = function (string $side) use (&$events, $homeName, $awayName): void {
            $events[] = $this->pointEvent($side, null, [$homeName], [$awayName]);
        };

        foreach ($sets as $set) {
            // Same pin as a rally set: the set winner takes the last game, or the engine
            // would award the set to the wrong player mid-sequence.
            foreach ($this->rallyOrder($set[0], $set[1], true) as $gameWinner) {
                foreach ($this->gamePoints($gameWinner) as $side) {
                    $push($side);
                }
            }
        }

        if ($currentGames !== null) {
            foreach ($this->rallyOrder($currentGames[0], $currentGames[1], false) as $gameWinner) {
                foreach ($this->gamePoints($gameWinner) as $side) {
                    $push($side);
                }
            }
        }

        if ($currentPoints !== null) {
            foreach ($this->rallyOrder($currentPoints[0], $currentPoints[1], false) as $side) {
                $push($side);
            }
        }

        return $events;
    }

    /**
     * One game's points. Four to the winner, up to two to the loser, winner last —
     * which is 40-0, 40-15 or 40-30, the three ways a game ends without deuce.
     *
     * @return array<int, string>
     */
    private function gamePoints(string $winner): array
    {
        $loser = $winner === 'home' ? 'away' : 'home';
        $conceded = mt_rand(0, 2);

        $order = array_merge(array_fill(0, 3, $winner), array_fill(0, $conceded, $loser));
        shuffle($order);
        $order[] = $winner;

        return $order;
    }

    /** @return array<string, mixed> */
    private function pointEvent(
        string $side,
        ?string $detail,
        array $homePlayers,
        array $awayPlayers,
        ?int $minute = null,
    ): array {
        $pool = $side === 'home' ? $homePlayers : $awayPlayers;

        return [
            'kind' => 'point',
            'side' => $side,
            'detail' => $detail,
            'player' => $this->scorer($pool),
            'minute' => $minute,
        ];
    }

    /**
     * Who scored. The front of a squad is picked far more often than the back, because
     * a flat random spread produces five players on identical points and a "top
     * scorers" panel that never has a top scorer.
     */
    private function scorer(array $players): ?string
    {
        if ($players === []) {
            return null;
        }

        $weighted = [];
        foreach (array_values($players) as $i => $name) {
            $weighted[] = [$name, 0, max(3, 30 - ($i * 5))];
        }

        return $this->weighted($weighted)[0];
    }

    /**
     * Pick one [value, …, weight] triple, weighted by its last element.
     *
     * @param  array<int, array{0:string,1:int,2:int}>  $options
     * @return array{0:string,1:int,2:int}
     */
    private function weighted(array $options): array
    {
        $total = array_sum(array_column($options, 2));
        $roll = mt_rand(1, max(1, $total));

        foreach ($options as $option) {
            $roll -= $option[2];
            if ($roll <= 0) {
                return $option;
            }
        }

        return $options[array_key_last($options)];
    }
}
