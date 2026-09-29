<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The strokes a scorer can name, and the only ones insights will ever count.
 *
 * The app sends a key with the ball (`"shot": "cover_drive"`); anything not in this list
 * is ignored rather than displayed, so a typo or an old client can never put an invented
 * stroke on somebody's innings. Labels here are the server's; the app draws its own
 * figure for each key.
 */
final class CricketShots
{
    /** @var array<string, string> */
    public const TYPES = [
        'straight_drive' => 'Straight drive',
        'cover_drive' => 'Cover drive',
        'on_drive' => 'On drive',
        'square_drive' => 'Square drive',
        'cut' => 'Cut',
        'pull' => 'Pull',
        'hook' => 'Hook',
        'flick' => 'Flick',
        'leg_glance' => 'Leg glance',
        'sweep' => 'Sweep',
        'slog_sweep' => 'Slog sweep',
        'reverse_sweep' => 'Reverse sweep',
        'scoop' => 'Scoop',
        'lofted' => 'Lofted shot',
        'slog' => 'Slog',
        'edge' => 'Edge',
    ];

    public static function normalise(mixed $key): ?string
    {
        if (! is_string($key)) {
            return null;
        }
        $key = strtolower(trim($key));

        return isset(self::TYPES[$key]) ? $key : null;
    }

    public static function label(string $key): string
    {
        return self::TYPES[$key] ?? $key;
    }
}
