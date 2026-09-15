<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Models\MatchEvent;
use App\Services\Insights\InsightContext as C;
use App\Services\MatchEventRecorder;

/**
 * Football: goals, who made them, and how the scoreline got where it is.
 *
 * Football's story is the ORDER of goals — an opener, an equaliser, a winner — so the
 * tags are about that order, not about volume. Assists come from the goal's recorded
 * assister; cards from the booking events. Possession, passes and xG are never recorded
 * by the scorer, so they are named as untracked instead of drawn as zeroes.
 */
final class FootballInsights implements SportInsightBuilder
{
    public function build(InsightContext $ctx): array
    {
        $rows = [];
        $ensure = function (string $side, string $name) use (&$rows): string {
            $key = $side.'|'.mb_strtolower($name);
            $rows[$key] ??= [
                'side' => $side, 'name' => $name, 'goals' => 0, 'assists' => 0,
                'yellow' => 0, 'red' => 0, 'own' => 0, 'opener' => false, 'winner' => false,
                'equalisers' => 0, 'minutes' => [],
            ];

            return $key;
        };

        $goals = $ctx->moments;
        $winner = $ctx->winner();

        // The winning goal: the one that took the winner one clear of the loser's final tally.
        $winningGoalSeq = null;
        if ($winner !== null) {
            $loserFinal = $winner === 'home' ? (int) $ctx->match->away_score : (int) $ctx->match->home_score;
            foreach ($goals as $g) {
                $mine = $winner === 'home' ? $g['total_home'] : $g['total_away'];
                if ($g['side'] === $winner && $mine === $loserFinal + 1) {
                    $winningGoalSeq = $g['seq'];
                    break;
                }
            }
        }

        $story = [];
        foreach ($goals as $i => $g) {
            $level = $g['total_home'] === $g['total_away'];
            $mine = $g['side'] === 'home' ? $g['total_home'] : $g['total_away'];
            $theirs = $g['side'] === 'home' ? $g['total_away'] : $g['total_home'];
            $story[] = [
                'minute' => $g['minute'],
                'side' => $g['side'],
                'player' => $g['own_goal'] ? $g['by'] : $g['player'],
                'assist' => $g['assist'],
                'own_goal' => $g['own_goal'],
                'home' => $g['total_home'],
                'away' => $g['total_away'],
                'moment' => match (true) {
                    $g['seq'] === $winningGoalSeq => 'winner',
                    $i === 0 => 'opener',
                    $level => 'equaliser',
                    $mine === $theirs + 1 => 'go_ahead',
                    default => 'goal',
                },
            ];

            if ($g['own_goal']) {
                if ($g['by'] !== '') {
                    $rows[$ensure($g['side'] === 'home' ? 'away' : 'home', $g['by'])]['own']++;
                }
                continue;
            }
            if ($g['player'] !== '') {
                $k = $ensure($g['side'], $g['player']);
                $rows[$k]['goals']++;
                if ($g['minute'] !== null) {
                    $rows[$k]['minutes'][] = $g['minute']."'";
                }
                $rows[$k]['opener'] = $rows[$k]['opener'] || $i === 0;
                $rows[$k]['winner'] = $rows[$k]['winner'] || $g['seq'] === $winningGoalSeq;
                if ($level) {
                    $rows[$k]['equalisers']++;
                }
            }
            if ($g['assist'] !== '') {
                $rows[$ensure($g['side'], $g['assist'])]['assists']++;
            }
        }

        foreach ($ctx->events as $e) {
            $name = trim((string) $e->player_name);
            if (in_array($e->kind, [MatchEvent::YELLOW, MatchEvent::RED], true)
                && in_array($e->side, ['home', 'away'], true) && $name !== '') {
                $rows[$ensure($e->side, $name)][$e->kind === MatchEvent::RED ? 'red' : 'yellow']++;
            }
        }

        $cards = [];
        foreach ($rows as $r) {
            $tags = [];
            if ($r['goals'] >= 3) {
                $tags[] = C::tag('hat_trick', 'Hat-trick');
            } elseif ($r['goals'] === 2) {
                $tags[] = C::tag('brace', 'Brace');
            }
            if ($r['winner']) {
                $tags[] = C::tag('winner', 'Winning goal');
            }
            if ($r['opener']) {
                $tags[] = C::tag('opener', 'Opened the scoring');
            }
            if ($r['equalisers'] > 0) {
                $tags[] = C::tag('equaliser', $r['equalisers'] > 1 ? $r['equalisers'].' equalisers' : 'Equaliser');
            }
            if ($r['assists'] >= 2) {
                $tags[] = C::tag('playmaker', 'Playmaker');
            } elseif ($r['goals'] > 0 && $r['assists'] > 0) {
                $tags[] = C::tag('goal_and_assist', 'Goal + assist');
            }
            if ($r['red'] > 0) {
                $tags[] = C::tag('sent_off', 'Sent off');
            } elseif ($r['yellow'] > 0) {
                $tags[] = C::tag('booked', 'Booked');
            }
            if ($r['own'] > 0) {
                $tags[] = C::tag('own_goal', 'Own goal');
            }

            $stats = [C::stat('Goals', $r['goals']), C::stat('Assists', $r['assists'])];
            if ($r['minutes'] !== []) {
                $stats[] = C::stat('Scored', implode(', ', $r['minutes']));
            }
            if ($r['yellow'] + $r['red'] > 0) {
                $stats[] = C::stat('Cards', trim(($r['yellow'] ? $r['yellow'].'Y ' : '').($r['red'] ? $r['red'].'R' : '')));
            }

            $card = C::card($r, $r['goals'], $r['goals'] === 1 ? 'Goal' : 'Goals', $ctx->share($r['side'], $r['goals']), $stats, $tags);
            $card['assists'] = $r['assists'];
            $card['_rank'] = [$r['goals'] + $r['assists'], $r['goals'], $r['winner'] ? 1 : 0, -($r['red'] * 2 + $r['yellow'])];
            $cards[] = $card;
        }
        usort($cards, fn (array $a, array $b): int => $b['_rank'] <=> $a['_rank']);
        $cards = array_map(function (array $c): array {
            unset($c['_rank']);

            return $c;
        }, $cards);

        $count = fn (string $kind, string $side): int => $ctx->events
            ->where('kind', $kind)->where('side', $side)->count();
        $attack = [];
        foreach (['home', 'away'] as $side) {
            $shots = $count('shot', $side);
            $scored = (int) $ctx->flow['total_'.$side];
            $attack[$side] = [
                'goals' => $scored,
                'shots' => $shots,
                'on_target' => $count('shot_on', $side),
                'corners' => $count('corner', $side),
                'fouls' => $count('foul', $side),
                'saves' => $count('save', $side),
                // Only when the tallies support it: goals over shots that were never counted
                // would be a division by a scorer's forgetfulness.
                'conversion' => $shots > 0 && $shots >= $scored ? (int) round($scored * 100 / $shots) : null,
            ];
        }

        return [
            'players' => $cards,
            'team' => [
                'story' => $story,
                'attack' => $attack,
                'stats' => app(MatchEventRecorder::class)->statsBlock($ctx->events),
            ],
            'reads' => $this->reads($ctx, $cards),
            'untracked' => ['Possession', 'Passes', 'Expected goals'],
        ];
    }

    /** @return array<int, string> */
    private function reads(InsightContext $ctx, array $cards): array
    {
        $reads = [];
        $cb = $ctx->flow['comeback'];
        if ($cb !== null) {
            $reads[] = $ctx->teamName($cb['side']).($cb['completed'] ? ' came back from ' : ' have come back from ')
                .$cb['deficit'].($cb['deficit'] === 1 ? ' goal' : ' goals').' down.';
        }
        $top = $cards[0] ?? null;
        if ($top !== null && $top['headline'] >= 2) {
            $total = (int) $ctx->flow['total_'.$top['side']];
            $reads[] = "{$top['name']} scored {$top['headline']} of ".$ctx->teamName($top['side'])."'s {$total}.";
        }
        if ($ctx->flow['lead_changes'] >= 2) {
            $reads[] = "The lead changed hands {$ctx->flow['lead_changes']} times.";
        }
        $run = $ctx->flow['longest_run'];
        if ($run !== null && $run['count'] >= 3) {
            $reads[] = $ctx->teamName($run['side'])." scored {$run['count']} unanswered goals.";
        }

        return array_slice($reads, 0, 3);
    }
}
