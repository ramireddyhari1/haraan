<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * "Which partner account owns this phone number?"
 *
 * Extracted so every partner sign-in path asks it the same way. Two of them now
 * exist — Firebase SMS and WhatsApp OTP — and two copies of this logic would be
 * genuinely dangerous: a number that matches on one path and not the other means a
 * partner who can log in on Tuesday and not on Wednesday, with nothing in the logs
 * to explain it.
 */
final class PartnerLookup
{
    /**
     * Match a verified E.164 number against however the partner's phone happens to
     * be stored.
     *
     * Verified numbers always arrive as +91XXXXXXXXXX, but admin-entered numbers
     * live in the database in every shape — bare 10 digits, a leading 0, "91…",
     * "+91…" — so an exact `where('phone', $e164)` silently misses and the partner
     * is told no account exists. Compare on the last 10 digits across the common
     * variants instead.
     */
    public static function byPhone(string $e164): ?User
    {
        $digits = preg_replace('/\D/', '', $e164) ?? '';
        $local = substr($digits, -10);

        $candidates = ($local !== '' && strlen($local) >= 10)
            ? array_unique([$e164, $local, '+91' . $local, '91' . $local, '0' . $local])
            : [$e164];

        $matches = User::query()->whereIn('phone', $candidates)->get();

        if ($matches->isEmpty()) {
            return null;
        }

        // The same digits can sit on more than one account — e.g. a member signed up
        // with the number AND a partner has it too. On a partner login the partner
        // must win, so prefer a PARTNER-role match over any member that merely
        // shares the digits.
        return $matches->first(fn (User $u): bool => $u->hasRoleEither(['PARTNER'])) ?? $matches->first();
    }

    /** Whether this number belongs to an account that may use the partner console. */
    public static function isPartner(string $e164): bool
    {
        return self::byPhone($e164)?->hasRoleEither(['PARTNER']) === true;
    }
}
