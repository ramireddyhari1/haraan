<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RazorpayGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Razorpay Standard Checkout — standalone demo endpoints (used by the local-only /pay page).
 *
 * The real product flow lives in {@see BookingsController} (reserve→confirm). These two
 * endpoints exist for the demo/test page and as a generic create-order + verify pair. Both
 * delegate to {@see RazorpayGateway} so key/secret handling lives in one place.
 */
final class RazorpayController extends Controller
{
    public function __construct(private readonly RazorpayGateway $gateway) {}

    /**
     * POST /api/create-order
     * Body: { amount (paise, >= 100), currency?, receipt? }
     * Returns: { key, order_id, amount, currency }
     */
    public function createOrder(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'amount'   => ['required', 'integer', 'min:' . RazorpayGateway::MIN_AMOUNT_PAISE],
                'currency' => ['sometimes', 'string', 'size:3'],
                'receipt'  => ['sometimes', 'string', 'max:40'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'error'   => 'Invalid order request.',
                'details' => $e->errors(),
            ], 422);
        }

        try {
            $order = $this->gateway->createOrder(
                (int) $data['amount'],
                $data['receipt'] ?? ('rcpt_' . Str::random(16)),
                $data['currency'] ?? 'INR',
            );
        } catch (RuntimeException $e) {
            $status = $e->getCode() >= 400 ? (int) $e->getCode() : 500;
            Log::warning('Razorpay create-order failed', ['status' => $status, 'msg' => $e->getMessage()]);

            return response()->json(['error' => $e->getMessage()], $status);
        }

        return response()->json([
            'key'      => $this->gateway->publicKey(),
            'order_id' => $order['id'],
            'amount'   => $order['amount'],
            'currency' => $order['currency'],
        ]);
    }

    /**
     * POST /api/verify-payment
     * Body: { razorpay_order_id, razorpay_payment_id, razorpay_signature }
     * Returns { verified: true } only on an exact, constant-time signature match.
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

        $ok = $this->gateway->verifySignature(
            $data['razorpay_order_id'],
            $data['razorpay_payment_id'],
            $data['razorpay_signature'],
        );

        if (! $ok) {
            Log::warning('Razorpay signature mismatch', ['order' => $data['razorpay_order_id']]);

            return response()->json([
                'verified' => false,
                'error'    => 'Payment signature verification failed.',
            ], 400);
        }

        return response()->json([
            'verified'   => true,
            'order_id'   => $data['razorpay_order_id'],
            'payment_id' => $data['razorpay_payment_id'],
        ]);
    }
}
