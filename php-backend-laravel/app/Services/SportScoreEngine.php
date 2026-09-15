<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LiveMatch;
use App\Models\MatchEvent;
use App\Services\Scoring\KabaddiMachine;
use App\Services\Scoring\RallyMachine;
use App\Services\Scoring\TennisMachine;
use App\Support\SportRules;
use Illuminate\Support\Collection;

/**
 * Replays a match's events into the scoreline for its sport.
 *
 * One deliberate constraint runs through all of it: the board is a pure function of the
 * event log. Nothing accumulates in place, nothing is patched incrementally — every
 * recompute starts from the first event. That is what makes undo trivially correct (drop
 * a row, replay) and what stops a dropped or double-sent request from leaving a scoreboard
 * that disagrees with its own timeline.
 *
 * The sport's rules live in state machines (tennis, rally sports, the kabaddi mat) so that
 * the insights replay walks the very same machine and can never tell a different story.
 *
 * Cricket is not handled here; it keeps its ball-by-ball pipeline.
 */
class SportScoreEngine
{
    /** Basketball's non-scoring box-score events. */
    public const BASKETBALL_STATS = ['rebound', 'assist', 'steal', 'block', 'foul', 'turnover'];

    /**
     * The whole board for a match, from its events, stamping each event with the score as
     * it stood after it.
     *
     * @param  Collection<int, MatchEvent>  $events
     * @return array{home: int, away: int, scoreText: string, state: array<string, mixed>, annotations: array<int, array<string, mixed>>}
     */
    public function compute(LiveMatch $match, Collection $events): array
    {
        $board = $this->replay($match, $events);

        foreach ($events as $event) {
            $stamp = $board['stamps'][$event->sequence] ?? null;
            if ($stamp !== null && ($event->home_score !== $stamp[0] || $event->away_score !== $stamp[1])) {
                $event->forceFill(['home_score' => $stamp[0], 'away_score' => $stamp[1]])->saveQuietly();
            }
        }
        unset($board['stamps']);

        return $board;
    }

    /**
     * The same replay with no writes — what a read path (the detail payload) uses.
     *
     * @param  Collection<int, MatchEvent>  $events
     * @return array{home: int, away: int, scoreText: string, state: array<string, mixed>, annotations: array<int, array<string, mixed>>, stamps: array<int, array{0: int, 1: int}>}
     */
    public function replay(LiveMatch $match, Collection $events): array
    {
        $sport = SportRules::normalise((string) ($match->sport ?: 'football'));
        $state = is_array($match->sport_state) ? $match->sport_state : [];
        $format = is_array($state['format'] ?? null) ? $state['format'] : [];

        return match (SportRules::family($sport)) {
            SportRules::POINTS => $sport === 'kabaddi'
                ? $this->kabaddi($events, $format, self::kabaddiTracksMat($state))
                : $this->basketball($sport, $events, $format),
            SportRules::SETS => $this->sets($sport, $events, $format),
            SportRules::TENNIS => $this->tennis($events, $format),
            default => $this->tally($events),
        };
    }

    /** Mat scoring is opt-in per match — see KabaddiMachine for why. */
    public static function kabaddiTracksMat(array $state): bool
    {
        return (bool) (is_array($state['rules'] ?? null) ? ($state['rules']['mat'] ?? false) : false);
    }

    /** @param Collection<int, MatchEvent> $events */
    private function tally(Collection $events): array
    {
        $home = 0;
        $away = 0;
        $stamps = [];

        foreach ($events as $event) {
            if ($this->tallyCountsFor($event, 'home')) {
                $home++;
            } elseif ($this->tallyCountsFor($event, 'away')) {
                $away++;
            }
            $stamps[$event->sequence] = [$home, $away];
        }

        return [
            'home' => $home,
            'away' => $away,
            'scoreText' => "{$home} - {$away}",
            'state' => [],
            'annotations' => [],
            'stamps' => $stamps,
        ];
    }

    /** An own goal credits the OTHER side — the classic scoreboard bug, in exactly one place. */
    private function tallyCountsFor(MatchEvent $event, string $side): bool
    {
        $other = $side === 'home' ? 'away' : 'home';

        return match ($event->kind) {
            MatchEvent::GOAL => $event->side === $side,
            MatchEvent::OWN_GOAL => $event->side === $other,
            MatchEvent::POINT => $event->side === $side,
            default => false,
        };
    }

