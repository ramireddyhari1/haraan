<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The dismissal rules cricket's three readers must agree on: the live board
 * (MatchesController::applyAction), the scorecard (LiveMatchController::buildInningsCards)
 * and the career replay (CareerBattingService). Before this they each assumed every wicket
 * belonged to the bowler and to the striker, so a run-out padded the bowler's figures and a
 * non-striker run out at the bowler's end recorded the wrong batter as out.
 */
final class CricketRules
{
    /** Normalise the scorer's dismissal key: "Run out", "run_out", "runout" → "runout". */
    public static function dismissal(array $payload): string
    {
        $how = strtolower(trim((string) ($payload['dismissal'] ?? $payload['wicket_type'] ?? '')));

        return str_replace([' ', '_', '-'], '', $how);
    }

    /**
     * Does the bowler get the wicket? Bowled, caught, LBW, stumped and hit wicket do. A
     * run-out, a retirement, obstruction, handling and timed out never do. An unrecorded
     * dismissal (every ball scored before the scorer asked) keeps the old credit — history
     * is not rewritten on a guess.
     */
    public static function bowlerCredited(array $payload): bool
    {
        return ! in_array(self::dismissal($payload), [
            'runout', 'retiredhurt', 'retired', 'retiredout', 'obstructingthefield',
            'obstructing', 'timedout', 'handledtheball', 'handled',
        ], true);
    }

    /** A batter who retires hurt is not out and does not count against the side. */
    public static function countsAsWicket(array $payload): bool
    {
        return self::dismissal($payload) !== 'retiredhurt';
    }

    /**
     * Which batter is out: the striker, unless the scorer marked the non-striker (a run-out at
     * the bowler's end). Only a run-out can remove the non-striker.
     */
    public static function nonStrikerOut(array $payload): bool
    {
        $role = strtolower(str_replace(['-', ' '], '_', trim((string) ($payload['out_role'] ?? ''))));

        return self::dismissal($payload) === 'runout' && in_array($role, ['non_striker', 'nonstriker'], true);
    }

    /** Scorebook text for a dismissal: "c Rahul b Arjun", "run out (Imran)", "b Arjun". */
    public static function dismissalText(array $payload, string $bowler, string $fielder = ''): string
    {
        $how = self::dismissal($payload);
        $b = trim($bowler);
        $f = trim($fielder);

        return match ($how) {
            'bowled' => $b !== '' ? "b {$b}" : 'bowled',
            'lbw' => 'lbw' . ($b !== '' ? " b {$b}" : ''),
            'caught' => match (true) {
                $f !== '' && $f === $b => "c & b {$b}",
                $f !== '' => "c {$f}" . ($b !== '' ? " b {$b}" : ''),
                default => $b !== '' ? "c b {$b}" : 'caught',
            },
            'runout' => $f !== '' ? "run out ({$f})" : 'run out',
            'stumped' => ($f !== '' ? "st {$f}" : 'st') . ($b !== '' ? " b {$b}" : ''),
            'hitwicket' => 'hit wicket' . ($b !== '' ? " b {$b}" : ''),
            'retiredhurt' => 'retired hurt',
            'retired', 'retiredout' => 'retired out',
            default => $b !== '' ? "b {$b}" : 'out',
        };
    }
}
