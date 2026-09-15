<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Services\Insights\InsightContext as C;
use App\Support\SportRules;

/**
 * Basketball: points, and what they were made of.
 *
 * Every bucket is recorded with its value (1 free throw / 2 field goal / 3 three), so the
 * shot MIX is real and per player. The quarter line shows where the game was won, and
 * "clutch" is only ever points in the final quarter while the game was within five.
 * Rebounds, assists, steals and minutes are not recorded — named as untracked.
 */
final class BasketballInsights implements SportInsightBuilder
{
    private const CLUTCH_MARGIN = 5;

    public function build(InsightContext $ctx): array
    {
        $finalPeriod = SportRules::periodCount('basketball', $ctx->format) - 1;
        $rows = [];
        $mix = [
            'home' => ['threes' => 0, 'twos' => 0, 'free_throws' => 0],
            'away' => ['threes' => 0, 'twos' => 0, 'free_throws' => 0],
        ];

        foreach ($ctx->moments as $m) {
            $kind = match ($m['value']) {
                3 => 'threes',
                1 => 'free_throws',
                default => 'twos',
            };
            $mix[$m['side']][$kind]++;

            if ($m['player'] === '') {
                continue;
            }
            $key = $m['side'].'|'.mb_strtolower($m['player']);
            $rows[$key] ??= ['threes' => 0, 'twos' => 0, 'free_throws' => 0, 'clutch' => 0];
            $rows[$key][$kind]++;

            $beforeHome = $m['total_home'] - ($m['side'] === 'home' ? $m['value'] : 0);
            $beforeAway = $m['total_away'] - ($m['side'] === 'away' ? $m['value'] : 0);
            if ($m['segment'] >= $finalPeriod && abs($beforeHome - $beforeAway) <= self::CLUTCH_MARGIN) {
                $rows[$key]['clutch'] += $m['value'];
            }
        }

        $players = $ctx->contributors['players'];
        $top = max([0, ...array_column($players, 'value')]);
        $topCount = count(array_filter($players, fn ($p) => $p['value'] === $top));

        $cards = [];
        foreach ($players as $key => $p) {
            $r = $rows[$key];
            $pts = $p['value'];
            $tags = [];
            if ($pts === $top && $topCount === 1 && $pts > 0) {
                $tags[] = C::tag('top_scorer', 'Top scorer');
            }
            if ($pts >= 20) {
                $tags[] = C::tag('twenty', '20-point game');
            } elseif ($pts >= 10) {
                $tags[] = C::tag('double_figures', 'Double figures');
            }
            if ($r['threes'] >= 3) {
                $tags[] = C::tag('sniper', $r['threes'].' threes');
            }
            if ($r['clutch'] >= 4) {
                $tags[] = C::tag('clutch', 'Clutch · '.$r['clutch'].' late');
            }
            if ($p['lead_takers'] >= 2) {
                $tags[] = C::tag('lead_changer', 'Took the lead '.$p['lead_takers'].'×');
            }
            if ($p['best_run'] >= 3) {
                $tags[] = C::tag('hot_hand', $p['best_run'].' straight buckets');
            }

            $card = C::card($p, $pts, 'Points', $ctx->share($p['side'], $pts), [
                C::stat('3PM', $r['threes']),
                C::stat('2PM', $r['twos']),
                C::stat('FT', $r['free_throws']),
            ], $tags);
            $card['mix'] = ['three' => $r['threes'] * 3, 'two' => $r['twos'] * 2, 'free_throw' => $r['free_throws']];
            $card['quarters'] = $this->perSegment($p['segments'], count($ctx->flow['segments']));
            $cards[] = $card;
        }
        usort($cards, fn (array $a, array $b): int => [$b['headline'], $b['share']] <=> [$a['headline'], $a['share']]);

        $quarters = $ctx->flow['segments'];
        $best = null;
        foreach ($quarters as $q) {
            $margin = abs($q['home'] - $q['away']);
            if ($margin > 0 && ($best === null || $margin > abs($best['home'] - $best['away']))) {
                $best = $q;
            }
        }

        $shotMix = [];
        foreach (['home', 'away'] as $side) {
            $m = $mix[$side];
            $shotMix[$side] = [
                'threes' => $m['threes'],
                'twos' => $m['twos'],
                'free_throws' => $m['free_throws'],
                'points_threes' => $m['threes'] * 3,
                'points_twos' => $m['twos'] * 2,
                'points_free_throws' => $m['free_throws'],
            ];
        }

        return [
            'players' => $cards,
            'team' => [
                'shot_mix' => $shotMix,
                'quarters' => $quarters,
                'best_quarter' => $best,
            ],
            'reads' => $this->reads($ctx, $best, $shotMix),
            // A box-score stat is untracked only while the scorer has never recorded one.
            'untracked' => array_values(array_filter([
                $ctx->events->contains('kind', 'rebound') ? null : 'Rebounds',
                ($ctx->events->contains('kind', 'assist') || $ctx->events->contains(fn ($e) => trim((string) $e->related_name) !== '')) ? null : 'Assists',
                $ctx->events->contains('kind', 'steal') ? null : 'Steals',
                'Shooting %',
            ])),
        ];
    }

    /** @return array<int, int> */
    private function perSegment(array $segments, int $count): array
    {
        $out = [];
        for ($i = 0; $i < max($count, 1); $i++) {
            $out[] = (int) ($segments[$i] ?? 0);
        }

        return $out;
    }

    /** @return array<int, string> */
    private function reads(InsightContext $ctx, ?array $best, array $mix): array
    {
        $reads = [];
        if ($best !== null && abs($best['home'] - $best['away']) >= 6) {
            $side = $best['home'] > $best['away'] ? 'home' : 'away';
            $reads[] = $ctx->teamName($side)." took {$best['label']} {$best['home']}–{$best['away']}.";
        }
        $cb = $ctx->flow['comeback'];
        if ($cb !== null) {
            $reads[] = $ctx->teamName($cb['side']).($cb['completed'] ? ' came back from ' : ' have come back from ')
                .$cb['deficit'].' down ('.$cb['from']['home'].'–'.$cb['from']['away'].').';
        }
        $run = $ctx->flow['longest_run'];
        if ($run !== null && $run['value'] >= 6) {
            $reads[] = 'Biggest run: '.$ctx->teamName($run['side'])." {$run['value']}–0.";
        }
        foreach (['home', 'away'] as $side) {
            $total = (int) $ctx->flow['total_'.$side];
            if ($total >= 20 && $mix[$side]['points_threes'] * 100 / $total >= 40) {
                $reads[] = $ctx->teamName($side).' made '.$mix[$side]['threes'].' threes — '
                    .(int) round($mix[$side]['points_threes'] * 100 / $total).'% of their points.';
                break;
            }
        }
        if ($ctx->flow['lead_changes'] >= 3) {
            $reads[] = "{$ctx->flow['lead_changes']} lead changes.";
        }

        return array_slice($reads, 0, 3);
    }
}
