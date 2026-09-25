<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Models\MatchEvent;
use App\Services\Insights\InsightContext as C;
use App\Support\SportRules;

/**
 * Badminton, read the way the sport is actually argued about.
 *
 * Volleyball and table tennis share RallyInsights because a set is a set. Badminton gets its
 * own reading because three things about it are recorded here and mean something only here:
 *
 *  · THE SERVE. Badminton's serve is not a coin-toss fact you have to be told — the rally
 *    winner serves the next rally. So once ONE rally has been played, the server of every
 *    rally after it is known exactly, without the scorer recording anything. Every figure in
 *    the serve section falls out of that rule, and the one rally whose server nobody can know
 *    (the first of the match, unless a `serve` event says otherwise) is excluded, not guessed.
 *  · THE INTERVAL. A game breaks when the leader first reaches 11, and the half either side of
 *    it is the half coaches talk about. That split is in the rules, so it is countable.
 *  · GAME POINTS. Past 20-all a game is won by two until 29-all, where the next rally takes
 *    it. Applying those rules to the score before each rally says who was one rally away, who
 *    took it, and who saved it.
 *
 * Nothing here is modelled or estimated. Smashes, rally length and shot placement are not
 * recorded by the scorer and are named as absent; aces and forced errors appear only when a
 * scorer actually tagged rallies with them.
 */
final class BadmintonInsights implements SportInsightBuilder
{
    /** The score at which a badminton game breaks, every game. */
    private const INTERVAL = 11;

