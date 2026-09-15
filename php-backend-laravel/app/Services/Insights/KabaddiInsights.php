<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Services\Insights\InsightContext as C;

/**
 * Kabaddi: raid points against tackle points.
 *
 * The sport's own honours are real thresholds — a SUPER 10 is ten raid points in a match,
 * a HIGH 5 is five tackle points — and both fall straight out of the recorded detail.
 * An all-out is the TEAM's two points for clearing the mat, so it is counted per side and
 * never credited to whoever the scorer happened to name. Raid attempts, empty raids and
 * do-or-die raids are not recorded — named as untracked.
 */
final class KabaddiInsights implements SportInsightBuilder
{
    public function build(InsightContext $ctx): array
    {
        $team = [
            'home' => ['raid' => 0, 'tackle' => 0, 'bonus' => 0, 'all_out' => 0, 'super_raids' => 0],
            'away' => ['raid' => 0, 'tackle' => 0, 'bonus' => 0, 'all_out' => 0, 'super_raids' => 0],
        ];
        $rows = [];

        foreach ($ctx->moments as $m) {
            $detail = $m['detail'] ?? '';
            $side = $m['side'];
            // raid, bonus and super raid are all points won by the raider.
            [$bucket, $raid, $tackle] = match ($detail) {
                'tackle' => ['tackle', 0, $m['value']],
                'all_out' => ['all_out', 0, 0],
                'bonus' => ['bonus', $m['value'], 0],
                'super_raid' => ['super_raids', $m['value'], 0],
                default => ['raid', $m['value'], 0],
            };
            $team[$side]['raid'] += $raid;
            $team[$side]['tackle'] += $tackle;
            if ($bucket === 'bonus') {
                $team[$side]['bonus']++;
            } elseif ($bucket === 'all_out') {
                $team[$side]['all_out']++;
            } elseif ($bucket === 'super_raids') {
                $team[$side]['super_raids']++;
            }

            if ($m['player'] === '' || $bucket === 'all_out') {
                continue;
            }
            $key = $side.'|'.mb_strtolower($m['player']);
            $rows[$key] ??= ['side' => $side, 'name' => $m['player'], 'raid' => 0, 'tackle' => 0, 'bonus' => 0, 'super_raids' => 0];
            $rows[$key]['raid'] += $raid;
            $rows[$key]['tackle'] += $tackle;
            if ($bucket === 'bonus') {
                $rows[$key]['bonus']++;
            }
            if ($bucket === 'super_raids') {
                $rows[$key]['super_raids']++;
            }
        }

        $topRaid = max([0, ...array_column($rows, 'raid')]);
        $topTackle = max([0, ...array_column($rows, 'tackle')]);

        $cards = [];
        foreach ($rows as $r) {
            $total = $r['raid'] + $r['tackle'];
            $role = $r['raid'] >= $r['tackle'] ? 'Raider' : 'Defender';
            $tags = [];
            if ($r['raid'] >= 10) {
                $tags[] = C::tag('super_10', 'Super 10');
            }
            if ($r['tackle'] >= 5) {
                $tags[] = C::tag('high_5', 'High 5');
            }
            if ($r['super_raids'] > 0) {
                $tags[] = C::tag('super_raid', $r['super_raids'] > 1 ? $r['super_raids'].' super raids' : 'Super raid');
            }
            if ($r['raid'] > 0 && $r['raid'] === $topRaid && count(array_filter($rows, fn ($x) => $x['raid'] === $topRaid)) === 1) {
                $tags[] = C::tag('top_raider', 'Top raider');
            }
            if ($r['tackle'] > 0 && $r['tackle'] === $topTackle && count(array_filter($rows, fn ($x) => $x['tackle'] === $topTackle)) === 1) {
                $tags[] = C::tag('top_defender', 'Top defender');
            }

            $card = C::card($r, $total, 'Points', $ctx->share($r['side'], $total), [
                C::stat('Raid', $r['raid']),
                C::stat('Tackle', $r['tackle']),
                C::stat('Bonus', $r['bonus']),
            ], $tags);
            $card['role'] = $role;
            $card['raid'] = $r['raid'];
            $card['tackle'] = $r['tackle'];
            $cards[] = $card;
        }
        usort($cards, fn (array $a, array $b): int => [$b['headline'], $b['raid']] <=> [$a['headline'], $a['raid']]);

        return [
            'players' => $cards,
            'team' => [
                'split' => $team,
                'halves' => $ctx->flow['segments'],
            ],
            'reads' => $this->reads($ctx, $team),
            // A match scored on the mat records every raid, empty ones included.
            'untracked' => \App\Services\SportScoreEngine::kabaddiTracksMat(is_array($ctx->match->sport_state) ? $ctx->match->sport_state : [])
                ? []
                : ['Raid attempts', 'Empty raids', 'Do-or-die raids'],
        ];
    }

    /** @return array<int, string> */
    private function reads(InsightContext $ctx, array $team): array
    {
        $reads = [];
        foreach (['home', 'away'] as $side) {
            if ($team[$side]['all_out'] > 0) {
                $n = $team[$side]['all_out'];
                $reads[] = $ctx->teamName($side).' inflicted '.($n === 1 ? 'an all out' : $n.' all outs').'.';
            }
        }
        $h = $team['home'];
        $a = $team['away'];
        if ($h['tackle'] + $a['tackle'] >= 4 && $h['tackle'] !== $a['tackle']) {
            $side = $h['tackle'] > $a['tackle'] ? 'home' : 'away';
            $reads[] = $ctx->teamName($side).' won the mat on defence, '
                .max($h['tackle'], $a['tackle']).'–'.min($h['tackle'], $a['tackle']).' in tackle points.';
        }
        $cb = $ctx->flow['comeback'];
        if ($cb !== null) {
            $reads[] = $ctx->teamName($cb['side']).' recovered from '.$cb['deficit'].' points down.';
        }

        return array_slice($reads, 0, 3);
    }
}
