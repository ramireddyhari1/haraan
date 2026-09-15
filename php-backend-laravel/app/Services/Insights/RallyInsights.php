<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Services\Insights\InsightContext as C;
use App\Support\SportRules;

/**
 * Volleyball, table tennis and badminton: rallies inside sets (or games).
 *
 * What decides these sports is the end of a set, so that is where the insight is: who
 * closed sets out, who won the rallies once it was level at the death (deuce), and every
 * SET POINT that was saved — a rally the leader needed and lost. All three fall out of the
 * replay using the sport's own set rules (volleyball's decider to 15, badminton's cap at
 * 30), so they agree with the board to the rally. Aces, blocks, smashes and errors are not
 * recorded — named as untracked.
 */
final class RallyInsights implements SportInsightBuilder
{
    public function build(InsightContext $ctx): array
    {
        $noun = SportRules::setNoun($ctx->sport);
        $sets = [];
        $saved = ['home' => 0, 'away' => 0];
        $deucePoints = ['home' => 0, 'away' => 0];
        $savers = [];
        $closedBy = [];

        foreach ($ctx->moments as $m) {
            $k = $m['segment'];
            $sets[$k] ??= [
                'label' => $noun.' '.($k + 1), 'home' => 0, 'away' => 0,
                'deuce' => false, 'saved_home' => 0, 'saved_away' => 0,
                'winner' => null, 'closed_by' => null, 'run_home' => 0, 'run_away' => 0,
                // The points this set is played to — 25, or 15 in a volleyball decider — so the
                // app can say "went past 24-all" with the number the rules actually used.
                'target' => SportRules::setTarget($ctx->sport, $k, $ctx->format)['target'],
            ];

            // The score BEFORE this rally — was anyone one point from the set?
            $bh = $m['seg_home'] - ($m['side'] === 'home' ? 1 : 0);
            $ba = $m['seg_away'] - ($m['side'] === 'away' ? 1 : 0);
            $target = SportRules::setTarget($ctx->sport, $k, $ctx->format);
            $opponent = $m['side'] === 'home' ? 'away' : 'home';
            $oppHadSetPoint = $opponent === 'home'
                ? SportRules::setIsWon($bh + 1, $ba, $target)
                : SportRules::setIsWon($bh, $ba + 1, $target);
            if ($oppHadSetPoint) {
                $saved[$m['side']]++;
                $sets[$k]['saved_'.$m['side']]++;
                if ($m['player'] !== '') {
                    $key = $m['side'].'|'.mb_strtolower($m['player']);
                    $savers[$key] = ($savers[$key] ?? 0) + 1;
                }
            }
            if ($m['deuce']) {
                $sets[$k]['deuce'] = true;
                $deucePoints[$m['side']]++;
            }

            $sets[$k]['home'] = $m['seg_home'];
            $sets[$k]['away'] = $m['seg_away'];
            if ($m['closes'] === 'set') {
                $sets[$k]['winner'] = $m['side'];
                $sets[$k]['closed_by'] = $m['player'] !== '' ? $m['player'] : null;
                if ($m['player'] !== '') {
                    $closedBy[$m['side'].'|'.mb_strtolower($m['player'])][] = $noun.' '.($k + 1);
                }
            }
        }

        // Longest unanswered run inside each set, per side.
        $cur = null;
        foreach ($ctx->moments as $m) {
            $k = $m['segment'];
            if ($cur !== null && $cur['side'] === $m['side'] && $cur['segment'] === $k) {
                $cur['n']++;
            } else {
                $cur = ['side' => $m['side'], 'segment' => $k, 'n' => 1];
            }
            $sets[$k]['run_'.$m['side']] = max($sets[$k]['run_'.$m['side']], $cur['n']);
        }

        $players = $ctx->contributors['players'];
        $top = max([0, ...array_column($players, 'value')]);
        $topCount = count(array_filter($players, fn ($p) => $p['value'] === $top));

        $cards = [];
        foreach ($players as $key => $p) {
            $tags = [];
            if ($p['value'] === $top && $topCount === 1 && $top > 0) {
                $tags[] = C::tag('top_scorer', 'Most points');
            }
            if (isset($closedBy[$key])) {
                $tags[] = C::tag('closer', 'Closed '.implode(', ', $closedBy[$key]));
            }
            if (($savers[$key] ?? 0) > 0) {
                $n = $savers[$key];
                $tags[] = C::tag('saver', $n === 1 ? 'Saved a '.strtolower($noun).' point' : "Saved {$n} ".strtolower($noun).' points');
            }
            if ($p['deuce'] >= 2) {
                $tags[] = C::tag('clutch', 'Won '.$p['deuce'].' at deuce');
            }
            if ($p['best_run'] >= 4) {
                $tags[] = C::tag('run', $p['best_run'].'-point run');
            }

            $card = C::card($p, $p['value'], 'Points', $ctx->share($p['side'], $p['value']), [
                C::stat($noun.'s closed', $p['closers']),
                C::stat('At deuce', $p['deuce']),
                C::stat('Best run', $p['best_run']),
            ], $tags);
            $card['per_set'] = array_map(
                fn (int $i): int => (int) ($p['segments'][$i] ?? 0),
                array_keys(array_values($sets)),
            );
            $cards[] = $card;
        }
        usort($cards, fn (array $a, array $b): int => [$b['headline'], $b['share']] <=> [$a['headline'], $a['share']]);

        $sets = array_values($sets);

        return [
            'players' => $cards,
            'team' => [
                'set_noun' => $noun,
                'sets' => $sets,
                'points_won' => ['home' => (int) $ctx->flow['total_home'], 'away' => (int) $ctx->flow['total_away']],
                'set_points_saved' => $saved,
                'deuce_points' => $deucePoints,
            ],
            'reads' => $this->reads($ctx, $sets, $saved, $noun),
            'untracked' => match ($ctx->sport) {
                'volleyball' => ['Aces', 'Blocks', 'Attack errors'],
                'badminton' => ['Smashes', 'Unforced errors', 'Rally length'],
                default => ['Serve points', 'Unforced errors', 'Rally length'],
            },
        ];
    }

