<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Razorpay Standard Checkout — server side.
 *
 * Two endpoints: one creates an order (so the amount is fixed server-side and can't
 * be tampered with in the browser), the other verifies the payment signature after
 * the checkout modal succeeds. The KEY_SECRET lives only here (config/services) and
 * never reaches the client.
 *
 * Orders are created via Razorpay's REST API with the framework HTTP client (no SDK
 * dependency) — key/secret go as HTTP basic auth, exactly as the SDK does internally.
 */
final class RazorpayController extends Controller
{
    /** Razorpay's floor for a live charge. */
    private const MIN_AMOUNT_PAISE = 100;

    private const ORDERS_ENDPOINT = 'https://api.razorpay.com/v1/orders';

    /**
     * POST /api/create-order
     * Body: { amount (paise, >= 100), currency?, receipt? }
     * Returns: { key, order_id, amount, currency }
     */
    public function createOrder(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'amount'   => ['required', 'integer', 'min:' . self::MIN_AMOUNT_PAISE],
                'currency' => ['sometimes', 'string', 'size:3'],
                'receipt'  => ['sometimes', 'string', 'max:40'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'error'   => 'Invalid order request.',
                'details' => $e->errors(),
            ], 422);
        }

        [$keyId, $keySecret] = $this->credentials();

        if ($keyId === null || $keySecret === null) {
            Log::error('Razorpay create-order: missing RAZORPAY_KEY_ID/SECRET');

            return response()->json(['error' => 'Payments are not configured.'], 500);
        }

        try {
            $response = Http::withBasicAuth($keyId, $keySecret)
                ->acceptJson()
                ->timeout(20)
                ->post(self::ORDERS_ENDPOINT, [
                    'amount'          => (int) $data['amount'],
                    'currency'        => strtoupper($data['currency'] ?? 'INR'),
                    'receipt'         => $data['receipt'] ?? ('rcpt_' . Str::random(16)),
                    'payment_capture' => 1,
                ]);
        } catch (ConnectionException $e) {
            Log::error('Razorpay create-order connection failed', ['msg' => $e->getMessage()]);

            return response()->json(['error' => 'Could not reach the payment provider.'], 502);
        }

        // Bad/expired keys come back as 401 from Razorpay — surface that verbatim so the
        // caller can tell auth failure from a generic error. Anything else non-2xx = 500.
        if ($response->status() === 401) {
            Log::warning('Razorpay create-order auth failed', ['body' => $response->json()]);

            return response()->json(['error' => 'Payment authentication failed.'], 401);
        }

        if (! $response->successful()) {
            Log::warning('Razorpay create-order rejected', [
                'status' => $response->status(),
                'body'   => $response->json(),
            ]);

            return response()->json(['error' => 'Could not create the payment order.'], 500);
        }

        $order = $response->json();

        return response()->json([
            'key'      => $keyId,
            'order_id' => $order['id'],
            'amount'   => $order['amount'],
            'currency' => $order['currency'],
        ]);
    }

    /**
     * POST /api/verify-payment
     * Body: { razorpay_order_id, razorpay_payment_id, razorpay_signature }
     * Verifies HMAC-SHA256(order_id|payment_id, KEY_SECRET) against the returned
     * signature. Returns { verified: true } only on an exact, constant-time match.
     */
    public function verifyPayment(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'razorpay_order_id'   => ['required', 'string'],
                'razorpay_payment_id' => ['required', 'string'],
                'razorpay_signature'  => ['required', 'string'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'verified' => false,
                'error'    => 'Missing payment fields.',
            ], 400);
        }

        [, $keySecret] = $this->credentials();

        if ($keySecret === null) {
            Log::error('Razorpay verify-payment: missing RAZORPAY_KEY_SECRET');

            return response()->json(['verified' => false, 'error' => 'Payments are not configured.'], 500);
        }

        $expected = hash_hmac(
            'sha256',
            $data['razorpay_order_id'] . '|' . $data['razorpay_payment_id'],
            $keySecret,
        );

        if (! hash_equals($expected, $data['razorpay_signature'])) {
            Log::warning('Razorpay signature mismatch', ['order' => $data['razorpay_order_id']]);

            return response()->json([
                'verified' => false,
                'error'    => 'Payment signature verification failed.',
            ], 400);
        }

        // Signature is authentic — the payment is genuine. A real fulfilment step
        // (mark a booking paid, issue the ticket) would hook in here.
        return response()->json([
            'verified'   => true,
            'order_id'   => $data['razorpay_order_id'],
            'payment_id' => $data['razorpay_payment_id'],
        ]);
    }

    /**
     * @return array{0: string|null, 1: string|null} [keyId, keySecret]
     */
    private function credentials(): array
    {
        $key    = config('services.razorpay.key');
        $secret = config('services.razorpay.secret');

        return [
            is_string($key) && $key !== '' ? $key : null,
            is_string($secret) && $secret !== '' ? $secret : null,
        ];
    }
}
