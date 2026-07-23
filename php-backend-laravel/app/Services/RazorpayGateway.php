<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper over Razorpay's REST API (no SDK — packagist is unreachable in this
 * environment). Creates orders with HTTP basic auth and verifies payment signatures with a
 * constant-time HMAC check. The KEY_SECRET is read from config and never leaves the server.
 */
final class RazorpayGateway
{
    public const MIN_AMOUNT_PAISE = 100;

    private const ORDERS_ENDPOINT = 'https://api.razorpay.com/v1/orders';

    /** Whether both keys are configured — callers gate the whole payment path on this. */
    public function isConfigured(): bool
    {
        return $this->keyId() !== null && $this->keySecret() !== null;
    }

    /** The public key id, safe to hand to the browser/app so it can open checkout. */
    public function publicKey(): ?string
    {
        return $this->keyId();
    }

    /**
     * Create a Razorpay order for the given amount (in paise). Returns the decoded order.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException  On misconfiguration, auth failure, or an unreachable/again-failing API.
     */
    public function createOrder(int $amountPaise, string $receipt, string $currency = 'INR'): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Payments are not configured.', 500);
        }

        if ($amountPaise < self::MIN_AMOUNT_PAISE) {
            throw new RuntimeException('Amount is below the minimum.', 422);
        }

        try {
            $response = Http::withBasicAuth($this->keyId(), $this->keySecret())
                ->acceptJson()
                ->timeout(20)
                ->post(self::ORDERS_ENDPOINT, [
                    'amount'          => $amountPaise,
                    'currency'        => strtoupper($currency),
                    'receipt'         => $receipt,
                    'payment_capture' => 1,
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach the payment provider.', 502);
        }

        if ($response->status() === 401) {
            throw new RuntimeException('Payment authentication failed.', 401);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Could not create the payment order.', 500);
        }

        return $response->json();
    }

    /**
     * Verify a Razorpay checkout signature: HMAC-SHA256(order_id|payment_id, secret) must equal
     * the returned signature (constant-time). Returns false on any mismatch or missing secret.
     */
    public function verifySignature(string $orderId, string $paymentId, string $signature): bool
    {
        $secret = $this->keySecret();

        if ($secret === null) {
            return false;
        }

        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, $secret);

        return hash_equals($expected, $signature);
    }

    private function keyId(): ?string
    {
        $key = config('services.razorpay.key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    private function keySecret(): ?string
    {
        $secret = config('services.razorpay.secret');

        return is_string($secret) && $secret !== '' ? $secret : null;
    }
}