    /**
     * Basketball: sum what each bucket was worth, split by quarter, and keep the box score —
     * points, rebounds, assists, steals, blocks, fouls and turnovers per player, plus team
     * fouls in the quarter (FIBA's penalty from the fifth) and timeouts in the half.
     *
     * @param  Collection<int, MatchEvent>  $events
     */
    private function basketball(string $sport, Collection $events, array $format): array
    {
        $home = 0;
        $away = 0;
        $period = 1;
        $periods = [];
        $periodHome = 0;
        $periodAway = 0;
        $teamFouls = ['home' => 0, 'away' => 0];
        $timeouts = ['home' => 0, 'away' => 0];
        $players = [];
        $team = ['home' => $this->blankBox(), 'away' => $this->blankBox()];
        $run = ['side' => null, 'points' => 0];
        $annotations = [];
        $stamps = [];
        $regulation = SportRules::periodCount($sport, $format);

        foreach ($events as $event) {
            $side = in_array($event->side, ['home', 'away'], true) ? $event->side : null;
            $tags = [];
            $value = 0;

            if ($event->kind === MatchEvent::PERIOD) {
                $periods[] = [$periodHome, $periodAway];
                $periodHome = 0;
                $periodAway = 0;
                $period++;
                $teamFouls = ['home' => 0, 'away' => 0];
                // Timeouts reset at half time and for each overtime.
                if ($period === intdiv($regulation, 2) + 1 || $period > $regulation) {
                    $timeouts = ['home' => 0, 'away' => 0];
                }
                $tags[] = 'period';
            } elseif ($event->kind === MatchEvent::POINT && $side !== null) {
                $value = SportRules::pointValue($sport, $event->detail);
                if ($side === 'home') {
                    $home += $value;
                    $periodHome += $value;
                } else {
                    $away += $value;
                    $periodAway += $value;
                }
                $key = match ($value) { 3 => 'fg3', 1 => 'ft', default => 'fg2' };
                $team[$side]['pts'] += $value;
                $team[$side][$key]++;
                $this->bump($players, $side, $event->player_name, ['pts' => $value, $key => 1]);
                if ($value === 3) {
                    $tags[] = 'three';
                }
                if ($run['side'] === $side) {
                    $run['points'] += $value;
                } else {
                    $run = ['side' => $side, 'points' => $value];
                }
                // An assist recorded on the basket itself.
                if (trim((string) $event->related_name) !== '' && $value > 1) {
                    $team[$side]['ast']++;
                    $this->bump($players, $side, $event->related_name, ['ast' => 1]);
                }
            } elseif (in_array($event->kind, self::BASKETBALL_STATS, true) && $side !== null) {
                $key = match ($event->kind) {
                    'rebound' => 'reb', 'assist' => 'ast', 'steal' => 'stl',
                    'block' => 'blk', 'foul' => 'pf', default => 'to',
                };
                $team[$side][$key]++;
                $this->bump($players, $side, $event->player_name, [$key => 1]);
                if ($event->kind === 'foul') {
                    $teamFouls[$side]++;
                    $fouls = $this->playerStat($players, $side, $event->player_name, 'pf');
                    if ($fouls >= (int) ($format['foulOut'] ?? 5)) {
                        $tags[] = 'fouled_out';
                    }
                    if ($teamFouls[$side] === 5) {
                        $tags[] = 'penalty';
                    }
                }
            } elseif ($event->kind === 'timeout' && $side !== null) {
                $timeouts[$side]++;
                $team[$side]['timeouts']++;
            }

            $stamps[$event->sequence] = [$home, $away];
            $annotations[$event->sequence] = [
                'value' => $value,
                'tags' => $tags,
                'line' => "{$home}–{$away}",
                'period' => $period,
            ];
        }

        $periods[] = [$periodHome, $periodAway];

        return [
            'home' => $home,
            'away' => $away,
            'scoreText' => "{$home} - {$away}",
            'state' => [
                'period' => $period,
                'period_label' => $period > $regulation ? 'OT'.($period - $regulation > 1 ? ($period - $regulation) : '') : SportRules::periodLabel($sport, $period),
                'periods' => $periods,
                'regulation_periods' => $regulation,
                'team_fouls' => [$teamFouls['home'], $teamFouls['away']],
                'timeouts' => [$timeouts['home'], $timeouts['away']],
                'timeouts_allowed' => $period <= intdiv($regulation, 2) ? 2 : ($period > $regulation ? 1 : 3),
                'run' => $run['side'] !== null && $run['points'] >= 6 ? $run : null,
                'box' => [
                    'team' => $team,
                    'players' => $this->flattenPlayers($players, 'pts'),
                ],
            ],
            'annotations' => $annotations,
            'stamps' => $stamps,
        ];
    }

