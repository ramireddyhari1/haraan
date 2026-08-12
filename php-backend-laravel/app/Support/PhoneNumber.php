<?php

declare(strict_types=1);

namespace App\Support;

/**
 * One place that decides what "9876543210", "+91 98765 43210" and "09876543210"
 * all mean, because every transport needs the same answer and metering keys on it.
 *
 * If WhatsApp normalised differently from SMS, the same customer would appear as
 * two recipients in the messaging ledger — two conversations, two charges, one
 * person. So both channels come through here.
 */
final class PhoneNumber
{
    /** Normalise to E.164 (+CCXXXXXXXXXX), defaulting bare 10-digit numbers to $defaultCountry. */
    public static function e164(string $phone, string $defaultCountry = '91'): string
    {
        $phone = trim($phone);

        if (str_starts_with($phone, '+')) {
            return '+' . preg_replace('/\D/', '', $phone);
        }

        $digits = (string) preg_replace('/\D/', '', $phone);
        $cc = (string) preg_replace('/\D/', '', $defaultCountry);

        // Trunk-prefixed local numbers (0XXXXXXXXXX) — drop the leading zero(s).
        $digits = ltrim($digits, '0');

        return strlen($digits) === 10 ? '+' . $cc . $digits : '+' . $digits;
    }

    /**
     * Normalise a messaging address for whichever channel it belongs to.
     *
     * The messaging ledger keys conversations, opt-outs and window lookups on this,
     * and it has to give the same answer no matter which side of the exchange the
     * address came from: an outbound send holds "9876543210" off a booking, the
     * inbound webhook hands back "919876543210", and both are one person with one
     * 24-hour window and one bill.
     *
     * Instagram is deliberately passed through untouched — its recipients are
     * scoped ids, not numbers, and running one through the phone rules would turn
     * it into a different id entirely.
     *
     * 'sms' is still listed although nothing sends SMS any more: the ledger holds
     * historical sms rows, and a report that reads them has to key on the same
     * spelling everything else does.
     */
    public static function forChannel(string $channel, string $address, string $defaultCountry = '91'): string
    {
        return in_array($channel, ['whatsapp', 'sms'], true)
            ? self::e164($address, $defaultCountry)
            : $address;
    }

    /** Whether a normalised number is plausibly routable (E.164 allows 8–15 digits). */
    public static function isRoutable(string $e164): bool
    {
        return preg_match('/^\+\d{8,15}$/', $e164) === 1;
    }
}
