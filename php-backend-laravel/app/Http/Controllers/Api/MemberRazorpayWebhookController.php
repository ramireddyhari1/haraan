<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Membership\MemberSubscriptions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Razorpay webhook for MEMBER subscriptions (Free / Pro / Hero).
 *
 * Deliberately a separate endpoint and secret from /api/webhooks/razorpay (partner billing,
 * tickets, credits): member plans can't be granted through the partner endpoint, and a
 * compromise of one secret can't forge the other. Configure it in the Razorpay dashboard as
 * a second webhook with the `subscription.*` events.
 *
 * The HMAC over the RAW body is the authentication, and it fails closed. After a valid
 * signature it answers 200 even when handling throws — Razorpay redelivers non-2xx, and the
 * reconcile command re-reads Razorpay for anything a failed delivery left behind.
 */
final class MemberRazorpayWebhookController extends Controller
{
    public function handle(Request $request, MemberSubscriptions $subscriptions): Response
    {
        $secret = (string) config('services.razorpay.member_webhook_secret', '');

        if ($secret === '') {
            Log::error('Member Razorpay webhook rejected: no member webhook secret configured.');

            return response('', 403);
        }

        $raw = $request->getContent();
        $signature = (string) $request->header('X-Razorpay-Signature', '');

        if ($signature === '' || ! hash_equals(hash_hmac('sha256', $raw, $secret), $signature)) {
            Log::warning('Member Razorpay webhook rejected: bad signature.');

            return response('', 403);
        }

        $payload = json_decode($raw, true);

        if (! is_array($payload) || ! is_string($payload['event'] ?? null)) {
            return response('', 400);
        }

        $eventId = trim((string) $request->header('X-Razorpay-Event-Id', ''));

        try {
            $outcome = $subscriptions->applyWebhook($payload['event'], $payload, $eventId === '' ? null : mb_substr($eventId, 0, 191));
            Log::info('Member Razorpay webhook ' . $payload['event'] . ' → ' . $outcome);
        } catch (\Throwable $e) {
            Log::error('Member Razorpay webhook handling failed: ' . $e->getMessage());
        }

        return response('', 200);
    }
}
