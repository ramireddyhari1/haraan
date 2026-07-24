<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Validates Twilio's X-Twilio-Signature header.
 *
 * The webhook is a public, unauthenticated endpoint that can create opt-outs and
 * trigger replies, so this is the only thing standing between it and anyone who
 * knows the URL. Without it, a stranger could POST "STOP" as any phone number and
 * silence a customer, or make Haraan message a number of their choosing.
 *
 * Twilio's algorithm: take the full URL exactly as they called it, append every
 * POST parameter sorted by name as key+value with no separators, HMAC-SHA1 it
 * with the account auth token, base64 the result.
 *
 * @see https://www.twilio.com/docs/usage/security#validating-requests
 */
final class TwilioSignature
{
    /**
     * @param  array<string, mixed>  $params  the POST body
     */
    public static function isValid(string $signature, string $url, array $params, string $authToken): bool
    {
        if ($signature === '' || $authToken === '') {
            return false;
        }

        $payload = $url;

        // Sort by key, then concatenate key and value. Twilio sorts with the
        // plain byte comparison ksort gives.
        ksort($params);

        foreach ($params as $key => $value) {
            // Nested params (rare on this webhook) are flattened the same way
            // Twilio's own helper libraries do.
            $payload .= $key . (is_array($value) ? implode('', $value) : (string) $value);
        }

        $expected = base64_encode(hash_hmac('sha1', $payload, $authToken, true));

        // Constant-time compare: a timing side channel here would leak the token
        // one byte at a time.
        return hash_equals($expected, $signature);
    }
}