    /** @return array<string, int> */
    private function blankBox(): array
    {
        return ['pts' => 0, 'fg2' => 0, 'fg3' => 0, 'ft' => 0, 'reb' => 0, 'ast' => 0,
            'stl' => 0, 'blk' => 0, 'pf' => 0, 'to' => 0, 'timeouts' => 0];
    }

    /** @param array<string, int> $by */
    private function bump(array &$players, string $side, ?string $name, array $by): void
    {
        $name = trim((string) $name);
        if ($name === '') {
            return;
        }
        $row = $players[$side][$name] ?? $this->blankBox();
        foreach ($by as $k => $v) {
            $row[$k] = ($row[$k] ?? 0) + $v;
        }
        $players[$side][$name] = $row;
    }

    private function playerStat(array $players, string $side, ?string $name, string $key): int
    {
        return (int) ($players[$side][trim((string) $name)][$key] ?? 0);
    }

    /** @return array<int, array<string, mixed>> */
    private function flattenPlayers(array $players, string $sortKey): array
    {
        $out = [];
        foreach ($players as $side => $rows) {
            foreach ($rows as $name => $row) {
                unset($row['timeouts']);
                $out[] = ['side' => $side, 'name' => (string) $name] + $row;
            }
        }
        usort($out, static fn (array $a, array $b): int => ($b[$sortKey] <=> $a[$sortKey]) ?: strcmp($a['name'], $b['name']));

        return $out;
    }

    /**
     * Kabaddi: raid outcomes replayed across the mat — see KabaddiMachine.
     *
     * @param  Collection<int, MatchEvent>  $events
     */
    private function kabaddi(Collection $events, array $format, bool $trackMat): array
    {
        $machine = new KabaddiMachine($format, $trackMat);
        $period = 1;
        $periods = [];
        $periodHome = 0;
        $periodAway = 0;
        $timeouts = ['home' => 0, 'away' => 0];
        $annotations = [];
        $stamps = [];

        foreach ($events as $event) {
            $side = in_array($event->side, ['home', 'away'], true) ? $event->side : null;
            $value = 0;
            $tags = [];
            $before = $machine->score;

            if ($event->kind === MatchEvent::PERIOD) {
                $periods[] = [$periodHome, $periodAway];
                $periodHome = 0;
                $periodAway = 0;
                $period++;
                $machine->period();
                $tags[] = 'period';
            } elseif ($event->kind === 'serve' && $side !== null) {
                $machine->setRaiding($side);
            } elseif ($event->kind === 'timeout' && $side !== null) {
                $timeouts[$side]++;
            } elseif (in_array($event->kind, [MatchEvent::POINT, 'raid'], true)) {
                $r = $machine->apply($event->kind, $side, (string) $event->detail, (string) $event->player_name);
                $value = $r['value'];
                $tags = $r['tags'];
            }

            $periodHome += $machine->score['home'] - $before['home'];
            $periodAway += $machine->score['away'] - $before['away'];
            $stamps[$event->sequence] = [$machine->score['home'], $machine->score['away']];
            $annotations[$event->sequence] = [
                'value' => $value,
                'tags' => $tags,
                'line' => $machine->score['home'].'–'.$machine->score['away'],
                'period' => $period,
                'mat' => $trackMat ? [$machine->onMat['home'], $machine->onMat['away']] : null,
            ];
        }

        $periods[] = [$periodHome, $periodAway];
        $home = $machine->score['home'];
        $away = $machine->score['away'];

        return [
            'home' => $home,
            'away' => $away,
            'scoreText' => "{$home} - {$away}",
            'state' => [
                'period' => $period,
                'period_label' => SportRules::periodLabel('kabaddi', $period),
                'periods' => $periods,
                'timeouts' => [$timeouts['home'], $timeouts['away']],
            ] + $machine->snapshot(),
            'annotations' => $annotations,
            'stamps' => $stamps,
        ];
    }

