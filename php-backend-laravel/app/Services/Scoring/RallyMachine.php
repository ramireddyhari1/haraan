<?php

declare(strict_types=1);

namespace App\Services\Scoring;

use App\Support\SportRules;

/**
 * Volleyball, table tennis and badminton, one rally at a time.
 *
 * All three are rally-scored — every rally is a point — and all three fill a set to a target
 * and reset. Where they genuinely differ is WHO SERVES, and that is what a live board in these
 * sports is watched for:
 *
 *  · Volleyball & badminton: the rally winner serves next. Volleyball adds ROTATION — a side
 *    that wins the serve back (a side-out) rotates one place — and two TIMEOUTS a set.
 *  · Table tennis: the serve changes every TWO points, and every point from 10–10. The first
 *    server alternates game by game. The rally winner has nothing to do with it.
 *  · Badminton serves from the RIGHT court on an even score and the left on an odd one.
 *
 * Who served first is known only when the scorer records it (a `serve` event). Until then a
 * table-tennis board shows no server rather than a guess; volleyball and badminton learn it
 * from the first rally, because the rally winner serves.
 */
final class RallyMachine
{
    public int $home = 0;
    public int $away = 0;
    public int $setsHome = 0;
    public int $setsAway = 0;
    public int $index = 0;

    /** @var array<int, array{0: int, 1: int}> */
    public array $completed = [];

    /** Who serves the next rally. */
    public ?string $server = null;

    /** Table tennis: who served first in the game in progress. */
    private ?string $gameFirstServer = null;

    /** Volleyball: who served first in the set in progress. */
    private ?string $setFirstServer = null;

    /** Volleyball rotation, 1–6, per side, in the current set. */
    public array $rotation = ['home' => 1, 'away' => 1];

    /** Timeouts called in the current set. */
    public array $timeouts = ['home' => 0, 'away' => 0];

    /** @var array<string, int> rallies won while serving / receiving, per side */
    public array $stats;

    private string $sport;
    private int $bestOf;

    /** @param array<string, mixed> $format */
    public function __construct(string $sport, private array $format = [])
    {
        $this->sport = SportRules::normalise($sport);
        $this->bestOf = max(1, (int) ($format['bestOf'] ?? SportRules::defaultBestOf($this->sport)));
        $blank = ['serve_points_won' => 0, 'serve_points_played' => 0, 'side_outs' => 0,
            'points_won' => 0, 'timeouts' => 0, 'aces' => 0, 'errors_given' => 0];
        $this->stats = ['home' => $blank, 'away' => $blank];
    }

    public static function other(string $side): string
    {
        return $side === 'home' ? 'away' : 'home';
    }

    public function decided(): bool
    {
        $half = intdiv($this->bestOf, 2);

        return $this->setsHome > $half || $this->setsAway > $half;
    }

    /** @return array{target: int, winBy: int, cap: int|null} */
    public function target(): array
    {
        return SportRules::setTarget($this->sport, $this->index, $this->format);
    }

    public function isDecider(): bool
    {
        return $this->bestOf > 1 && $this->index === $this->bestOf - 1;
    }

    /** Who serves the rally about to be played. */
    public function currentServer(): ?string
    {
        if ($this->sport !== 'table_tennis') {
            return $this->server;
        }
        if ($this->gameFirstServer === null) {
            return null;
        }
        $block = $this->ttBlock();

        return $block % 2 === 0 ? $this->gameFirstServer : self::other($this->gameFirstServer);
    }

    /** Table tennis: serves left in the current server's turn — 2, 1, or 1 for good at deuce. */
    public function servesLeft(): ?int
    {
        if ($this->sport !== 'table_tennis' || $this->gameFirstServer === null) {
            return null;
        }
        $t = $this->target()['target'];
        $played = $this->home + $this->away;
        $pairPoints = 2 * ($t - 1);

        return $played >= $pairPoints ? 1 : 2 - ($played % 2);
    }

