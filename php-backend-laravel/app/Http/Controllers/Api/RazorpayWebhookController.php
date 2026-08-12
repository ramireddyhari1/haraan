<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RazorpayBilling;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Razorpay's webhook. Grants paid features and prepaid credits, so the signature
 * check is the authentication and it fails CLOSED — no configured secret means
 * every request is rejected.
 *
 * The signature is HMAC-SHA256 over the RAW request body, which is why this reads
 * getContent() rather than the parsed input: re-encoding the JSON would change a
 * byte somewhere and break every comparison.
 *
 * Always answers 200 once the signature passes. Razorpay retries non-2xx, and a
 * handler that already applied the event doesn't want it delivered again — the
 * grant path is idempotent precisely so a retry is harmless, but there's no
 * reason to invite one.
 */
class RazorpayWebhookController extends Controller
{
    public function handle(Request $request, RazorpayBilling $billing): Response
    {
        $secret = (string) config('services.razorpay.webhook_secret', '');

        if ($secret === '') {
            Log::error('Razorpay webhook rejected: no webhook secret configured.');

            return response('', 403);
        }

        $raw = $request->getContent();
        $signature = (string) $request->header('X-Razorpay-Signature', '');

        if ($signature === '' || ! hash_equals(hash_hmac('sha256', $raw, $secret), $signature)) {
            Log::warning('Razorpay webhook rejected: bad signature.');

            return response('', 403);
        }

        $payload = json_decode($raw, true);

        if (! is_array($payload) || ! isset($payload['event'])) {
            return response('', 400);
        }

        try {
            $outcome = $billing->applyWebhook((string) $payload['event'], $payload);
            Log::info('Razorpay webhook ' . $payload['event'] . ' → ' . $outcome);
        } catch (\Throwable $e) {
            // Swallow after logging: a 500 here means Razorpay redelivers, and
            // whatever broke will break again on the retry.
            Log::error('Razorpay webhook handling failed: ' . $e->getMessage());
        }

        return response('', 200);
    }
}
