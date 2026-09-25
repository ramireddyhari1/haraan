<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\Operations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * Thrown by every Razorpay entry point that STARTS a payment while /control → Operations →
 * "Stop taking payments" is on. A RuntimeException with code 503, so the existing callers —
 * which catch RuntimeException, release any hold, and answer with the exception's code and
 * message — already do the right thing. Verifying and confirming payments already started is
 * never blocked: that money has left the customer.
 */
final class PaymentsPaused extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(Operations::paymentsMessage(), 503);
    }

    public static function guard(): void
    {
        if (Operations::paymentsDisabled()) {
            throw new self;
        }
    }

    /** Uncaught (a caller that doesn't catch RuntimeException): still a clear 503, not a 500. */
    public function render(Request $request): JsonResponse|Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['error' => 'payments_paused', 'message' => $this->getMessage()], 503);
        }

        return response($this->getMessage(), 503);
    }
}
