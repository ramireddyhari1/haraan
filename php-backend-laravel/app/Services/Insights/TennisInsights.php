<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Services\Insights\InsightContext as C;

/**
 * Tennis: points into games into sets.
 *
 * Tennis famously lets a player win fewer points and still win the match, so total points
 * won sits beside games and sets rather than being hidden. Deuce games are the long,
 * contested ones and are counted from the ladder itself. The scorer taps who won each point
 * but not who served it, so breaks of serve, aces and double faults are NOT derivable here
 * and are named as untracked rather than guessed from "last point winner serves" — that is
 * a rally-sport rule, not a tennis one.
 */
final class TennisInsights implements SportInsightBuilder
{
    public function build(InsightContext $ctx): array
    {
        $games = ['home' => 0, 'away' => 0];
        $deuceGames = 0;
        $deuceWon = ['home' => 0, 'away' => 0];
        $inDeuce = false;
        $streak = ['side' => null, 'n' => 0];
        $bestStreak = ['home' => 0, 'away' => 0];
        $sets = [];

        foreach ($ctx->moments as $m) {
            $k = $m['segment'];
            $sets[$k] ??= ['label' => 'Set '.($k + 1), 'home' => 0, 'away' => 0, 'points_home' => 0, 'points_away' => 0, 'winner' => null];
            $sets[$k]['points_'.$m['side']]++;
            if ($m['deuce']) {
                $inDeuce = true;
            }
            if ($m['closes'] === null) {
                continue;
            }
            $games[$m['side']]++;
            $sets[$k][$m['side']]++;
            if ($inDeuce) {
                $deuceGames++;
                $deuceWon[$m['side']]++;
            }
            $inDeuce = false;
            $streak = $streak['side'] === $m['side'] ? ['side' => $m['side'], 'n' => $streak['n'] + 1] : ['side' => $m['side'], 'n' => 1];
            $bestStreak[$m['side']] = max($bestStreak[$m['side']], $streak['n']);
            if ($m['closes'] === 'set') {
                $sets[$k]['winner'] = $m['side'];
            }
        }

        $cards = [];
        foreach ($ctx->contributors['players'] as $p) {
            $tags = [];
            $closed = [];
            foreach ($ctx->moments as $m) {
                if ($m['closes'] === 'set' && $m['side'] === $p['side']
                    && mb_strtolower($m['player']) === mb_strtolower($p['name'])) {
                    $closed[] = 'Set '.($m['segment'] + 1);
                }
            }
            if ($closed !== []) {
                $tags[] = C::tag('closer', 'Closed '.implode(', ', $closed));
            }
            if ($p['deuce'] >= 3) {
                $tags[] = C::tag('clutch', 'Won '.$p['deuce'].' deuce points');
            }
            if ($p['best_run'] >= 5) {
                $tags[] = C::tag('run', $p['best_run'].' points in a row');
            }
            if ($bestStreak[$p['side']] >= 3) {
                $tags[] = C::tag('streak', $bestStreak[$p['side']].' games in a row');
            }

            $cards[] = C::card($p, $p['value'], 'Points won', $ctx->share($p['side'], $p['value']), [
                C::stat('Game pts', $p['closers']),
                C::stat('Deuce pts', $p['deuce']),
                C::stat('Best run', $p['best_run']),
            ], $tags);
        }
        usort($cards, fn (array $a, array $b): int => $b['headline'] <=> $a['headline']);

        $totalPoints = (int) $ctx->flow['total_home'] + (int) $ctx->flow['total_away'];

        return [
            'players' => $cards,
            'team' => [
                'sets' => array_values($sets),
                'games' => $games,
                'points_won' => ['home' => (int) $ctx->flow['total_home'], 'away' => (int) $ctx->flow['total_away']],
                'points_share_home' => $totalPoints > 0 ? (int) round($ctx->flow['total_home'] * 100 / $totalPoints) : null,
                'deuce_games' => $deuceGames,
                'deuce_games_won' => $deuceWon,
                'best_game_streak' => $bestStreak,
            ],
            'reads' => $this->reads($ctx, $games, $deuceGames, $deuceWon),
            'untracked' => ['Serve', 'Breaks of serve', 'Aces', 'Double faults'],
        ];
    }

    /** @return array<int, string> */
    private function reads(InsightContext $ctx, array $games, int $deuceGames, array $deuceWon): array
    {
        $reads = [];
        $h = (int) $ctx->flow['total_home'];
        $a = (int) $ctx->flow['total_away'];
        if ($h + $a >= 8 && $h !== $a && $games['home'] !== $games['away'] && ($h > $a) !== ($games['home'] > $games['away'])) {
            $side = $h > $a ? 'home' : 'away';
            $reads[] = $ctx->teamName($side).' won more points ('.max($h, $a).'–'.min($h, $a).') but fewer games.';
        } elseif ($h + $a >= 8) {
            $side = $h >= $a ? 'home' : 'away';
            $reads[] = $ctx->teamName($side).' won '.(int) round(max($h, $a) * 100 / ($h + $a)).'% of points.';
        }
        if ($deuceGames >= 2) {
            $side = $deuceWon['home'] >= $deuceWon['away'] ? 'home' : 'away';
            $reads[] = "{$deuceGames} games went to deuce — ".$ctx->teamName($side)." won {$deuceWon[$side]}.";
        }
        $cb = $ctx->flow['comeback'];
        if ($cb !== null) {
            $reads[] = $ctx->teamName($cb['side']).' came back from a set down.';
        }

        return array_slice($reads, 0, 3);
    }
}
