<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * "The bowler is running in" — the moment between the scorer tapping BALL and tapping the
 * result. Viewers see a delivery animation for exactly this window, and a paired camera
 * phone records exactly this window.
 *
 * Kept in the cache, NEVER in match_actions: it is not a ball. Writing it to the log would
 * make UNDO remove the signal instead of the last delivery, and every replay (scorecard,
 * commentary, careers) would have to learn to skip it.
 *
 * Every BALL gets a number (`seq`) so the camera can tell one delivery from the next and
 * tag its clip with it, and so the scorer's REVIEW can ask for THAT ball's clip. How the
 * last ball ended is remembered too: a dead ball's footage is thrown away on the camera.
 *
 * Any real scoring action ends the window (see MatchesController::scoreAction). The TTL is
 * only the backstop for a scorer who taps BALL and then walks away — a viewer must never
 * be left watching a ball that is bowled forever.
 */
final class BallInPlay
{
    public const TTL_SECONDS = 90;

    /** How long the ball counter and the last ending are kept: longer than any match day. */
    private const MEMORY_SECONDS = 86400;

    private static function key(int|string $matchId, string $what): string
    {
        return 'match:'.$matchId.':ball_'.$what;
    }

    /** The bowler is running in. Returns this delivery's number. */
    public static function start(int|string $matchId): int
    {
        // Read-then-write rather than Cache::increment: only the match's own scorer ever
        // gets here, one tap at a time, and a plain value survives every cache driver.
        $seq = (int) Cache::get(self::key($matchId, 'seq'), 0) + 1;
        Cache::put(self::key($matchId, 'seq'), $seq, self::MEMORY_SECONDS);
        Cache::put(self::key($matchId, 'in_play'), ['seq' => $seq, 'at' => now()->toIso8601String()], self::TTL_SECONDS);

        return $seq;
    }

    /**
     * The ball is over: its result was scored (or undone), or — `$cancelled` — it was called
     * dead before anything happened. A no-op when no ball is in play.
     */
    public static function clear(int|string $matchId, bool $cancelled = false): void
    {
        $current = Cache::get(self::key($matchId, 'in_play'));
        if (! is_array($current)) {
            return;
        }
        Cache::put(self::key($matchId, 'ended'), ['seq' => (int) $current['seq'], 'cancelled' => $cancelled], self::MEMORY_SECONDS);
        Cache::forget(self::key($matchId, 'in_play'));
    }

    /** When the current ball began, or null when no ball is in play. */
    public static function since(int|string $matchId): ?Carbon
    {
        $current = Cache::get(self::key($matchId, 'in_play'));

        return is_array($current) && ! empty($current['at']) ? Carbon::parse($current['at']) : null;
    }

    /** The number of the most recent BALL, 0 before the first. */
    public static function lastSeq(int|string $matchId): int
    {
        return (int) Cache::get(self::key($matchId, 'seq'), 0);
    }

    /**
     * What a paired camera needs to know, in one read: which ball is the latest, is it
     * still being bowled, and — once it is over — was it called dead.
     *
     * @return array{seq: int, inPlay: bool, cancelled: bool}
     */
    public static function cue(int|string $matchId): array
    {
        $seq = self::lastSeq($matchId);
        $ended = Cache::get(self::key($matchId, 'ended'));

        return [
            'seq' => $seq,
            'inPlay' => self::since($matchId) !== null,
            'cancelled' => is_array($ended) && (int) $ended['seq'] === $seq && (bool) $ended['cancelled'],
        ];
    }
}