    public function build(C $ctx): array
    {
        $noun = SportRules::setNoun($ctx->sport);          // "Game"
        $serve = $this->serveChain($ctx);
        $games = [];
        $saved = ['home' => 0, 'away' => 0];
        $deucePoints = ['home' => 0, 'away' => 0];
        $afterInterval = ['home' => 0, 'away' => 0];
        $gamePoints = [
            'home' => ['for' => 0, 'converted' => 0, 'faced' => 0, 'saved' => 0],
            'away' => ['for' => 0, 'converted' => 0, 'faced' => 0, 'saved' => 0],
        ];
        $savers = [];
        $closedBy = [];
        $perPlayer = [];

        foreach ($ctx->moments as $m) {
            $k = $m['segment'];
            $target = SportRules::setTarget($ctx->sport, $k, $ctx->format);
            $games[$k] ??= [
                'label' => $noun.' '.($k + 1), 'home' => 0, 'away' => 0,
                'deuce' => false, 'saved_home' => 0, 'saved_away' => 0,
                'winner' => null, 'closed_by' => null, 'run_home' => 0, 'run_away' => 0,
                'target' => $target['target'], 'cap' => $target['cap'],
                'interval_home' => 0, 'interval_away' => 0, 'reached_interval' => false,
                'serve_home' => 0, 'serve_away' => 0, 'breaks_home' => 0, 'breaks_away' => 0,
            ];
            $player = $m['player'];
            $key = $player === '' ? null : $m['side'].'|'.mb_strtolower($player);
            if ($key !== null) {
                $perPlayer[$key] ??= ['on_serve' => 0, 'saved' => 0, 'game_points' => 0, 'after_interval' => 0, 'aces' => 0];
            }

            // The score BEFORE this rally, so "who was one rally away" is asked of the right board.
            $bh = $m['seg_home'] - ($m['side'] === 'home' ? 1 : 0);
            $ba = $m['seg_away'] - ($m['side'] === 'away' ? 1 : 0);

            // Past the interval: either side had already reached 11 when this rally started.
            if (max($bh, $ba) >= self::INTERVAL) {
                $afterInterval[$m['side']]++;
                $games[$k]['interval_'.$m['side']]++;
                $games[$k]['reached_interval'] = true;
                if ($key !== null) {
                    $perPlayer[$key]['after_interval']++;
                }
            }

            // Game point, by the rules rather than by a threshold: could this rally have closed it?
            foreach (['home', 'away'] as $side) {
                $h = $bh + ($side === 'home' ? 1 : 0);
                $a = $ba + ($side === 'away' ? 1 : 0);
                if (! SportRules::setIsWon($h, $a, $target)) {
                    continue;
                }
                $gamePoints[$side]['for']++;
                $gamePoints[self::other($side)]['faced']++;
                if ($side === $m['side']) {
                    $gamePoints[$side]['converted']++;
                    if ($key !== null) {
                        $perPlayer[$key]['game_points']++;
                    }

                    continue;
                }
                $gamePoints[$m['side']]['saved']++;
                $saved[$m['side']]++;
                $games[$k]['saved_'.$m['side']]++;
                if ($key !== null) {
                    $savers[$key] = ($savers[$key] ?? 0) + 1;
                    $perPlayer[$key]['saved']++;
                }
            }

            if ($m['deuce']) {
                $games[$k]['deuce'] = true;
                $deucePoints[$m['side']]++;
            }

            // Who served this rally, from the chain — and whether the serve was held or broken.
            $server = $serve['by_seq'][$m['seq']] ?? null;
            if ($server !== null) {
                $games[$k]['serve_'.$server]++;
                if ($server === $m['side']) {
                    if ($key !== null) {
                        $perPlayer[$key]['on_serve']++;
                    }
                } else {
                    $games[$k]['breaks_'.$m['side']]++;
                }
            }
            if (($m['detail'] ?? '') === 'ace' && $key !== null) {
                $perPlayer[$key]['aces']++;
            }

            $games[$k]['home'] = $m['seg_home'];
            $games[$k]['away'] = $m['seg_away'];
            if ($m['closes'] === 'set') {
                $games[$k]['winner'] = $m['side'];
                $games[$k]['closed_by'] = $player !== '' ? $player : null;
                if ($player !== '') {
                    $closedBy[$m['side'].'|'.mb_strtolower($player)][] = $noun.' '.($k + 1);
                }
            }
        }

        [$runs, $longestInGame] = $this->runs($ctx);
        foreach ($longestInGame as $k => $perSide) {
            if (! isset($games[$k])) {
                continue;
            }
            $games[$k]['run_home'] = $perSide['home'];
            $games[$k]['run_away'] = $perSide['away'];
        }

        $games = array_values($games);
        $section = $this->serveSection($ctx, $serve);

        return [
            'players' => $this->cards($ctx, $noun, $perPlayer, $savers, $closedBy, $games),
            'team' => [
                'set_noun' => $noun,
                'sets' => $games,
                'points_won' => ['home' => (int) $ctx->flow['total_home'], 'away' => (int) $ctx->flow['total_away']],
                'set_points_saved' => $saved,
                'deuce_points' => $deucePoints,
                'serve' => $section,
                'pressure' => [
                    'game_points' => $gamePoints,
                    'deuce' => $deucePoints,
                    'after_interval' => $afterInterval,
                    'interval' => self::INTERVAL,
                    'runs3' => $runs['runs3'],
                    'responses' => $runs['responses'],
                    'longest' => $runs['longest'],
                    'runs' => $runs['runs'],
                ],
            ],
            'reads' => $this->reads($ctx, $games, $saved, $gamePoints, $section, $afterInterval),
            'untracked' => $this->untracked($section),
        ];
    }

    private static function other(string $side): string
    {
        return $side === 'home' ? 'away' : 'home';
    }

    /**
     * Who served every rally.
     *
     * Badminton's own rule does the work: the winner of a rally serves the next one. A scorer
     * MAY record a `serve` event, and when they do it is believed over the rule; otherwise the
     * chain starts unknown and becomes known the instant the first rally is won. So at most one
     * rally in a match has no server, and it is counted as unknown rather than assigned.
     *
     * @return array{by_seq: array<int, string>, first: string|null, recorded: bool, unknown: int}
     */
    private function serveChain(C $ctx): array
    {
        $scored = [];
        foreach ($ctx->moments as $m) {
            $scored[(int) $m['seq']] = $m;
        }

        $bySeq = [];
        $server = null;
        $first = null;
        $recorded = false;
        $unknown = 0;

        foreach ($ctx->events as $e) {
            $side = in_array($e->side, ['home', 'away'], true) ? $e->side : null;
            if ($e->kind === MatchEvent::SERVE && $side !== null) {
                $server = $side;
                $recorded = true;
                $first ??= $side;

                continue;
            }
            $seq = (int) $e->sequence;
            if ($e->kind !== MatchEvent::POINT || ! isset($scored[$seq])) {
                continue;   // a rally the board ignored (the match was already decided) is not a rally
            }
            if ($server === null) {
                $unknown++;
            } else {
                $bySeq[$seq] = $server;
                $first ??= $server;
            }
            // The rally winner serves next — including the first rally of the next game.
            $server = (string) $scored[$seq]['side'];
        }

        return ['by_seq' => $bySeq, 'first' => $first, 'recorded' => $recorded, 'unknown' => $unknown];
    }