    private function ttBlock(): int
    {
        $t = $this->target()['target'];
        $played = $this->home + $this->away;
        $pairPoints = 2 * ($t - 1);

        return $played < $pairPoints
            ? intdiv($played, 2)
            : ($t - 1) + ($played - $pairPoints);
    }

    /** The scorer says who is serving now. */
    public function setServer(string $side): void
    {
        if ($this->sport === 'table_tennis') {
            $this->gameFirstServer = $this->ttBlock() % 2 === 0 ? $side : self::other($side);

            return;
        }
        $this->server = $side;
        if ($this->home + $this->away === 0) {
            $this->setFirstServer = $side;
        }
    }

    public function timeout(string $side): void
    {
        $this->timeouts[$side]++;
        $this->stats[$side]['timeouts']++;
    }

    /**
     * Play one rally.
     *
     * @return array{closes: string|null, server: string|null, deuce: bool, set_point: string|null,
     *               side_out: bool, set_home: int, set_away: int, set_index: int, change_ends: bool, interval: bool}
     */
    public function point(string $side, string $detail = ''): array
    {
        $server = $this->currentServer();
        $setPoint = $this->setPointSide();
        $t = $this->target();
        $deuce = $this->home >= $t['target'] - 1 && $this->away >= $t['target'] - 1;
        $setIndex = $this->index;

        if ($this->decided()) {
            return ['closes' => null, 'server' => $server, 'deuce' => $deuce, 'set_point' => null,
                'side_out' => false, 'set_home' => $this->home, 'set_away' => $this->away,
                'set_index' => $setIndex, 'change_ends' => false, 'interval' => false];
        }

        $detail = strtolower(trim($detail));
        $this->stats[$side]['points_won']++;
        if ($detail === 'ace') {
            $this->stats[$side]['aces']++;
        } elseif ($detail === 'error') {
            $this->stats[self::other($side)]['errors_given']++;
        }
        if ($server !== null) {
            $this->stats[$server]['serve_points_played']++;
            if ($side === $server) {
                $this->stats[$server]['serve_points_won']++;
            }
        }

        // Volleyball: winning the serve back is a side-out, and the side that wins it rotates.
        $sideOut = $server !== null && $side !== $server;
        if ($sideOut) {
            $this->stats[$side]['side_outs']++;
            if ($this->sport === 'volleyball') {
                $this->rotation[$side] = $this->rotation[$side] % 6 + 1;
            }
        }

        $leaderBefore = max($this->home, $this->away);
        $side === 'home' ? $this->home++ : $this->away++;
        $h = $this->home;
        $a = $this->away;

        if ($this->sport !== 'table_tennis') {
            $this->server = $side;
            if ($this->setFirstServer === null && $h + $a === 1 && $server !== null) {
                $this->setFirstServer = $server;
            }
        }

        // Ends change mid-decider: volleyball at 8, badminton at 11, table tennis at 5. The
        // board says so on the rally that crosses it — the moment players actually swap.
        $changeAt = match ($this->sport) {
            'volleyball' => 8,
            'badminton' => 11,
            default => 5,
        };
        $crossed = max($h, $a) === $changeAt && min($h, $a) < $changeAt && $leaderBefore < $changeAt;
        $changeEnds = $this->isDecider() && $crossed;
        // Badminton breaks for an interval when the leader first reaches 11, every game.
        $interval = $this->sport === 'badminton' && max($h, $a) === 11 && min($h, $a) < 11 && $leaderBefore < 11;

        $closes = null;
        if (SportRules::setIsWon($h, $a, $t)) {
            $this->completed[] = [$h, $a];
            $h > $a ? $this->setsHome++ : $this->setsAway++;
            $closes = $this->decided() ? 'match' : 'set';
            $this->home = 0;
            $this->away = 0;
            $this->index++;
            // Volleyball's timeouts are per set; table tennis allows ONE per match.
            if ($this->sport !== 'table_tennis') {
                $this->timeouts = ['home' => 0, 'away' => 0];
            }
            $this->rotation = ['home' => 1, 'away' => 1];

            if ($this->sport === 'table_tennis') {
                // The first server alternates game by game.
                $this->gameFirstServer = $this->gameFirstServer === null ? null : self::other($this->gameFirstServer);
            } elseif ($this->sport === 'volleyball') {
                // The side that received first in the last set serves first in the next.
                $first = $this->setFirstServer === null ? null : self::other($this->setFirstServer);
                $this->server = $first;
                $this->setFirstServer = $first;
            }
            // Badminton: the game winner serves first — already true, they won the last rally.
        }

        return ['closes' => $closes, 'server' => $server, 'deuce' => $deuce, 'set_point' => $setPoint,
            'side_out' => $sideOut, 'set_home' => $closes ? $h : $this->home, 'set_away' => $closes ? $a : $this->away,
            'set_index' => $setIndex, 'change_ends' => $changeEnds, 'interval' => $interval];
    }

