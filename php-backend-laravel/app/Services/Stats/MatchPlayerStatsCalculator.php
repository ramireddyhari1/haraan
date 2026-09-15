<?php

declare(strict_types=1);

namespace App\Services\Stats;

use App\Models\LiveMatch;
use App\Models\MatchEvent;
use App\Models\User;
use App\Services\CareerBattingService;
use App\Services\SportScoreEngine;
use App\Support\SportRules;
use Illuminate\Support\Collection;

/**
 * Every player's figures for ONE match, derived from the match's own log and nothing else.
 *
 * This is the single calculator behind the player-stats chain:
 *
 *     event log  →  player  →  match stats  →  insights  →  career
 *
 * Cricket replays `match_actions` through CareerBattingService (the same attribution as the
 * live scorecard). Every other sport replays `match_events` through SportScoreEngine and its
 * rule machines, so a basketball player's points here are the points on the box score, a
 * raider's points are the points on the kabaddi board, and neither can drift from the score.
 *
 * Honesty rules, the same ones the boards follow:
 *  - A figure exists only if the scorer recorded it. No stat is estimated or padded.
 *  - Every squad member gets a row (`played`), so an appearance counts even with no stats.
 *  - A player is identified by the squad id (== users.player_id) when the scorer picked a
 *    registered player; a typed-in name becomes a guest row that careers never absorb.
 *  - Singles racket sports attribute the side's rally figures to the side's one player; in
 *    doubles only points that name a player are attributed.
 */
final class MatchPlayerStatsCalculator
{
    public function __construct(private readonly SportScoreEngine $engine) {}

    /**
     * @return array<int, array{player_key: string, player_id: string|null, user_id: int|null,
     *     player_name: string, side: string, sport: string, played: bool, result: string|null,
     *     stats: array<string, mixed>}>
     */
    public function forMatch(LiveMatch $match): array
    {
        $sport = SportRules::normalise((string) ($match->sport ?: 'cricket'));
        $roster = new MatchRoster($match);

        if ($sport === 'cricket') {
            $this->cricket($match, $roster);
        } else {
            $events = MatchEvent::query()->where('live_match_id', $match->id)->inOrder()->get();
            match (SportRules::family($sport)) {
                SportRules::TALLY => $this->football($events, $roster),
                SportRules::POINTS => $sport === 'kabaddi'
                    ? $this->kabaddi($match, $events, $roster)
                    : $this->basketball($match, $events, $roster),
                SportRules::SETS, SportRules::TENNIS => $this->racket($match, $sport, $events, $roster),
                default => null,
            };
        }

        $result = self::resultFor($match);

        return array_map(static function (array $row) use ($sport, $result): array {
            $row['sport'] = $sport;
            $row['result'] = $result === null ? null : ($result === 'draw' ? 'draw' : ($result === $row['side'] ? 'win' : 'loss'));

            return $row;
        }, $roster->rows());
    }

    /**
     * The finished match's winner side, 'draw', or null when it isn't finished. Rally and
     * tennis scorelines are sets won; a level scoreline in those is not a result.
     */
    public static function resultFor(LiveMatch $match): ?string
    {
        if (! $match->isFinished()) {
            return null;
        }
        $home = (int) $match->home_score;
        $away = (int) $match->away_score;
        $sport = SportRules::normalise((string) ($match->sport ?: 'cricket'));

        if ($sport === 'cricket') {
            // Cricket's scorer writes the result into status: "KDW won by 3 wickets",
            // "Match tied". The team is named by full name or short code, whichever was set.
            $text = strtolower(trim((string) $match->status));
            if (str_contains($text, 'tied') || str_contains($text, 'no result')) {
                return 'draw';
            }
            if (! str_contains($text, ' won by ')) {
                return null;
            }
            $winner = trim(strstr($text, ' won by ', true));
            foreach (['home' => [$match->home_full, $match->home], 'away' => [$match->away_full, $match->away]] as $side => $names) {
                foreach ($names as $n) {
                    if (trim((string) $n) !== '' && strtolower(trim((string) $n)) === $winner) {
                        return $side;
                    }
                }
            }

            return null;
        }

        if ($home === $away) {
            return in_array(SportRules::family($sport), [SportRules::SETS, SportRules::TENNIS], true) ? null : 'draw';
        }

        return $home > $away ? 'home' : 'away';
    }

    // ------------------------------------------------------------------ cricket

