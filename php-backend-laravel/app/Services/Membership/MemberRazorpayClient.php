<?php

declare(strict_types=1);

namespace App\Services\Membership;

use App\Exceptions\PaymentsPaused;
use App\Models\MemberPlanPrice;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Razorpay's Plans and Subscriptions REST API, for member plans only.
 *
 * REST rather than the SDK for the same reason as RazorpayGateway (packagist is unreachable
 * from this environment). The key secret is read from config and never leaves the server;
 * only publicKey() is handed to the app.
 *
 * Every failure throws MembershipException carrying an HTTP status the controller can use
 * directly — callers must treat a thrown fetch as "don't know", never as "not paid".
 */
class MemberRazorpayClient
{
    private const BASE = 'https://api.razorpay.com/v1';

    public function isConfigured(): bool
    {
        return $this->keyId() !== null && $this->keySecret() !== null;
    }

    public function publicKey(): ?string
    {
        return $this->keyId();
    }

    /**
     * Create the Razorpay plan a member price charges against.
     *
     * @param  array<string, string>  $notes
     */
    public function createPlan(string $name, string $interval, int $amountPaise, string $currency, array $notes = []): string
    {
        // 3- and 6-month plans are Razorpay "monthly" plans billed every 3 or 6 periods.
        $term = MemberPlanPrice::razorpayTerm($interval);

        $response = $this->send('post', '/plans', [
            'period' => $term['period'],
            'interval' => $term['every'],
            'item' => [
                'name' => mb_substr($name, 0, 100),
                'amount' => $amountPaise,
                'currency' => strtoupper($currency),
            ],
            'notes' => $notes,
        ], 'Could not create the Razorpay plan.');

        $id = (string) ($response['id'] ?? '');
        if ($id === '') {
            throw new MembershipException('Razorpay did not return a plan id.', 502);
        }

        return $id;
    }

    /**
     * @param  array<string, string>  $notes
     * @return array<string, mixed>
     */
    public function createSubscription(string $planId, int $totalCount, ?int $startAt, int $expireBy, array $notes): array
    {
        // /control → Operations → Stop taking payments.
        PaymentsPaused::guard();

        $body = [
            'plan_id' => $planId,
            'total_count' => $totalCount,
            'quantity' => 1,
            // Razorpay sends the pre-debit notices RBI requires for recurring mandates.
            'customer_notify' => 1,
            'expire_by' => $expireBy,
            'notes' => $notes,
        ];

        if ($startAt !== null) {
            $body['start_at'] = $startAt;
        }

        $subscription = $this->send('post', '/subscriptions', $body, 'Could not start the subscription.');

        if (blank($subscription['id'] ?? null)) {
            throw new MembershipException('Razorpay did not return a subscription id.', 502);
        }

        return $subscription;
    }

    /** @return array<string, mixed> */
    public function fetchSubscription(string $subscriptionId): array
    {
        return $this->send('get', '/subscriptions/' . rawurlencode($subscriptionId), [], 'Could not read the subscription.');
    }

    /** @return array<string, mixed> */
    public function cancelSubscription(string $subscriptionId, bool $atCycleEnd): array
    {
        return $this->send(
            'post',
            '/subscriptions/' . rawurlencode($subscriptionId) . '/cancel',
            ['cancel_at_cycle_end' => $atCycleEnd ? 1 : 0],
            'Could not cancel the subscription.',
        );
    }

    /**
     * Checkout's success signature for a subscription:
     * HMAC-SHA256(payment_id|subscription_id, key_secret), compared in constant time.
     */
    public function verifyCheckoutSignature(string $paymentId, string $subscriptionId, string $signature): bool
    {
        $secret = $this->keySecret();

        if ($secret === null || $paymentId === '' || $subscriptionId === '' || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $paymentId . '|' . $subscriptionId, $secret), $signature);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, array $body, string $failure): array
    {
        if (! $this->isConfigured()) {
            throw new MembershipException('Payments are not configured.', 503);
        }

        try {
            $request = Http::withBasicAuth((string) $this->keyId(), (string) $this->keySecret())
                ->acceptJson()
                ->timeout(20);

            /** @var Response $response */
            $response = $method === 'get' ? $request->get(self::BASE . $path) : $request->post(self::BASE . $path, $body);
        } catch (ConnectionException) {
            throw new MembershipException('Could not reach the payment provider.', 502);
        }

        if ($response->status() === 401) {
            Log::error('Razorpay member billing: authentication failed.');

            throw new MembershipException('Payment authentication failed.', 502);
        }

        if (! $response->successful()) {
            // Razorpay's description is safe to log (no secrets), and it is the only clue.
            Log::warning('Razorpay member billing ' . strtoupper($method) . ' ' . $path . ' failed: '
                . $response->status() . ' ' . mb_substr($response->body(), 0, 500));

            throw new MembershipException($failure, $response->status() === 404 ? 404 : 502);
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
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
