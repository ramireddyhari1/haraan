<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LiveMatch;
use App\Models\MatchEvent;
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
 * Cricket is not handled here; it keeps its ball-by-ball pipeline.
 */
class SportScoreEngine
{
    /**
     * The whole board for a match, from its events.
     *
     * @param  Collection<int, MatchEvent>  $events
     * @return array{home: int, away: int, scoreText: string, state: array<string, mixed>}
     */
    public function compute(LiveMatch $match, Collection $events): array
    {
        $sport = SportRules::normalise((string) ($match->sport ?: 'football'));
        $format = $this->format($match);

        return match (SportRules::family($sport)) {
            SportRules::POINTS => $this->points($sport, $events),
            SportRules::SETS => $this->sets($sport, $events, $format),
            SportRules::TENNIS => $this->tennis($sport, $events, $format),
            default => $this->tally($events),
        };
    }

    /** The format the creator chose, stored verbatim at creation. */
    private function format(LiveMatch $match): array
    {
        $state = is_array($match->sport_state) ? $match->sport_state : [];
        $format = $state['format'] ?? [];

        return is_array($format) ? $format : [];
    }

    /**
     * Football: one goal is one point. An own goal credits the OTHER side — the classic
     * scoreboard bug, so it lives in exactly one place.
     *
     * @param  Collection<int, MatchEvent>  $events
     */
    private function tally(Collection $events): array
    {
        $home = 0;
        $away = 0;

        foreach ($events as $event) {
            if ($this->tallyCountsFor($event, 'home')) {
                $home++;
            } elseif ($this->tallyCountsFor($event, 'away')) {
                $away++;
            }

            $this->stamp($event, $home, $away);
        }

        return [
            'home' => $home,
            'away' => $away,
            'scoreText' => "{$home} - {$away}",
            'state' => [],
        ];
    }

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
     * Basketball and kabaddi: sum the value each point carried.
     *
     * Per-period splits come free from the replay, so the board can show a quarter-by-
     * quarter line without a second pass or a stored counter that could drift.
     *
     * @param  Collection<int, MatchEvent>  $events
     */
    private function points(string $sport, Collection $events): array
    {
        $home = 0;
        $away = 0;
        $period = 1;
        $periods = [];   // [[homeInPeriod, awayInPeriod], …]
        $periodHome = 0;
        $periodAway = 0;

        foreach ($events as $event) {
            // A 'period' event closes the current quarter/half and opens the next.
            if ($event->kind === MatchEvent::PERIOD) {
                $periods[] = [$periodHome, $periodAway];
                $periodHome = 0;
                $periodAway = 0;
                $period++;
                $this->stamp($event, $home, $away);

                continue;
            }

            if ($event->kind === MatchEvent::POINT && in_array($event->side, ['home', 'away'], true)) {
                $value = SportRules::pointValue($sport, $event->detail);
                if ($event->side === 'home') {
                    $home += $value;
                    $periodHome += $value;
                } else {
                    $away += $value;
                    $periodAway += $value;
                }
            }

            $this->stamp($event, $home, $away);
        }

        $periods[] = [$periodHome, $periodAway];

        return [
            'home' => $home,
            'away' => $away,
            'scoreText' => "{$home} - {$away}",
            'state' => [
                'period' => $period,
                'period_label' => SportRules::periodLabel($sport, $period),
                'periods' => $periods,
            ],
        ];
    }

