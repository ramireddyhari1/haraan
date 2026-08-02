<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InboundMessages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Inbound WhatsApp from MSG91 — the BSP twin of {@see MetaWebhookController}.
 *
 * Both end at the same place, `InboundMessages::handle()`, so STOP handling,
 * partner attribution and the 24-hour service window behave identically no matter
 * which driver is carrying WhatsApp. Only the envelope differs, and that's the
 * whole job of this class.
 *
 * Three things shape it:
 *
 *  1. **MSG91 ships no signature.** Meta signs every webhook with an app secret;
 *     MSG91's panel offers arbitrary request headers and nothing else. So the
 *     shared secret we put in that header IS the authentication, and it fails
 *     closed — an unauthenticated endpoint here would let anyone forge a STOP for
 *     a number they don't own, or forge an inbound message to open a free-text
 *     window and send through it.
 *  2. **Delivery reports arrive on the same URL as customer messages.** Treating
 *     one as inbound would open (and bill) a 24-hour conversation off the back of
 *     our own outgoing ticket. They're identified and dropped before anything
 *     touches the ledger.
 *  3. **MSG91 retries.** Every event is claimed by id first, so a redelivery can't
 *     re-run a STOP or double-count a conversation.
 */
class Msg91WebhookController extends Controller
{
    /** How long a processed event id is remembered, to swallow provider retries. */
    private const DEDUPE_TTL_SECONDS = 3600;

    /**
     * Reachability check, for whoever is holding the URL.
     *
     * Providers commonly GET a webhook URL to validate it before letting you save,
     * and a 405 there reads as "your endpoint is broken" when it means "you used
     * the wrong verb". Answering 200 removes that trip hazard, and costs nothing:
     * it takes no input, touches nothing, and deliberately reports NO state — not
     * whether a token is configured, not what the header is called. That detail
     * helps exactly one person more than it helps us, and it isn't the operator.
     */
    public function ping(): JsonResponse
    {
        return response()->json(['ok' => true, 'channel' => 'whatsapp', 'provider' => 'msg91']);
    }

    public function handle(Request $request, InboundMessages $inbound): Response
    {
        if (! $this->authorised($request)) {
            return response('', 403);
        }

        // Diagnostic escape hatch, OFF by default. MSG91's payload is operator-
        // defined in their panel, so when a delivery is accepted but produces
        // nothing there is no way to tell an unresolved {{variable}} from a field
        // we're reading under the wrong name — the raw body is the only evidence.
        // Logs customer message content, so it is deliberately opt-in and meant to
        // be switched off again once the mapping is confirmed.
        if (filter_var(config('services.whatsapp.msg91.log_payloads', false), FILTER_VALIDATE_BOOLEAN)) {
            Log::info('MSG91 webhook raw payload: ' . mb_substr($request->getContent(), 0, 4000));
        }

        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            return response('', 400);
        }

        foreach ($this->events($payload) as $event) {
            try {
                $this->handleEvent($event, $inbound);
            } catch (\Throwable $e) {
                // Never 500: MSG91 retries on one, and the retry re-runs whatever
                // just broke. Swallow, log, and acknowledge.
                Log::error('MSG91 webhook event handling failed: ' . $e->getMessage());
            }
        }