    private function cricket(LiveMatch $match, MatchRoster $roster): void
    {
        foreach (CareerBattingService::replayMatch($match) as $inningsNo => $inn) {
            foreach ($inn['batting'] as $pid => $t) {
                if ($t['balls'] <= 0 && ! $t['out']) {
                    continue; // at the crease but never faced — not an innings
                }
                $roster->mergeById($pid, $t['name'] ?? null, function (array $s) use ($t, $inningsNo): array {
                    $bat = $s['batting'] ?? ['innings' => [], 'runs' => 0, 'balls' => 0, 'fours' => 0, 'sixes' => 0, 'outs' => 0, 'zones' => []];
                    $bat['innings'][] = ['no' => $inningsNo + 1, 'runs' => $t['runs'], 'balls' => $t['balls'],
                        'fours' => $t['fours'], 'sixes' => $t['sixes'], 'out' => (bool) $t['out']];
                    $bat['runs'] += $t['runs'];
                    $bat['balls'] += $t['balls'];
                    $bat['fours'] += $t['fours'];
                    $bat['sixes'] += $t['sixes'];
                    $bat['outs'] += $t['out'] ? 1 : 0;
                    foreach ($t['zones'] ?? [] as $z => $zt) {
                        $bat['zones'][$z] ??= ['shots' => 0, 'fours' => 0, 'sixes' => 0, 'runs' => 0];
                        foreach ($zt as $k => $v) {
                            $bat['zones'][$z][$k] += $v;
                        }
                    }
                    $s['batting'] = $bat;

                    return $s;
                });
            }
            foreach ($inn['bowling'] as $pid => $b) {
                if ($b['balls'] <= 0 && $b['wickets'] <= 0) {
                    continue;
                }
                $roster->mergeById($pid, $b['name'] ?? null, function (array $s) use ($b, $inningsNo): array {
                    $bowl = $s['bowling'] ?? ['innings' => [], 'balls' => 0, 'runs' => 0, 'wickets' => 0, 'maidens' => 0];
                    $bowl['innings'][] = ['no' => $inningsNo + 1, 'balls' => $b['balls'], 'runs' => $b['runs'],
                        'wickets' => $b['wickets'], 'maidens' => $b['maidens'] ?? 0];
                    $bowl['balls'] += $b['balls'];
                    $bowl['runs'] += $b['runs'];
                    $bowl['wickets'] += $b['wickets'];
                    $bowl['maidens'] += $b['maidens'] ?? 0;
                    $s['bowling'] = $bowl;

                    return $s;
                });
            }
            foreach ($inn['fielding'] ?? [] as $pid => $f) {
                $roster->mergeById($pid, $f['name'] ?? null, function (array $s) use ($f): array {
                    $fl = $s['fielding'] ?? ['catches' => 0, 'run_outs' => 0, 'stumpings' => 0];
                    $fl['catches'] += $f['catches'];
                    $fl['run_outs'] += $f['run_outs'];
                    $fl['stumpings'] += $f['stumpings'];
                    $s['fielding'] = $fl;

                    return $s;
                });
            }
        }
    }

    // ----------------------------------------------------------------- football

    /** @param Collection<int, MatchEvent> $events */
    private function football(Collection $events, MatchRoster $roster): void
    {
        $yellows = [];

        foreach ($events as $e) {
            $side = in_array($e->side, ['home', 'away'], true) ? $e->side : null;
            if ($side === null) {
                continue;
            }

            switch ($e->kind) {
                case MatchEvent::GOAL:
                    $roster->bump($side, $e->player_name, $e->player_id, ['goals' => 1]);
                    if (trim((string) $e->related_name) !== '') {
                        $roster->bump($side, $e->related_name, null, ['assists' => 1]);
                    }
                    break;
                case MatchEvent::ASSIST:
                    $roster->bump($side, $e->player_name, $e->player_id, ['assists' => 1]);
                    break;
                case MatchEvent::OWN_GOAL:
                    // The player's own side conceded it; it is never their goal.
                    $roster->bump($side, $e->player_name, $e->player_id, ['own_goals' => 1]);
                    break;
                case MatchEvent::YELLOW:
                    $key = $side . '|' . strtolower(trim((string) $e->player_name));
                    $yellows[$key] = ($yellows[$key] ?? 0) + 1;
                    $roster->bump($side, $e->player_name, $e->player_id, ['yellow_cards' => 1]);
                    // A second yellow is a sending-off, whether or not the scorer also tapped red.
                    if ($yellows[$key] === 2) {
                        $roster->bump($side, $e->player_name, $e->player_id, ['red_cards' => 1]);
                    }
                    break;
                case MatchEvent::RED:
                    $key = $side . '|' . strtolower(trim((string) $e->player_name));
                    if (($yellows[$key] ?? 0) < 2) {
                        $roster->bump($side, $e->player_name, $e->player_id, ['red_cards' => 1]);
                    }
                    break;
                case 'save':
                    $roster->bump($side, $e->player_name, $e->player_id, ['saves' => 1]);
                    break;
            }
        }
    }

