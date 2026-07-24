<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChannelConnection;
use App\Services\InboundMessages;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Meta's Instagram webhook. Two jobs on one URL.
 *
 * GET  — the subscription handshake. Meta calls once with a verify token of our
 *        choosing and expects hub.challenge echoed back verbatim.
 * POST — message events, signed with X-Hub-Signature-256: 'sha256=' + HMAC-SHA256
 *        of the RAW body using the app secret. Like every other webhook here it
 *        fails closed.
 *
 * The trap this guards hardest: **echoes**. Meta delivers our own outgoing
 * messages back to us with message.is_echo set. Handling one would generate a
 * reply, which arrives as another echo — an infinite loop with a partner's
 * Instagram account as the megaphone. They're dropped first, before anything
 * else looks at the payload.
 */
class MetaWebhookController extends Controller
{
    /** Subscription handshake. */
    public function verify(Request $request): Response
    {
        $expected = (string) config('services.instagram.verify_token', '');
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge', ''));

        if ($expected === '' || ! hash_equals($expected, $token)) {
            Log::warning('Meta webhook verification failed: bad verify token.');

            return response('', 403);
        }

        // Must be echoed exactly, as plain text.
        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function handle(Request $request, InboundMessages $inbound): Response
    {
        if (! $this->verifySignature($request)) {
            return response('', 403);
        }

        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            return response('', 400);
        }

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['messaging'] ?? [] as $event) {
                try {
                    $this->handleEvent($event, $inbound);
                } catch (\Throwable $e) {
                    // Never 500: Meta retries, and a retry re-runs whatever broke.
                    Log::error('Instagram event handling failed: ' . $e->getMessage());
                }
            }
        }

        return response('', 200);
    }

    /** @param array<string, mixed> $event */
    private function handleEvent(array $event, InboundMessages $inbound): void
    {
        $message = $event['message'] ?? null;

        if (! is_array($message)) {
            return;   // delivery receipt, read receipt, reaction — nothing to answer
        }

        // Our own outbound message, echoed back. Replying would echo again.
        if (($message['is_echo'] ?? false) === true) {
            return;
        }

        $text = trim((string) ($message['text'] ?? ''));

        if ($text === '') {
            return;   // an image or a sticker; there's no keyword to match
        }

        $senderId = (string) ($event['sender']['id'] ?? '');
        $accountId = (string) ($event['recipient']['id'] ?? '');

        if ($senderId === '' || $accountId === '') {
            return;
        }

        $connection = ChannelConnection::forAccount('instagram', $accountId);

        if ($connection === null) {
            // A DM to an account nobody has linked. Worth a log line — it usually
            // means a partner connected in Meta but not here.
            Log::info("Instagram DM for unlinked account {$accountId} ignored.");

            return;
        }

        $inbound->handleInstagram($connection, $senderId, $text, $message['mid'] ?? null);
    }

    private function verifySignature(Request $request): bool
    {
        if (! (bool) config('services.instagram.validate_signature', true)) {
            Log::warning('Meta webhook signature validation is DISABLED.');

            return true;
        }

        $secret = (string) config('services.instagram.app_secret', '');

        if ($secret === '') {
            Log::error('Meta webhook rejected: no app secret configured.');

            return false;
        }

        $header = (string) $request->header('X-Hub-Signature-256', '');

        if (! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $header);
    }
}