    /**
     * Rallies won on serve and on the return, per side, plus the longest run a side kept the
     * serve for. A BREAK is the receiving side winning the rally — in badminton that is both a
     * point and the serve changing hands, which is why it is the figure that moves games.
     *
     * @param  array{by_seq: array<int, string>, first: string|null, recorded: bool, unknown: int}  $serve
     * @return array<string, mixed>
     */
    private function serveSection(C $ctx, array $serve): array
    {
        $blank = ['played' => 0, 'won' => 0, 'pct' => 0, 'breaks' => 0, 'break_chances' => 0,
            'best_streak' => 0, 'aces' => 0, 'errors_forced' => 0];
        $side = ['home' => $blank, 'away' => $blank];
        $streak = ['home' => 0, 'away' => 0];
        $aces = ['home' => 0, 'away' => 0];
        $errors = ['home' => 0, 'away' => 0];
        $detailed = false;

        foreach ($ctx->moments as $m) {
            $detail = (string) ($m['detail'] ?? '');
            if ($detail === 'ace') {
                $aces[$m['side']]++;
                $detailed = true;
            } elseif ($detail === 'error') {
                // The rally was won because the OTHER side put it out — credited as forced.
                $errors[$m['side']]++;
                $detailed = true;
            }

            $server = $serve['by_seq'][$m['seq']] ?? null;
            if ($server === null) {
                continue;
            }
            $side[$server]['played']++;
            $side[self::other($server)]['break_chances']++;
            if ($server === $m['side']) {
                $side[$server]['won']++;
                $streak[$server]++;
                $streak[self::other($server)] = 0;
                $side[$server]['best_streak'] = max($side[$server]['best_streak'], $streak[$server]);

                continue;
            }
            $side[$m['side']]['breaks']++;
            $streak = ['home' => 0, 'away' => 0];
        }

        foreach (['home', 'away'] as $s) {
            $side[$s]['pct'] = $side[$s]['played'] > 0
                ? (int) round($side[$s]['won'] * 100 / $side[$s]['played'])
                : 0;
            $side[$s]['aces'] = $aces[$s];
            $side[$s]['errors_forced'] = $errors[$s];
        }

        $rallies = $side['home']['played'] + $side['away']['played'];

        return [
            'known' => $rallies > 0,
            'rallies' => $rallies,
            'unknown' => $serve['unknown'],
            'first_server' => $serve['first'],
            'recorded' => $serve['recorded'],
            'detailed' => $detailed,
            'home' => $side['home'],
            'away' => $side['away'],
        ];
    }

