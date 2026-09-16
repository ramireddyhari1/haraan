<?php

declare(strict_types=1);

namespace App\Support\Membership;

use App\Models\Tournament;

/**
 * The sports advanced insights are sold per. Same eight keys the app scores and tournaments
 * use (Tournament::sportKeys), so a selection always names a sport a match can have.
 */
final class InsightSports
{
    /** @return list<string> */
    public static function keys(): array
    {
        return Tournament::sportKeys();
    }

    public static function exists(string $sport): bool
    {
        return in_array($sport, self::keys(), true);
    }

    public static function label(string $sport): string
    {
        return Tournament::sportLabel($sport);
    }

    /**
     * A match's sport as a selection key. Matches store it loosely ("Table Tennis", null for
     * old cricket rows); anything unrecognised stays as-is so it can never match a selection.
     */
    public static function normalize(?string $sport): string
    {
        $key = strtolower(trim((string) $sport));
        $key = str_replace([' ', '-'], '_', $key);

        return $key === '' ? 'cricket' : $key;
    }
}