    /**
     * Volleyball, table tennis, badminton: rally points fill a set, a won set resets the
     * rally count, and the SCORELINE is sets won.
     *
     * This is why the scoreline can't be a running point count: 25-23, 20-25, 15-11 is a
     * 2-1 win, and a board that added those up to "60-59" would be describing a game
     * nobody played.
     *
     * @param  Collection<int, MatchEvent>  $events
     */
    private function sets(string $sport, Collection $events, array $format): array
    {
        $bestOf = (int) ($format['bestOf'] ?? SportRules::defaultBestOf($sport));
        $completed = [];        // finished sets: [[home, away], …]
        $setHome = 0;
        $setAway = 0;
        $setsHome = 0;
        $setsAway = 0;
        $index = 0;             // which set is in progress (0-based)

        foreach ($events as $event) {
            if ($event->kind === MatchEvent::POINT && in_array($event->side, ['home', 'away'], true)) {
                // Points landing after the match is decided are ignored rather than
                // silently starting a set that shouldn't exist.
                $decided = $setsHome > intdiv($bestOf, 2) || $setsAway > intdiv($bestOf, 2);
                if (! $decided) {
                    if ($event->side === 'home') {
                        $setHome++;
                    } else {
                        $setAway++;
                    }

                    $target = SportRules::setTarget($sport, $index, $format);
                    if (SportRules::setIsWon($setHome, $setAway, $target)) {
                        $completed[] = [$setHome, $setAway];
                        if ($setHome > $setAway) {
                            $setsHome++;
                        } else {
                            $setsAway++;
                        }
                        $setHome = 0;
                        $setAway = 0;
                        $index++;
                    }
                }
            }

            // A timeline row for a rally sport shows SETS at that moment, matching what
            // the hero shows — not the rally count, which resets and would read as the
            // score going backwards.
            $this->stamp($event, $setsHome, $setsAway);
        }

        $target = SportRules::setTarget($sport, $index, $format);

        return [
            'home' => $setsHome,
            'away' => $setsAway,
            'scoreText' => "{$setsHome} - {$setsAway}",
            'state' => [
                'best_of' => $bestOf,
                'set_noun' => SportRules::setNoun($sport),
                'sets' => $completed,
                'current' => [$setHome, $setAway],
                'set_index' => $index,
                'target' => $target['target'],
                'cap' => $target['cap'],
                // Rally scoring: whoever won the last point serves the next one.
                'serving' => $this->lastPointSide($events),
            ],
        ];
    }

    /**
     * Tennis: points climb 0/15/30/40, deuce needs two clear, games make a set, sets make
     * the match. The scoreline is sets; the games and the live point ladder ride in state.
     *
     * @param  Collection<int, MatchEvent>  $events
     */
    private function tennis(string $sport, Collection $events, array $format): array
    {
        $bestOf = (int) ($format['bestOf'] ?? 3);
        $gamesToSet = (int) ($format['gamesTo'] ?? 6);

        $pointHome = 0;
        $pointAway = 0;
        $gameHome = 0;
        $gameAway = 0;
        $setsHome = 0;
        $setsAway = 0;
        $completed = [];

        foreach ($events as $event) {
            if ($event->kind === MatchEvent::POINT && in_array($event->side, ['home', 'away'], true)) {
                if ($event->side === 'home') {
                    $pointHome++;
                } else {
                    $pointAway++;
                }

                // Game won: 4+ points and two clear (which is exactly what "deuce, then
                // advantage, then game" means once you stop naming the numbers).
                if (max($pointHome, $pointAway) >= 4 && abs($pointHome - $pointAway) >= 2) {
                    if ($pointHome > $pointAway) {
                        $gameHome++;
                    } else {
                        $gameAway++;
                    }
                    $pointHome = 0;
                    $pointAway = 0;

                    if (max($gameHome, $gameAway) >= $gamesToSet && abs($gameHome - $gameAway) >= 2) {
                        $completed[] = [$gameHome, $gameAway];
                        if ($gameHome > $gameAway) {
                            $setsHome++;
                        } else {
                            $setsAway++;
                        }
                        $gameHome = 0;
                        $gameAway = 0;
                    }
                }
            }

            $this->stamp($event, $setsHome, $setsAway);
        }

        return [
            'home' => $setsHome,
            'away' => $setsAway,
            'scoreText' => "{$setsHome} - {$setsAway}",
            'state' => [
                'best_of' => $bestOf,
                'set_noun' => 'Set',
                'sets' => $completed,
                'games' => [$gameHome, $gameAway],
                'points' => [
                    SportRules::tennisPointLabel($pointHome, $pointAway),
                    SportRules::tennisPointLabel($pointAway, $pointHome),
                ],
                'serving' => $this->lastPointSide($events),
            ],
        ];
    }

    /** Who won the most recent rally — the server for the next one. */
    private function lastPointSide(Collection $events): ?string
    {
        $last = $events->last(fn (MatchEvent $e): bool => $e->kind === MatchEvent::POINT
            && in_array($e->side, ['home', 'away'], true));

        return $last?->side;
    }

    /**
     * Stamp the score AS IT STOOD after this event, so a timeline row renders without
     * replaying the feed. Written only when it actually changed — this runs on every
     * event of every recompute.
     */
    private function stamp(MatchEvent $event, int $home, int $away): void
    {
        if ($event->home_score !== $home || $event->away_score !== $away) {
            $event->forceFill(['home_score' => $home, 'away_score' => $away])->saveQuietly();
        }
    }
}