    /**
     * Volleyball, table tennis, badminton: rally points fill a set, a won set resets the
     * rally count, and the SCORELINE is sets won — 25-23, 20-25, 15-11 is a 2-1 win, never
     * "60-59".
     *
     * @param  Collection<int, MatchEvent>  $events
     */
    private function sets(string $sport, Collection $events, array $format): array
    {
        $machine = new RallyMachine($sport, $format);
        $annotations = [];
        $stamps = [];

        foreach ($events as $event) {
            $side = in_array($event->side, ['home', 'away'], true) ? $event->side : null;
            $note = ['value' => 0, 'tags' => [], 'set_index' => $machine->index];

            if ($event->kind === MatchEvent::POINT && $side !== null) {
                // Rallies after the match is decided are ignored rather than silently
                // starting a set that shouldn't exist.
                $wasDecided = $machine->decided();
                $r = $machine->point($side, (string) $event->detail);
                $note['value'] = $wasDecided ? 0 : 1;
                $note['set_index'] = $r['set_index'];
                $note['line'] = $r['set_home'].'–'.$r['set_away'];
                $note['server'] = $r['server'];
                if ($r['closes'] !== null) {
                    $note['tags'][] = $r['closes'];
                }
                if ($r['set_point'] !== null) {
                    // A set point converted, or one saved.
                    $note['tags'][] = $r['set_point'] === $side ? 'converted' : 'saved';
                }
                if ($r['deuce']) {
                    $note['tags'][] = 'deuce';
                }
                if ($r['side_out']) {
                    $note['tags'][] = 'side_out';
                }
                if ($r['change_ends']) {
                    $note['tags'][] = 'change_ends';
                }
                if ($r['interval']) {
                    $note['tags'][] = 'interval';
                }
                if (strtolower((string) $event->detail) === 'ace') {
                    $note['tags'][] = 'ace';
                }
            } elseif ($event->kind === 'serve' && $side !== null) {
                $machine->setServer($side);
            } elseif ($event->kind === 'timeout' && $side !== null) {
                $machine->timeout($side);
                $note['tags'][] = 'timeout';
                $note['line'] = $machine->home.'–'.$machine->away;
            }

            // A timeline row for a rally sport shows SETS at that moment, matching what the
            // hero shows — not the rally count, which resets and would read as the score
            // going backwards.
            $stamps[$event->sequence] = [$machine->setsHome, $machine->setsAway];
            $annotations[$event->sequence] = $note;
        }

        return [
            'home' => $machine->setsHome,
            'away' => $machine->setsAway,
            'scoreText' => "{$machine->setsHome} - {$machine->setsAway}",
            'state' => $machine->snapshot(),
            'annotations' => $annotations,
            'stamps' => $stamps,
        ];
    }

    /**
     * Tennis: points climb 0/15/30/40, deuce needs two clear, 6–6 goes to a tie-break, and the
     * serve alternates game by game. See TennisMachine.
     *
     * @param  Collection<int, MatchEvent>  $events
     */
    private function tennis(Collection $events, array $format): array
    {
        $machine = new TennisMachine($format);
        $annotations = [];
        $stamps = [];

        foreach ($events as $event) {
            $side = in_array($event->side, ['home', 'away'], true) ? $event->side : null;
            $note = ['value' => 0, 'tags' => []];

            if ($event->kind === MatchEvent::POINT && $side !== null) {
                $r = $machine->point($side, (string) $event->detail);
                $note['value'] = 1;
                $note['server'] = $r['server'];
                $labels = $machine->pointLabels();
                $note['line'] = $r['closes'] === null
                    ? ($machine->tiebreak ? "TB {$labels[0]}–{$labels[1]}" : "{$labels[0]}–{$labels[1]}")
                    : "{$machine->gamesHome}–{$machine->gamesAway}";
                if ($r['closes'] !== null) {
                    $note['tags'][] = $r['closes'];
                    if ($r['server'] !== null && $side !== $r['server'] && ! $r['tiebreak']) {
                        $note['tags'][] = 'break';
                    }
                    if ($r['tiebreak']) {
                        $note['tags'][] = 'tiebreak';
                    }
                }
                if ($r['break_points'] > 0) {
                    $note['tags'][] = $side === $r['server'] ? 'break_point_saved' : 'break_point';
                }
                if ($r['deuce']) {
                    $note['tags'][] = 'deuce';
                }
                $detail = strtolower((string) $event->detail);
                if (in_array($detail, ['ace', 'double_fault', 'winner', 'error'], true)) {
                    $note['tags'][] = $detail;
                }
            } elseif ($event->kind === 'serve' && $side !== null) {
                $machine->setServer($side);
            }

            $stamps[$event->sequence] = [$machine->setsHome, $machine->setsAway];
            $annotations[$event->sequence] = $note;
        }

        return [
            'home' => $machine->setsHome,
            'away' => $machine->setsAway,
            'scoreText' => "{$machine->setsHome} - {$machine->setsAway}",
            'state' => $machine->snapshot(),
            'annotations' => $annotations,
            'stamps' => $stamps,
        ];
    }
}
