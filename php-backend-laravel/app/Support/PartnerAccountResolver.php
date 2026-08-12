<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Resolves the account behind an email an admin is about to make a partner.
 *
 * haraan.app/login and haraan.app/partner/login look like two separate places to
 * sign in, but they are two doors into the SAME `users` table — the public login
 * turns PARTNER accounts away ({@see \App\Http\Controllers\Auth\WebPasswordAuthController}),
 * and the partner login turns everyone else away
 * ({@see \App\Http\Controllers\Auth\PartnerAuthController}). One email is therefore
 * always exactly one row, and it cannot be two accounts on two doors.
 *
 * That made "The email has already been taken" a dead end on the Create Partner
 * form, because the most common case is not a mistake: a venue owner or event host
 * usually booked on Haraan long before they listed anything, so their email is
 * already a member row. The right move is to UPGRADE that member in place — they
 * keep their id, bookings, wallet and history — not to demand a second email.
 *
 * This class answers the two questions the form needs: which account is behind
 * this email, and may it be upgraded?
 */
final class PartnerAccountResolver
{
    /**
     * Case-insensitive lookup. Emails are stored as entered, so every comparison
     * in this codebase lowers both sides — see [[status-casing]] style elsewhere.
     */
    public static function findByEmail(?string $email): ?User
    {
        $email = trim((string) $email);

        if ($email === '') {
            return null;
        }

        return User::query()
            ->whereRaw('lower(email) = ?', [mb_strtolower($email)])
            ->first();
    }

    /**
     * Null when this account may be upgraded to a partner; otherwise the reason it
     * may not, phrased for the admin reading the form.
     */
    public static function blockReason(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        if ($user->hasRoleEither(['PARTNER'])) {
            return 'This email is already a partner account — open it from the Partners list to edit it.';
        }

        // Never quietly demote an internal login into a tenant workspace.
        if ($user->isSuperAdmin() || $user->hasRoleEither(['FINANCE', 'MARKETING', 'OPS'])) {
            return 'This email belongs to an internal staff account and cannot be turned into a partner.';
        }

        return null;
    }

    /** True when saving the form will upgrade an existing member rather than create a row. */
    public static function isUpgrade(?string $email): bool
    {
        $existing = self::findByEmail($email);

        return $existing !== null && self::blockReason($existing) === null;
    }

    /** One line describing the member account an admin is about to upgrade. */
    public static function describe(User $user): string
    {
        $name = trim((string) $user->name) ?: 'Unnamed member';
        $since = $user->created_at?->format('M Y');

        return $since ? "{$name} · member since {$since}" : $name;
    }

    /**
     * Grant PARTNER to the account behind `$attributes['email']`, or create one.
     *
     * Shared by all three admin entry points (the Filament Partners form, the legacy
     * `POST admin/partners`, and `POST /api/partners`) so an email that already exists
     * behaves the same way everywhere instead of erroring differently in each.
     *
     * Only attributes the caller actually supplied are written, so a blank field on an
     * upgrade never wipes something the member already had. A blank password keeps the
     * existing account's password and gives a brand-new account a random one.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{0: User, 1: bool}  the account, and whether it was an upgrade
     *
     * @throws \RuntimeException when the existing account must not be turned into a partner
     */
    public static function upgradeOrCreate(array $attributes, ?string $password = null): array
    {
        $fields = ['name', 'phone', 'avatar', 'partner_type', 'status', 'event_host_id'];
        $existing = self::findByEmail($attributes['email'] ?? null);

        if ($existing && ($reason = self::blockReason($existing)) !== null) {
            throw new \RuntimeException($reason);
        }

        if (! $existing) {
            return [User::create(array_merge(
                array_filter(
                    array_intersect_key($attributes, array_flip($fields)),
                    static fn (mixed $v): bool => filled($v),
                ),
                [
                    'email' => $attributes['email'],
                    'password' => filled($password) ? $password : Str::random(16),
                    'role' => 'PARTNER',
                    'status' => filled($attributes['status'] ?? null) ? $attributes['status'] : 'active',
                ],
            )), false];
        }

        foreach ($fields as $field) {
            if (array_key_exists($field, $attributes) && filled($attributes[$field])) {
                $existing->{$field} = $attributes[$field];
            }
        }

        $existing->role = 'PARTNER';

        if (filled($password)) {
            $existing->password = $password;
        }

        $existing->save();

        return [$existing, true];
    }
}