    /** The side one rally from taking the set (or the match), before the next rally. */
    public function setPointSide(): ?string
    {
        foreach (['home', 'away'] as $side) {
            if ($this->wouldClose($side) !== null) {
                return $side;
            }
        }

        return null;
    }

    private function wouldClose(string $side): ?string
    {
        if ($this->decided()) {
            return null;
        }
        $h = $this->home + ($side === 'home' ? 1 : 0);
        $a = $this->away + ($side === 'away' ? 1 : 0);
        if (! SportRules::setIsWon($h, $a, $this->target())) {
            return null;
        }
        $sets = ($side === 'home' ? $this->setsHome : $this->setsAway) + 1;

        return $sets > intdiv($this->bestOf, 2) ? 'match' : 'set';
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $t = $this->target();
        $decided = $this->decided();
        $setPoint = null;
        $matchPoint = null;
        foreach (['home', 'away'] as $side) {
            $closes = $this->wouldClose($side);
            if ($closes === 'match') {
                $matchPoint = $side;
            } elseif ($closes === 'set') {
                $setPoint = $side;
            }
        }
        $server = $decided ? null : $this->currentServer();
        $serverScore = $server === null ? null : ($server === 'home' ? $this->home : $this->away);

        return [
            'best_of' => $this->bestOf,
            'set_noun' => SportRules::setNoun($this->sport),
            'sets' => $this->completed,
            'current' => $decided ? null : [$this->home, $this->away],
            'set_index' => $this->index,
            'target' => $t['target'],
            'cap' => $t['cap'],
            'decider' => $this->isDecider(),
            'serving' => $server,
            'serves_left' => $decided ? null : $this->servesLeft(),
            // Badminton's service court follows the SERVER's own score: even right, odd left.
            'service_court' => $this->sport === 'badminton' && $serverScore !== null
                ? ($serverScore % 2 === 0 ? 'right' : 'left')
                : null,
            'deuce' => ! $decided && $this->home >= $t['target'] - 1 && $this->away >= $t['target'] - 1
                && $this->home === $this->away,
            'golden_point' => ! $decided && $t['cap'] !== null
                && $this->home === $t['cap'] - 1 && $this->away === $t['cap'] - 1,
            'set_point' => $setPoint,
            'match_point' => $matchPoint,
            'rotation' => $this->sport === 'volleyball' ? [$this->rotation['home'], $this->rotation['away']] : null,
            'timeouts' => [$this->timeouts['home'], $this->timeouts['away']],
            'timeouts_allowed' => $this->sport === 'volleyball' ? (int) ($this->format['timeouts'] ?? 2)
                : ($this->sport === 'table_tennis' ? 1 : null),
            'decided' => $decided,
            'rally_stats' => $this->stats,
        ];
    }
}