    /**
     * Runs of unanswered rallies, inside a game. A run that crosses a game boundary is two
     * runs, because the scoreboard it mattered on reset in between.
     *
     * @return array{0: array<string, mixed>, 1: array<int, array{home: int, away: int}>}
     */
    private function runs(C $ctx): array
    {
        $runs3 = ['home' => 0, 'away' => 0];
        $responses = ['home' => 0, 'away' => 0];
        $longest = null;
        $perGame = [];
        $list = [];
        $cur = null;

        $flush = function (?array $run) use (&$runs3, &$longest, &$perGame, &$list): void {
            if ($run === null) {
                return;
            }
            $perGame[$run['segment']] ??= ['home' => 0, 'away' => 0];
            $perGame[$run['segment']][$run['side']] = max($perGame[$run['segment']][$run['side']], $run['count']);
            if ($run['count'] >= 3) {
                $runs3[$run['side']]++;
            }
            if ($longest === null || $run['count'] > $longest['count']) {
                $longest = $run;
            }
            $list[] = $run;
        };

        foreach ($ctx->moments as $m) {
            $k = $m['segment'];
            if ($cur !== null && $cur['side'] === $m['side'] && $cur['segment'] === $k) {
                $cur['count']++;
            } else {
                // Stopping the other side's run of three or more, in the same game, immediately.
                if ($cur !== null && $cur['segment'] === $k && $cur['count'] >= 3) {
                    $responses[$m['side']]++;
                }
                $flush($cur);
                $cur = ['side' => $m['side'], 'segment' => $k, 'count' => 1, 'end_home' => 0, 'end_away' => 0];
            }
            $cur['end_home'] = $m['seg_home'];
            $cur['end_away'] = $m['seg_away'];
        }
        $flush($cur);

        return [
            [
                'runs3' => $runs3,
                'responses' => $responses,
                'longest' => $longest !== null && $longest['count'] >= 2 ? $longest : null,
                // Every run in order, so the tab can draw the match as the streaks it was made
                // of rather than re-deriving them from a sampled line.
                'runs' => $list,
            ],
            $perGame,
        ];
    }

    /**
     * A player's card. The headline stays POINTS — the thing a badminton scorer records — and
     * everything beside it is a count of named rallies, never a rating. "Decisive" is spelled
     * out on the tab: game points won, plus game points saved, plus rallies won at deuce.
     *
     * @param  array<string, array<string, int>>  $perPlayer
     * @param  array<string, int>  $savers
     * @param  array<string, array<int, string>>  $closedBy
     * @param  array<int, array<string, mixed>>  $games
     * @return array<int, array<string, mixed>>
     */
    private function cards(C $ctx, string $noun, array $perPlayer, array $savers, array $closedBy, array $games): array
    {
        $players = $ctx->contributors['players'];
        $top = max([0, ...array_column($players, 'value')]);
        $topCount = count(array_filter($players, fn ($p) => $p['value'] === $top));
        $lower = mb_strtolower($noun);
        $blank = ['on_serve' => 0, 'saved' => 0, 'game_points' => 0, 'after_interval' => 0, 'aces' => 0];

        $cards = [];
        foreach ($players as $key => $p) {
            $extra = $perPlayer[$key] ?? $blank;
            $decisive = $extra['game_points'] + $extra['saved'] + $p['deuce'];

            $tags = [];
            if ($p['value'] === $top && $topCount === 1 && $top > 0) {
                $tags[] = C::tag('top_scorer', 'Most points');
            }
            if (isset($closedBy[$key])) {
                $tags[] = C::tag('closer', 'Closed '.implode(', ', $closedBy[$key]));
            }
            if (($savers[$key] ?? 0) > 0) {
                $n = $savers[$key];
                $tags[] = C::tag('saver', $n === 1 ? 'Saved a '.$lower.' point' : "Saved {$n} {$lower} points");
            }
            if ($p['deuce'] >= 2) {
                $tags[] = C::tag('clutch', 'Won '.$p['deuce'].' at deuce');
            }
            if ($p['best_run'] >= 4) {
                $tags[] = C::tag('run', $p['best_run'].'-point run');
            }
            if ($extra['aces'] > 0) {
                $tags[] = C::tag('ace', $extra['aces'] === 1 ? 'An ace' : $extra['aces'].' aces');
            }

            $card = C::card($p, $p['value'], 'Points', $ctx->share($p['side'], $p['value']), [
                C::stat('On serve', $extra['on_serve']),
                C::stat('After '.self::INTERVAL, $extra['after_interval']),
                C::stat('Best run', $p['best_run']),
                C::stat('Decisive', $decisive),
            ], $tags);

            $card['per_set'] = array_map(
                fn (int $i): int => (int) ($p['segments'][$i] ?? 0),
                array_keys($games),
            );
            $card['on_serve'] = $extra['on_serve'];
            $card['after_interval'] = $extra['after_interval'];
            $card['game_points_won'] = $extra['game_points'];
            $card['game_points_saved'] = $extra['saved'];
            $card['aces'] = $extra['aces'];
            $card['deuce'] = $p['deuce'];
            $card['best_run'] = $p['best_run'];
            $card['closers'] = $p['closers'];
            $card['decisive'] = $decisive;
            $cards[] = $card;
        }
        usort($cards, fn (array $a, array $b): int => [$b['headline'], $b['decisive'], $b['share']] <=> [$a['headline'], $a['decisive'], $a['share']]);

        return $cards;
    }

