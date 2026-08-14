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

    private const PAYMENT_LINKS_ENDPOINT = 'https://api.razorpay.com/v1/payment_links';

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
     * Create a Razorpay **payment link** the desk can send to a walk-in customer
     * (WhatsApp/SMS), so "pay online" works without the customer being in our app.
     *
     * Razorpay owns the payment page; we only keep the returned `short_url` and `id`.
     * The booking is marked paid by the existing payment webhook, never by this call —
     * a link that was created is not money that arrived.
     *
     * @param  array<string, string>  $notes  Echoed back on the webhook (booking id, etc.).
     * @return array{id: string, short_url: string|null}
     *
     * @throws RuntimeException  On misconfiguration, auth failure, or an unreachable API.
     */
    public function createPaymentLink(
        int $amountPaise,
        string $description,
        ?string $customerName = null,
        ?string $customerPhone = null,
        array $notes = [],
    ): array {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Payments are not configured.', 500);
        }

        if ($amountPaise < self::MIN_AMOUNT_PAISE) {
            throw new RuntimeException('Amount is below the minimum.', 422);
        }

        $payload = [
            'amount'      => $amountPaise,
            'currency'    => 'INR',
            // Razorpay caps the description; keep it short and human.
            'description' => mb_substr($description, 0, 120),
            'notes'       => $notes,
            // Let Razorpay text the link too when we know the number.
            'notify'      => ['sms' => $customerPhone !== null, 'email' => false],
            'reminder_enable' => true,
        ];

        $customer = array_filter([
            'name'    => $customerName,
            'contact' => $customerPhone,
        ]);

        if ($customer !== []) {
            $payload['customer'] = $customer;
        }

        try {
            $response = Http::withBasicAuth($this->keyId(), $this->keySecret())
                ->acceptJson()
                ->timeout(20)
                ->post(self::PAYMENT_LINKS_ENDPOINT, $payload);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach the payment provider.', 502);
        }

        if ($response->status() === 401) {
            throw new RuntimeException('Payment authentication failed.', 401);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Could not create the payment link.', 500);
        }

        $link = $response->json();

        return [
            'id'        => (string) ($link['id'] ?? ''),
            'short_url' => $link['short_url'] ?? null,
        ];
    }

    /**
     * The id of a CAPTURED payment against a Razorpay order, or null when the order has not
     * been paid. Asks Razorpay directly, so it is the authority when our own record of an
     * order is in doubt — a buyer whose browser died after paying leaves us a reservation
     * that looks abandoned and a payment that very much happened.
     *
     * Only `captured` counts. An `authorized` payment is money held, not money taken, and it
     * can still fail; orders here are created with `payment_capture: 1`, so authorisation
     * turns into capture within seconds and the webhook picks up anything that lands late.
     *
     * @throws RuntimeException  When Razorpay can't be reached or answers with an error — the
     *                           caller must treat that as "don't know", never as "not paid".
     */
    public function capturedPaymentFor(string $orderId): ?string
    {
        if (! $this->isConfigured() || trim($orderId) === '') {
            return null;
        }

        try {
            $response = Http::withBasicAuth($this->keyId(), $this->keySecret())
                ->acceptJson()
                ->timeout(15)
                ->get(self::ORDERS_ENDPOINT . '/' . trim($orderId) . '/payments');
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach the payment provider.', 502);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Could not read the payment order.', 502);
        }

        foreach ((array) ($response->json('items') ?? []) as $payment) {
            if (! is_array($payment) || ($payment['status'] ?? null) !== 'captured') {
                continue;
            }

            $id = trim((string) ($payment['id'] ?? ''));

            if ($id !== '') {
                return $id;
            }
        }

        return null;
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
