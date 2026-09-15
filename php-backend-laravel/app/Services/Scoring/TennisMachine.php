<?php

declare(strict_types=1);

namespace App\Services\Scoring;

/**
 * Tennis, one point at a time.
 *
 * A pure state machine: the score engine and the insights replay both feed it the same event
 * log, so a tie-break on the board is the tie-break in the insights, and neither can drift.
 *
 * The rules that make a tennis board worth trusting, and that the first engine got wrong:
 *
 *  · The SERVER holds serve for a whole game and the serve alternates game by game. It is not
 *    "whoever won the last point" — that is rally scoring, a different family of sports.
 *  · At 6–6 the set goes to a TIE-BREAK (first to 7, two clear), not on forever. The player
 *    whose turn it is serves the first tie-break point, then the serve changes every two
 *    points; whoever received first in the tie-break serves first in the next set.
 *  · A BREAK POINT is the receiver being one point from the game. 0–40 is three of them.
 *
 * Who served first is a fact only the scorer knows. Until a `serve` event says so, the server
 * is null — the board shows no serve dot and no break points rather than guessing.
 */
final class TennisMachine
{
    public int $pointsHome = 0;
    public int $pointsAway = 0;
    public int $gamesHome = 0;
    public int $gamesAway = 0;
    public int $setsHome = 0;
    public int $setsAway = 0;

    /** @var array<int, array{0: int, 1: int, tb?: int}> finished sets, oldest first */
    public array $completed = [];

    public bool $tiebreak = false;

    /** Server of the current game (or of the NEXT tie-break point). Null until known. */
    public ?string $server = null;

    /** Who served the first point of the tie-break in progress. */
    private ?string $tiebreakFirst = null;

    /** @var array<string, array<string, int>> */
    public array $stats;

    private int $bestOf;
    private int $gamesTo;
    private int $tiebreakTo;
    private bool $finalSetTiebreak;

    /** No-ad scoring: at deuce the next point takes the game (one deciding point). */
    private bool $noAd;

    /** The deciding set is played as a single match tie-break to 10 instead of a full set. */
    private bool $finalSetSuperTiebreak;

    /** The tie-break in progress is that match tie-break. */
    public bool $superTiebreak = false;

    /** @param array<string, mixed> $format */
    public function __construct(array $format = [])
    {
        $this->bestOf = max(1, (int) ($format['bestOf'] ?? 3));
        $this->gamesTo = max(1, (int) ($format['gamesTo'] ?? 6));
        $this->tiebreakTo = max(1, (int) ($format['tiebreakTo'] ?? 7));
        // A final set can be played as an advantage set; the default is the modern tie-break.
        $this->finalSetTiebreak = (bool) ($format['finalSetTiebreak'] ?? true);
        $this->noAd = (bool) ($format['noAd'] ?? false);
        $this->finalSetSuperTiebreak = strtolower((string) ($format['finalSet'] ?? '')) === 'super_tiebreak';

        $blank = [
            'aces' => 0, 'double_faults' => 0, 'winners' => 0, 'errors' => 0,
            'service_games' => 0, 'service_games_held' => 0,
            'break_points_faced' => 0, 'break_points_saved' => 0,
            'break_points_won' => 0, 'break_point_chances' => 0,
            'breaks' => 0, 'points_won' => 0,
        ];
        $this->stats = ['home' => $blank, 'away' => $blank];
    }

    public static function other(string $side): string
    {
        return $side === 'home' ? 'away' : 'home';
    }

    public function decided(): bool
    {
        $need = intdiv($this->bestOf, 2) + 1;

        return $this->setsHome >= $need || $this->setsAway >= $need;
    }

    /** Who is serving the point about to be played. */
    public function currentServer(): ?string
    {
        if (! $this->tiebreak || $this->tiebreakFirst === null) {
            return $this->server;
        }
        $played = $this->pointsHome + $this->pointsAway;
        $block = intdiv($played + 1, 2);

        return $block % 2 === 0 ? $this->tiebreakFirst : self::other($this->tiebreakFirst);
    }

    /**
     * The scorer says who is serving NOW. Alternation carries on from here — this is both
     * "who served first" at 0–0 and a mid-match correction.
     */
    public function setServer(string $side): void
    {
        if ($this->tiebreak) {
            $played = $this->pointsHome + $this->pointsAway;
            $block = intdiv($played + 1, 2);
            $this->tiebreakFirst = $block % 2 === 0 ? $side : self::other($side);
        }
        $this->server = $side;
    }

