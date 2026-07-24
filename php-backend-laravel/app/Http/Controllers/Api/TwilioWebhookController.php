<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InboundMessages;
use App\Support\TwilioSignature;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Twilio's inbound WhatsApp webhook.
 *
 * Public and unauthenticated by necessity — Twilio can't hold a session — so the
 * X-Twilio-Signature check IS the authentication. Everything downstream (opt-outs,
 * auto-replies) can be triggered by whoever gets past it, so it fails CLOSED: no
 * auth token configured means every request is rejected, rather than silently
 * accepting anything.
 *
 * Always answers 204. Twilio retries on a non-2xx, and a retry storm on an
 * endpoint that has already recorded the message is worse than a dropped reply;
 * anything that goes wrong is logged instead.
 */
class TwilioWebhookController extends Controller
{
    public function whatsapp(Request $request, InboundMessages $inbound): Response
    {
        if (! $this->verify($request)) {
            // 403, not 204: a rejected request should be visibly rejected in
            // Twilio's console rather than looking like it worked.
            return response('', 403);
        }

        $from = $this->strip((string) $request->input('From'));
        $body = (string) $request->input('Body', '');
        $sid = $request->input('MessageSid');

        if ($from === '') {
            Log::warning('Twilio webhook: no From on inbound payload.');

            return response('', 204);
        }

        try {
            $inbound->handle('whatsapp', $from, $body, is_string($sid) ? $sid : null);
        } catch (\Throwable $e) {
            // Swallow: the message is already recorded, and a 500 here just makes
            // Twilio deliver the same message again.
            Log::error('Twilio inbound handling failed: ' . $e->getMessage());
        }

        return response('', 204);
    }

    private function verify(Request $request): bool
    {
        if (! (bool) config('messaging.webhook.validate_signature', true)) {
            // Escape hatch for local testing only — never true in production.
            Log::warning('Twilio webhook signature validation is DISABLED.');

            return true;
        }

        $token = (string) config('services.whatsapp.auth_token', '');

        if ($token === '') {
            Log::error('Twilio webhook rejected: no auth token configured to validate against.');

            return false;
        }

        return TwilioSignature::isValid(
            (string) $request->header('X-Twilio-Signature', ''),
            $this->publicUrl($request),
            $request->post(),
            $token,
        );
    }

    /**
     * The URL Twilio signed.
     *
     * It must match byte for byte, and the request as PHP sees it is not
     * trustworthy here: nginx terminates TLS, so the internal scheme can be http
     * while Twilio signed https. Build it from APP_URL instead, with an explicit
     * override for the case where the two ever diverge.
     */
    private function publicUrl(Request $request): string
    {
        $override = (string) config('messaging.webhook.url', '');

        if ($override !== '') {
            return $override;
        }

        return rtrim((string) config('app.url'), '/') . '/' . ltrim($request->path(), '/');
    }

    /** "whatsapp:+919876543210" → "+919876543210". */
    private function strip(string $address): string
    {
        return trim(str_starts_with($address, 'whatsapp:') ? substr($address, 9) : $address);
    }
}