    // --------------------------------------------------------------- basketball

    /** @param Collection<int, MatchEvent> $events */
    private function basketball(LiveMatch $match, Collection $events, MatchRoster $roster): void
    {
        $board = $this->engine->replay($match, $events);
        foreach ($board['state']['box']['players'] ?? [] as $p) {
            $side = (string) $p['side'];
            $roster->bump($side, (string) $p['name'], null, [
                'points' => (int) $p['pts'], 'two_pointers' => (int) $p['fg2'], 'three_pointers' => (int) $p['fg3'],
                'free_throws' => (int) $p['ft'], 'rebounds' => (int) $p['reb'], 'assists' => (int) $p['ast'],
                'steals' => (int) $p['stl'], 'blocks' => (int) $p['blk'], 'fouls' => (int) $p['pf'],
                'turnovers' => (int) $p['to'],
            ]);
        }
    }

    // ------------------------------------------------------------------ kabaddi

    /** @param Collection<int, MatchEvent> $events */
    private function kabaddi(LiveMatch $match, Collection $events, MatchRoster $roster): void
    {
        $board = $this->engine->replay($match, $events);
        foreach ($board['state']['kabaddi_players'] ?? [] as $p) {
            $roster->bump((string) $p['side'], (string) $p['name'], null, [
                'points' => (int) $p['total'], 'raid_points' => (int) $p['raid_points'],
                'bonus_points' => (int) $p['bonus_points'], 'tackle_points' => (int) $p['tackle_points'],
                'raids' => (int) $p['raids'], 'super_raids' => (int) $p['super_raids'],
                'super_tackles' => (int) $p['super_tackles'],
            ]);
        }
    }

    // ------------------------------------------- volleyball, TT, badminton, tennis

    /** @param Collection<int, MatchEvent> $events */
    private function racket(LiveMatch $match, string $sport, Collection $events, MatchRoster $roster): void
    {
        $board = $this->engine->replay($match, $events);
        $state = $board['state'];
        $isTennis = $sport === 'tennis';
        $sideStats = $isTennis ? ($state['tennis_stats'] ?? []) : ($state['rally_stats'] ?? []);

        // Points that name the player who won them.
        $named = ['home' => [], 'away' => []];
        foreach ($events as $e) {
            if ($e->kind !== MatchEvent::POINT || ! in_array($e->side, ['home', 'away'], true)) {
                continue;
            }
            $name = trim((string) $e->player_name);
            if ($name === '') {
                continue;
            }
            $by = ['points_won' => 1];
            $detail = strtolower((string) $e->detail);
            if ($detail === 'ace') {
                $by['aces'] = 1;
            } elseif ($detail === 'winner' || $detail === 'kill') {
                $by['winners'] = 1;
            } elseif ($detail === 'block') {
                $by['blocks'] = 1;
            }
            $roster->bump($e->side, $name, $e->player_id, $by);
            $named[$e->side][] = $name;
        }

        foreach (['home', 'away'] as $idx => $side) {
            $members = $roster->sideMembers($side);
            // Singles: the side IS the player, so the side's figures are theirs.
            if (count($members) !== 1 || $named[$side] !== []) {
                continue;
            }
            $s = $sideStats[$side] ?? [];
            $sets = $state['sets'] ?? [];
            $by = [
                'points_won' => (int) ($s['points_won'] ?? 0),
                'aces' => (int) ($s['aces'] ?? 0),
                ($isTennis ? 'sets_won' : 'games_won') => $idx === 0 ? (int) $match->home_score : (int) $match->away_score,
            ];
            if ($isTennis) {
                $by += [
                    'games_won' => array_sum(array_map(static fn (array $set): int => (int) $set[$idx], $sets)),
                    'double_faults' => (int) ($s['double_faults'] ?? 0),
                    'breaks' => (int) ($s['breaks'] ?? 0),
                    'service_games_held' => (int) ($s['service_games_held'] ?? 0),
                    'service_games' => (int) ($s['service_games'] ?? 0),
                ];
            } else {
                $by['serve_points_won'] = (int) ($s['serve_points_won'] ?? 0);
                $by['serve_points_played'] = (int) ($s['serve_points_played'] ?? 0);
            }
            $roster->bumpKey($members[0], array_filter($by, static fn (int $v): bool => $v !== 0));
        }
    }
}
