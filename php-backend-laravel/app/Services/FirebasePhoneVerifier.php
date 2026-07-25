<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Verifies a Firebase phone-auth ID token minted in the browser (Firebase JS SDK)
 * and returns the confirmed phone number.
 *
 * Rather than pull in the Firebase Admin SDK just to validate one token, this posts
 * the token to Google's Identity Toolkit `accounts:lookup` REST endpoint with the
 * project's web API key. That endpoint only returns a user for a token it accepts,
 * so a successful response with a phoneNumber is proof the OTP was completed for
 * that number — the browser can't forge it.
 */
final class FirebasePhoneVerifier
{
    /** True only when a web API key is configured. */
    public function isConfigured(): bool
    {
        return $this->apiKey() !== null;
    }

    /**
     * @return string the E.164 phone number the token was issued for
     *
     * @throws RuntimeException when the token is missing, rejected, or has no phone
     */
    public function phoneFromIdToken(string $idToken): string
    {
        $apiKey = $this->apiKey();
        if ($apiKey === null) {
            throw new RuntimeException('Phone sign-in is not configured.');
        }

        $response = Http::timeout(15)->post(
            'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key='.$apiKey,
            ['idToken' => $idToken],
        );

        if (! $response->successful()) {
            throw new RuntimeException('We could not verify that code. Please try again.');
        }

        $user = $response->json('users.0');
        $phone = is_array($user) ? ($user['phoneNumber'] ?? null) : null;

        if (! is_string($phone) || $phone === '') {
            throw new RuntimeException('That sign-in did not include a phone number.');
        }

        return $phone;
    }

    private function apiKey(): ?string
    {
        $key = config('services.firebase.api_key');

        return is_string($key) && $key !== '' ? $key : null;
    }
}
