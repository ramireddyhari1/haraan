<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * What we already know about the person buying, so checkout only asks for the rest.
 *
 * The one rule that matters: WhatsApp-OTP signup mints a placeholder address
 * (`<phone>@whatsapp.local`, see WhatsAppAuthController) purely to satisfy the
 * NOT NULL column. It is not an inbox — most of the user base has one — so it must
 * never be offered as "your email" or a ticket goes nowhere. Same for any other
 * internal `.local` address we may mint later.
 */
final class ContactPrefill
{
    /** @return array{name: string, email: string, phone: string} */
    public static function for(?User $user): array
    {
        if ($user === null) {
            return ['name' => '', 'email' => '', 'phone' => ''];
        }

        return [
            'name' => trim((string) $user->name),
            'email' => self::isRealEmail($user->email) ? trim((string) $user->email) : '',
            'phone' => trim((string) $user->phone),
        ];
    }

    /** A placeholder we minted is not an address anyone reads. */
    public static function isRealEmail(?string $email): bool
    {
        $email = trim((string) $email);

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Match on the DOMAIN, not the string tail: `<phone>@whatsapp.local` ends in
        // ".local", but the seeded `admin@local` does not — its domain is the dotless
        // `local`, which is just as undeliverable. Both are internal, neither is an inbox.
        $domain = mb_strtolower(Str::afterLast($email, '@'));

        return $domain !== 'local' && ! str_ends_with($domain, '.local');
    }
}