    /**
     * Up to three sentences, each one checkable against a figure printed on the tab.
     *
     * @param  array<int, array<string, mixed>>  $games
     * @param  array<string, int>  $saved
     * @param  array<string, array<string, int>>  $gamePoints
     * @param  array<string, mixed>  $serve
     * @param  array<string, int>  $afterInterval
     * @return array<int, string>
     */
    private function reads(C $ctx, array $games, array $saved, array $gamePoints, array $serve, array $afterInterval): array
    {
        $reads = [];
        $h = (int) $ctx->flow['total_home'];
        $a = (int) $ctx->flow['total_away'];
        $gamesHome = count(array_filter($games, fn ($g) => $g['winner'] === 'home'));
        $gamesAway = count(array_filter($games, fn ($g) => $g['winner'] === 'away'));

        // Winning more rallies and fewer games is the most interesting thing a badminton match can do.
        if ($h !== $a && $gamesHome !== $gamesAway && ($h > $a) !== ($gamesHome > $gamesAway)) {
            $side = $h > $a ? 'home' : 'away';
            $reads[] = $ctx->teamName($side).' won more rallies ('.max($h, $a).'–'.min($h, $a).') but trail on games.';
        }

        // Service, the sport's own currency — only once the chain is long enough to mean anything.
        if ($serve['rallies'] >= 12) {
            foreach (['home', 'away'] as $side) {
                if ($serve[$side]['played'] >= 6 && $serve[$side]['pct'] >= 60) {
                    $reads[] = $ctx->teamName($side).' won '.$serve[$side]['pct'].'% of the rallies they served ('
                        .$serve[$side]['won'].' of '.$serve[$side]['played'].').';
                    break;
                }
            }
        }

        foreach (['home', 'away'] as $side) {
            if ($saved[$side] >= 2) {
                $reads[] = $ctx->teamName($side).' saved '.$saved[$side].' game points.';
                break;
            }
        }

        foreach (['home', 'away'] as $side) {
            if ($gamePoints[$side]['for'] >= 3 && $gamePoints[$side]['converted'] > 0
                && $gamePoints[$side]['for'] > $gamePoints[$side]['converted']) {
                $reads[] = $ctx->teamName($side).' needed '.$gamePoints[$side]['for'].' game points to win '
                    .$gamePoints[$side]['converted'].'.';
                break;
            }
        }

        $deuceGames = array_filter($games, fn ($g) => $g['deuce']);
        if ($deuceGames !== []) {
            $g = reset($deuceGames);
            $reads[] = "{$g['label']} went past ".((int) $g['target'] - 1)."-all, {$g['home']}–{$g['away']}.";
        }

        $past = $afterInterval['home'] + $afterInterval['away'];
        if ($past >= 8 && $afterInterval['home'] !== $afterInterval['away']) {
            $side = $afterInterval['home'] > $afterInterval['away'] ? 'home' : 'away';
            $reads[] = $ctx->teamName($side).' won '.$afterInterval[$side].' of the '.$past
                .' rallies played after the '.self::INTERVAL.'-point interval.';
        }

        $run = $ctx->flow['longest_run'];
        if ($run !== null && $run['count'] >= 5) {
            $reads[] = $ctx->teamName($run['side'])." won {$run['count']} rallies in a row.";
        }

        return array_slice($reads, 0, 3);
    }

    /**
     * What the scorer never records for badminton. Aces and forced errors are OPTIONAL taps,
     * so they are named as missing only when this match's scorer never used them.
     *
     * @param  array<string, mixed>  $serve
     * @return array<int, string>
     */
    private function untracked(array $serve): array
    {
        $out = ['Smashes', 'Rally length', 'Shot placement'];
        if (! $serve['detailed']) {
            array_unshift($out, 'Aces', 'Unforced errors');
        }

        return $out;
    }
}
