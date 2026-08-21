<?php

declare(strict_types=1);

namespace App\Support;

/**
 * What each sport's score MEANS, in one place.
 *
 * Five sports arrived at once (volleyball, basketball, kabaddi, tennis, table tennis) and
 * the tempting move was five copies of football's "count the goals" loop. They don't
 * actually differ five ways — they differ three ways, and the three are about what a
 * scoring event does to the board:
 *
 *   TALLY   football        one goal = one point, forever. The scoreline is the count.
 *   POINTS  basketball,     a point event carries a VALUE (a three, a two-point raid,
 *           kabaddi         an all-out). The scoreline is the sum of those values.
 *   SETS    volleyball,     rally points fill a set; winning a set resets the rally
 *           table tennis,   count. The scoreline is SETS won — the points per set live
 *           badminton       in sport_state.
 *   TENNIS  tennis          points climb 15/30/40/deuce into games, games into sets.
 *                           Its own family because nothing else counts like this.
 *
 * Everything here is derived from recorded events. Nothing trusts a client-sent score,
 * which is the property that makes a live board worth showing at all.
 */
final class SportRules
{
    public const TALLY = 'tally';
    public const POINTS = 'points';
    public const SETS = 'sets';
    public const TENNIS = 'tennis';

    /** Every sport the ActionBoard can create AND score end to end. */
    public const SUPPORTED = [
        'cricket', 'football', 'badminton',
        'volleyball', 'basketball', 'kabaddi', 'tennis', 'table_tennis',
    ];

    /** Which scoring engine a sport uses. Cricket never reaches here — it has its own. */
    public static function family(string $sport): string
    {
        return match (self::normalise($sport)) {
            'basketball', 'kabaddi' => self::POINTS,
            'volleyball', 'table_tennis', 'badminton' => self::SETS,
            'tennis' => self::TENNIS,
            default => self::TALLY,
        };
    }

    /** Accepts "Table Tennis", "table-tennis", "TABLE_TENNIS" — all one sport. */
    public static function normalise(string $sport): string
    {
        $key = strtolower(trim($sport));
        $key = str_replace([' ', '-'], '_', $key);

        return $key === '' ? 'cricket' : $key;
    }

    /**
     * How much a scoring event is worth.
     *
     * Basketball puts the value in `detail` ("1"/"2"/"3" — free throw, field goal, three).
     * Kabaddi names the move instead, because "tackle" and "bonus" are what a scorer taps
     * and their values are rules of the sport, not arithmetic a phone should be trusted
     * with: raid/tackle/bonus score 1, an all-out 2, a super raid 3.
     *
     * An unrecognised detail is worth the sport's ordinary point — a point that was
     * definitely scored counting as one is a smaller lie than a point that vanishes.
     */
    public static function pointValue(string $sport, ?string $detail): int
    {
        $sport = self::normalise($sport);
        $detail = strtolower(trim((string) $detail));

        if ($sport === 'basketball') {
            return match ($detail) {
                '3', 'three' => 3,
                '1', 'free_throw', 'ft' => 1,
                default => 2,   // a bare tap on a basketball court is a field goal
            };
        }

        if ($sport === 'kabaddi') {
            return match ($detail) {
                'all_out' => 2,
                'super_raid' => 3,
                default => 1,   // raid, tackle, bonus, technical
            };
        }

        return 1;
    }

    /**
     * The target for one set: points to win, the margin needed, and any hard cap.
     *
     * The deciding set is shorter in volleyball (15, not 25) — the rule everyone who has
     * played knows and every naive implementation misses. `cap` is badminton's 30, where
     * win-by-two stops applying and the next point simply takes it; null means the set
     * runs on until somebody is two clear.
     *
     * @param  array<string, mixed>  $format  sport_state.format, as the creator set it
     * @return array{target: int, winBy: int, cap: int|null}
     */
    public static function setTarget(string $sport, int $setIndex, array $format = []): array
    {
        $sport = self::normalise($sport);
        $bestOf = (int) ($format['bestOf'] ?? self::defaultBestOf($sport));
        $isDecider = $bestOf > 1 && $setIndex === $bestOf - 1;

        return match ($sport) {
            'volleyball' => [
                'target' => $isDecider ? 15 : (int) ($format['pointsTo'] ?? 25),
                'winBy' => 2,
                'cap' => null,
            ],
            'table_tennis' => [
                'target' => (int) ($format['pointsTo'] ?? 11),
                'winBy' => 2,
                'cap' => null,
            ],
            'badminton' => [
                'target' => (int) ($format['pointsTo'] ?? 21),
                'winBy' => 2,
                'cap' => 30,
            ],
            default => [
                'target' => (int) ($format['pointsTo'] ?? 21),
                'winBy' => 2,
                'cap' => null,
            ],
        };
    }

    /** Sets/games needed to take the match, when the creator didn't say. */
    public static function defaultBestOf(string $sport): int
    {
        return match (self::normalise($sport)) {
            'volleyball' => 5,
            default => 3,
        };
    }

    /**
     * Is this set finished?
     *
     * @param  array{target: int, winBy: int, cap: int|null}  $target
     */
    public static function setIsWon(int $a, int $b, array $target): bool
    {
        $cap = $target['cap'];
        if ($cap !== null && ($a >= $cap || $b >= $cap)) {
            return true;
        }

        return ($a >= $target['target'] || $b >= $target['target'])
            && abs($a - $b) >= $target['winBy'];
    }

    /** What a period is called, so a basketball board never says "2nd half". */
    public static function periodLabel(string $sport, int $period): string
    {
        return match (self::normalise($sport)) {
            'basketball' => 'Q'.$period,
            'kabaddi', 'football' => $period <= 1 ? '1st half' : '2nd half',
            default => 'Period '.$period,
        };
    }

    /** What one set is called to a player of this sport. */
    public static function setNoun(string $sport): string
    {
        return self::normalise($sport) === 'badminton' ? 'Game' : 'Set';
    }

    /** How many periods the sport runs, for the clock/period chip. */
    public static function periodCount(string $sport, array $format = []): int
    {
        return match (self::normalise($sport)) {
            'basketball' => (int) ($format['periods'] ?? 4),
            'kabaddi' => 2,
            'football' => (int) ($format['halves'] ?? 2),
            default => 1,
        };
    }

    /**
     * Tennis' point ladder. Three-all and beyond is deuce/advantage, never "40–40",
     * and the side without the advantage shows nothing rather than a wrong number.
     */
    public static function tennisPointLabel(int $mine, int $theirs): string
    {
        if ($mine >= 3 && $theirs >= 3) {
            if ($mine === $theirs) {
                return '40';
            }

            return $mine > $theirs ? 'AD' : '40';
        }

        return match ($mine) {
            0 => '0',
            1 => '15',
            2 => '30',
            default => '40',
        };
    }
}