    /**
     * Break points available to the receiver on the point about to be played — 0–40 is three.
     * None in a tie-break (a lost serve there is a mini-break, not a break) or when the server
     * is unknown.
     */
    public function breakPoints(): int
    {
        $server = $this->currentServer();
        if ($server === null || $this->tiebreak || $this->decided()) {
            return 0;
        }
        $s = $server === 'home' ? $this->pointsHome : $this->pointsAway;
        $r = $server === 'home' ? $this->pointsAway : $this->pointsHome;

        // No-ad: the deciding point at deuce is a break point too.
        if ($this->noAd && $r >= 3 && $s >= 3) {
            return 1;
        }

        return ($r >= 3 && $r > $s) ? $r - $s : 0;
    }

    /**
     * Play one point.
     *
     * @return array{closes: string|null, server: string|null, break_points: int, tiebreak: bool, deuce: bool}
     *         the situation the point was played IN, and what it finished
     */
    public function point(string $side, string $detail = ''): array
    {
        $server = $this->currentServer();
        $before = [
            'server' => $server,
            'break_points' => $this->breakPoints(),
            'tiebreak' => $this->tiebreak,
            'deuce' => ! $this->tiebreak && $this->pointsHome >= 3 && $this->pointsHome === $this->pointsAway,
        ];

        if ($this->decided()) {
            return $before + ['closes' => null];
        }

        $detail = strtolower(trim($detail));
        $this->stats[$side]['points_won']++;
        if ($detail === 'ace') {
            $this->stats[$side]['aces']++;
        } elseif ($detail === 'double_fault') {
            // A double fault is a point to the RECEIVER; the fault belongs to the other side.
            $this->stats[self::other($side)]['double_faults']++;
        } elseif ($detail === 'winner') {
            $this->stats[$side]['winners']++;
        } elseif ($detail === 'error') {
            $this->stats[self::other($side)]['errors']++;
        }

        if ($before['break_points'] > 0 && $server !== null) {
            $receiver = self::other($server);
            $this->stats[$server]['break_points_faced']++;
            $this->stats[$receiver]['break_point_chances']++;
            if ($side === $server) {
                $this->stats[$server]['break_points_saved']++;
            } else {
                $this->stats[$receiver]['break_points_won']++;
            }
        }

        if ($side === 'home') {
            $this->pointsHome++;
        } else {
            $this->pointsAway++;
        }

        $closes = $this->tiebreak ? $this->settleTiebreak($server) : $this->settleGame($server);

        return $before + ['closes' => $closes];
    }

    private function settleGame(?string $server): ?string
    {
        $h = $this->pointsHome;
        $a = $this->pointsAway;
        $decidingPointWon = $this->noAd && max($h, $a) >= 4 && min($h, $a) >= 3;
        if (max($h, $a) < 4 || (abs($h - $a) < 2 && ! $decidingPointWon)) {
            return null;
        }

        $winner = $h > $a ? 'home' : 'away';
        if ($server !== null) {
            $this->stats[$server]['service_games']++;
            if ($winner === $server) {
                $this->stats[$server]['service_games_held']++;
            } else {
                $this->stats[$winner]['breaks']++;
            }
        }

        $this->pointsHome = 0;
        $this->pointsAway = 0;
        $winner === 'home' ? $this->gamesHome++ : $this->gamesAway++;
        // Serve alternates every game, across set boundaries too.
        $this->server = $server === null ? null : self::other($server);

        $gh = $this->gamesHome;
        $ga = $this->gamesAway;
        if (max($gh, $ga) >= $this->gamesTo && abs($gh - $ga) >= 2) {
            $this->closeSet(null);

            return $this->decided() ? 'match' : 'set';
        }

        if ($gh === $this->gamesTo && $ga === $this->gamesTo && $this->tiebreakApplies()) {
            $this->tiebreak = true;
            $this->tiebreakFirst = $this->server;
        }

        return 'game';
    }