        return response('', 200);
    }

    /**
     * The shared secret, compared in constant time.
     *
     * Configure the same header/value pair in the MSG91 panel under the number's
     * Action menu → Webhook. With no token configured this refuses everything:
     * an open endpoint is worse than a broken one, because a broken one is noticed.
     */
    private function authorised(Request $request): bool
    {
        $expected = (string) config('services.whatsapp.msg91.webhook_token', '');

        if ($expected === '') {
            Log::warning('MSG91 webhook rejected: no MSG91_WEBHOOK_TOKEN is configured.');

            return false;
        }

        $header = (string) config('services.whatsapp.msg91.webhook_header', 'X-Haraan-Token');
        $given = (string) $request->header($header, '');

        if ($given === '' || ! hash_equals($expected, $given)) {
            Log::warning('MSG91 webhook rejected: bad or missing ' . $header . ' header.');

            return false;
        }

        return true;
    }

    /**
     * Flatten whatever arrived into a list of events.
     *
     * MSG91 posts a single flat object for one message, but batches and wraps it
     * under `data` / `messages` in other cases. Rather than pin to one shape and
     * silently ignore the others, take a list wherever one is found and fall back
     * to treating the body itself as a single event.
     *
     * @param  array<mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function events(array $payload): array
    {
        // A bare JSON array of events.
        if (array_is_list($payload)) {
            return array_values(array_filter($payload, is_array(...)));
        }

        foreach (['data', 'messages', 'events'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key]) && array_is_list($payload[$key])) {
                return array_values(array_filter($payload[$key], is_array(...)));
            }
        }

        return [$payload];
    }

    /** @param array<string, mixed> $event */
    private function handleEvent(array $event, InboundMessages $inbound): void
    {
        // MSG91's "Test Run" button posts a fully-formed sample event with
        // dryRun:true. It looks exactly like a real message apart from that flag, so
        // without this an operator testing their webhook config would open a real
        // conversation against a sample number and bill it.
        if (filter_var($event['dryRun'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        if (! $this->isInbound($event)) {
            // A delivery report (sent/delivered/read/failed). Nothing to reply to,
            // and nothing here may touch the ledger: message_log counters are
            // already committed at send time, and re-deriving them from reports
            // would double-count. Wiring reports into per-message cost/status is a
            // separate piece of work with its own columns.
            return;
        }

        $from = $this->number($event);
        $text = $this->text($event);

        if ($from === null || $text === '') {
            // A sticker, image, reaction or location — no keyword to match, and
            // InboundMessages has nothing to do with it.
            return;
        }

        if (! $this->claim($event)) {
            return;
        }

        // MSG91 reports the customer without a plus; the ledger keys on E.164 and
        // WhatsAppService normalises the same way, so they must agree.
        $inbound->handle('whatsapp', $from, $text, $this->eventId($event));
    }

    /**
     * Is this a message FROM a customer, rather than a report about one of ours?
     *
     * Deliberately strict. `direction` is the field MSG91 sets on delivery reports,
     * so anything that names a direction other than inbound is a report — and so is
     * anything whose event name talks about a report or a status. When in doubt the
     * answer must be "not inbound": mistaking our own outgoing ticket for a
     * customer message opens a billable window that nobody opened.
     *
     * @param  array<string, mixed>  $event
     */
    private function isInbound(array $event): bool
    {
        // Two fields carry the direction and MSG91's DEFAULT payload uses the second
        // one: their sample event has no `direction` at all but does have
        // `webhookType: "outbound"`. Reading only `direction` would have let every
        // delivery report through as a customer message — the precise failure this
        // method exists to prevent.
        foreach (['direction', 'webhookType', 'webhook_type'] as $key) {
            $value = strtolower(trim((string) ($event[$key] ?? '')));

            if ($value !== '' && ! in_array($value, ['inbound', 'in', 'incoming', 'received'], true)) {
                return false;
            }
        }

        $name = strtolower((string) ($event['eventName'] ?? $event['event_name'] ?? ''));

        if ($name !== '' && (str_contains($name, 'report') || str_contains($name, 'status') || str_contains($name, 'delivery'))) {
            return false;
        }

        return true;
    }

    /**
     * The customer's number in E.164, or null if there isn't a usable one.
     *
     * @param  array<string, mixed>  $event
     */
    private function number(array $event): ?string
    {
        $raw = (string) ($event['customerNumber'] ?? $event['customer_number'] ?? $event['from'] ?? '');
        $digits = (string) preg_replace('/\D/', '', $raw);

        return strlen($digits) >= 10 ? '+' . $digits : null;
    }

    /**
     * The message body.
     *
     * MSG91 puts it in `text` on an inbound message, but also ships a `content`
     * field holding stringified JSON ("{\"text\":\"Hi, I have a query\"}") — so both
     * are read, and a JSON blob that fails to parse is treated as plain text rather
     * than dropped.
     *
     * @param  array<string, mixed>  $event
     */
    private function text(array $event): string
    {
        $text = $event['text'] ?? null;

        // `text` is sometimes the object rather than the string.
        if (is_array($text)) {
            $text = $text['body'] ?? $text['text'] ?? null;
        }

        if (is_string($text) && trim($text) !== '') {
            return trim($text);
        }

        $content = $event['content'] ?? null;

        if (is_array($content)) {
            return trim((string) ($content['text'] ?? ''));
        }

        if (is_string($content) && trim($content) !== '') {
            $decoded = json_decode($content, true);

            return is_array($decoded)
                ? trim((string) ($decoded['text'] ?? ''))
                : trim($content);
        }

        return '';
    }

    /**
     * Claim this event, returning false if it has already been handled.
     *
     * Cache::add is atomic, so two concurrent redeliveries can't both win. An event
     * with no usable id is always processed — under-reacting to a STOP is worse
     * than handling one twice, and InboundMessages is idempotent for the keywords
     * that matter.
     *
     * @param  array<string, mixed>  $event
     */
    private function claim(array $event): bool
    {
        $id = $this->eventId($event);

        if ($id === null) {
            return true;
        }

        return Cache::add('msg91-webhook:' . sha1($id), true, self::DEDUPE_TTL_SECONDS);
    }

    /** @param array<string, mixed> $event */
    private function eventId(array $event): ?string
    {
        foreach (['uuid', 'requestId', 'request_id', 'messageId', 'message_id', 'replyMsgId'] as $key) {
            $value = $event[$key] ?? null;

            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }
}