    /** @return array<int, string> */
    private function reads(InsightContext $ctx, array $sets, array $saved, string $noun): array
    {
        $reads = [];
        $h = (int) $ctx->flow['total_home'];
        $a = (int) $ctx->flow['total_away'];
        $setsHome = count(array_filter($sets, fn ($s) => $s['winner'] === 'home'));
        $setsAway = count(array_filter($sets, fn ($s) => $s['winner'] === 'away'));
        // Winning more rallies and fewer sets is the most interesting thing a rally match can do.
        if ($h !== $a && $setsHome !== $setsAway && ($h > $a) !== ($setsHome > $setsAway)) {
            $side = $h > $a ? 'home' : 'away';
            $reads[] = $ctx->teamName($side).' won more rallies ('.max($h, $a).'–'.min($h, $a).') but trail in '.strtolower($noun).'s.';
        }
        foreach (['home', 'away'] as $side) {
            if ($saved[$side] >= 2) {
                $reads[] = $ctx->teamName($side).' saved '.$saved[$side].' '.strtolower($noun).' points.';
            }
        }
        $deuceSets = array_filter($sets, fn ($s) => $s['deuce']);
        if (count($deuceSets) > 0) {
            $s = reset($deuceSets);
            $reads[] = "{$s['label']} went to deuce, {$s['home']}–{$s['away']}.";
        }
        $run = $ctx->flow['longest_run'];
        if ($run !== null && $run['count'] >= 5) {
            $reads[] = $ctx->teamName($run['side'])." won {$run['count']} rallies in a row.";
        }

        return array_slice($reads, 0, 3);
    }
}