    private function settleTiebreak(?string $server): ?string
    {
        $h = $this->pointsHome;
        $a = $this->pointsAway;
        $to = $this->superTiebreak ? 10 : $this->tiebreakTo;
        if (max($h, $a) < $to || abs($h - $a) < 2) {
            return null;
        }
        $this->superTiebreak = false;

        $winner = $h > $a ? 'home' : 'away';
        $loserPoints = min($h, $a);
        $winner === 'home' ? $this->gamesHome++ : $this->gamesAway++;

        // Whoever RECEIVED first in the tie-break serves the next set's first game.
        $this->server = $this->tiebreakFirst === null ? null : self::other($this->tiebreakFirst);
        $this->pointsHome = 0;
        $this->pointsAway = 0;
        $this->tiebreak = false;
        $this->tiebreakFirst = null;
        $this->closeSet($loserPoints);

        return $this->decided() ? 'match' : 'set';
    }

    private function closeSet(?int $tiebreakLoserPoints): void
    {
        $set = [$this->gamesHome, $this->gamesAway];
        if ($tiebreakLoserPoints !== null) {
            $set['tb'] = $tiebreakLoserPoints;
        }
        $this->completed[] = $set;
        $this->gamesHome > $this->gamesAway ? $this->setsHome++ : $this->setsAway++;
        $this->gamesHome = 0;
        $this->gamesAway = 0;

        // One set all (or two all): the decider is a single match tie-break when the format
        // says so. It is scored as a set won 1–0 with the tie-break points alongside.
        $half = intdiv($this->bestOf, 2);
        if ($this->finalSetSuperTiebreak && ! $this->decided() && $this->setsHome === $half && $this->setsAway === $half) {
            $this->tiebreak = true;
            $this->superTiebreak = true;
            $this->tiebreakFirst = $this->server;
        }
    }

    private function tiebreakApplies(): bool
    {
        $finalSet = $this->setsHome === intdiv($this->bestOf, 2) && $this->setsAway === intdiv($this->bestOf, 2);

        return ! $finalSet || $this->finalSetTiebreak;
    }

    /** Would `$side` taking the next point finish the set, or the match? */
    public function pointWouldClose(string $side): ?string
    {
        if ($this->decided()) {
            return null;
        }
        $probe = clone $this;
        $closes = $probe->point($side)['closes'];

        return in_array($closes, ['set', 'match'], true) ? $closes : null;
    }

    /** @return array{0: string, 1: string} what each side's point column reads */
    public function pointLabels(): array
    {
        if ($this->tiebreak) {
            return [(string) $this->pointsHome, (string) $this->pointsAway];
        }

        return [
            \App\Support\SportRules::tennisPointLabel($this->pointsHome, $this->pointsAway),
            \App\Support\SportRules::tennisPointLabel($this->pointsAway, $this->pointsHome),
        ];
    }

    /** @return array<string, mixed> the live situation the board draws */
    public function snapshot(): array
    {
        $setPoint = null;
        $matchPoint = null;
        foreach (['home', 'away'] as $side) {
            $closes = $this->pointWouldClose($side);
            if ($closes === 'match') {
                $matchPoint = $side;
            } elseif ($closes === 'set') {
                $setPoint = $side;
            }
        }

        $server = $this->decided() ? null : $this->currentServer();
        $advantage = null;
        if (! $this->tiebreak && $this->pointsHome >= 3 && $this->pointsAway >= 3 && $this->pointsHome !== $this->pointsAway) {
            $advantage = $this->pointsHome > $this->pointsAway ? 'home' : 'away';
        }

        return [
            'best_of' => $this->bestOf,
            'games_to' => $this->gamesTo,
            'set_noun' => 'Set',
            'sets' => array_map(static fn (array $s): array => [$s[0], $s[1]], $this->completed),
            'tiebreaks' => array_map(static fn (array $s): ?int => $s['tb'] ?? null, $this->completed),
            'games' => [$this->gamesHome, $this->gamesAway],
            'points' => $this->pointLabels(),
            'raw_points' => [$this->pointsHome, $this->pointsAway],
            'serving' => $server,
            'tiebreak' => $this->tiebreak,
            'super_tiebreak' => $this->superTiebreak,
            'no_ad' => $this->noAd,
            'deuce' => ! $this->tiebreak && $this->pointsHome >= 3 && $this->pointsHome === $this->pointsAway,
            'advantage' => $advantage,
            'break_points' => $this->breakPoints(),
            'set_point' => $setPoint,
            'match_point' => $matchPoint,
            'decided' => $this->decided(),
            'tennis_stats' => $this->stats,
        ];
    }
}
