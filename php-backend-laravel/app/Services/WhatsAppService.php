<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MessageLog;
use App\Support\MessageContext;
use App\Support\PhoneNumber;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp delivery, over either of the two ways to reach the same WhatsApp
 * Business Account:
 *
 *  - `meta`  — the WhatsApp Cloud API (Graph), direct. No reseller in the path.
 *  - `msg91` — MSG91 as the Business Solution Provider in front of the WABA.
 *
 * The distinction is transport only. Both carry the same number, the same
 * approved templates and the same 24-hour window rule, so a template approved for
 * one is approved for the other and switching driver is an env change. What
 * differs is the wire format, which is why the public methods build a
 * provider-neutral *intent* and each driver renders it — a caller must never have
 * to know who is carrying the message.
 *
 * The public interface has been stable across three transports (whatsapp-web.js →
 * Twilio → Cloud API → BSP) on purpose: sendMessage() / sendMedia() /
 * sendTemplate() / isConfigured(), all best-effort returning bool, never throwing
 * into a booking or an OTP flow. Every attempt — including the ones that never
 * leave the building — lands in the messaging ledger via {@see MessageMeter},
 * tagged with the driver that carried it, so "MSG91 has been rejecting our
 * template for two days" is visible instead of invisible.
 *
 * There is no SMS sibling to this class any more. A ticket goes out over email,
 * push and WhatsApp independently rather than as one ladder, and email — not SMS —
 * is now the rung that needs no approval and no app.
 */
class WhatsAppService
{
    public function __construct(private readonly MessageMeter $meter) {}

    public function sendMessage(string $phone, string $message, ?MessageContext $context = null): bool
    {
        return $this->dispatch($phone, $context, ['kind' => 'text', 'body' => $message]);
    }

    /**
     * Send an image (the ticket QR) with a caption. $mediaUrl must be publicly
     * reachable — the provider fetches it server-side. Falls back to text if empty.
     */
    public function sendMedia(string $phone, string $caption, string $mediaUrl, ?MessageContext $context = null): bool
    {
        if ($mediaUrl === '') {
            return $this->sendMessage($phone, $caption, $context);
        }

        return $this->dispatch($phone, $context, [
            'kind' => 'image',
            'url' => $mediaUrl,
            'caption' => $caption,
        ]);
    }

    /**
     * Send a pre-approved template — the only legal way to reach someone outside
     * the 24-hour customer service window.
     *
     * A template is identified by NAME + language, not by an opaque id, on both
     * drivers; body variables are positional ({{1}}, {{2}}… to Meta, body_1,
     * body_2… to MSG91) and this takes them in that order.
     *
     * @param  array<int|string, string>  $variables
     */
    public function sendTemplate(
        string $phone,
        string $templateName,
        array $variables = [],
        ?MessageContext $context = null,
        string $language = 'en',
    ): bool {
        return $this->dispatch($phone, $context, [
            'kind' => 'template',
            'name' => $templateName,
            'language' => $language,
            'variables' => array_values(array_map(self::templateVar(...), $variables)),
        ]);
    }

    /**
     * Make one value safe to put in a template parameter.
     *
     * WhatsApp rejects the whole message (error 132000 / "parameter format
     * mismatch") if a parameter contains a newline, a tab or four consecutive
     * spaces — no partial delivery, no useful hint. The values we substitute are
     * event titles and venue names typed by partners, which is exactly where a
     * pasted line break comes from, so they're flattened here rather than in each
     * of the four callers that would otherwise have to remember.
     */
    private static function templateVar(mixed $value): string
    {
        $flat = preg_replace('/[\r\n\t]+/', ' ', (string) $value) ?? '';

        return trim((string) preg_replace('/ {2,}/', ' ', $flat));
    }

    /**
     * Send a one-time passcode over an AUTHENTICATION-category template.
     *
     * Not a special case of sendTemplate(), because WhatsApp's authentication
     * templates aren't a special case of templates. They're created from a fixed
     * skeleton with a copy-code (or one-tap autofill) button, and the code has to
     * be supplied TWICE — once for the body and once for the button, which is what
     * the "Copy code" tap actually copies. Send only the body parameter and the
     * whole message is rejected for a component count mismatch, which reads like a
     * broken login rather than a malformed payload.
     *
     * Set `services.whatsapp.auth_template_has_button` to false if the template was
     * approved with no button at all — WhatsApp permits that, and then the extra
     * component is itself the mismatch.
     */
    public function sendOtpTemplate(
        string $phone,
        string $templateName,
        string $code,
        ?MessageContext $context = null,
        string $language = 'en',
    ): bool {
        return $this->dispatch($phone, $context, [
            'kind' => 'template',
            'name' => $templateName,
            'language' => $language,
            'variables' => [$code],
            'button_code' => filter_var(
                config('services.whatsapp.auth_template_has_button', true),
                FILTER_VALIDATE_BOOLEAN,
            ) ? $code : null,
        ]);
    }

    /** Which transport is carrying WhatsApp right now: 'meta' or 'msg91'. */
    public function driver(): string
    {
        return (string) config('services.whatsapp.driver', 'meta');
    }

    /** Whether the active driver has everything it needs to attempt a send. */
    public function isConfigured(): bool
    {
        $c = (array) config('services.whatsapp');

        return match ($this->driver()) {
            // MSG91 needs the account key AND the number registered on their panel.
            // Without the integrated number they have no idea which WABA to send as.
            'msg91' => filled($c['msg91']['auth_key'] ?? null) && filled($c['msg91']['integrated_number'] ?? null),
            'meta' => filled($c['phone_number_id'] ?? null) && filled($c['access_token'] ?? null),
            default => false,
        };
    }

    /**
     * @param  array{kind: string, ...}  $intent  what to send, provider-neutral
     */
    private function dispatch(string $phone, ?MessageContext $context, array $intent): bool
    {
        $driver = $this->driver();
        $enabled = filter_var(config('services.whatsapp.enabled', false), FILTER_VALIDATE_BOOLEAN);

        if (! $enabled) {
            Log::info("WhatsApp (disabled — not sent) to {$phone}");
            $this->meter->record('whatsapp', $phone, MessageLog::STATUS_DISABLED, $context, provider: $driver);

            return false;
        }

        if (! $this->isConfigured()) {
            Log::warning('WhatsApp not sent: the ' . $driver . ' driver is not fully configured.');
            $this->meter->record('whatsapp', $phone, MessageLog::STATUS_UNCONFIGURED, $context, provider: $driver);

            return false;
        }

        $to = PhoneNumber::e164($phone, (string) config('services.whatsapp.default_country', '91'));

        if (! PhoneNumber::isRoutable($to)) {
            Log::warning("WhatsApp not sent: unroutable number {$phone}");
            $this->meter->record('whatsapp', $phone, MessageLog::STATUS_UNROUTABLE, $context, provider: $driver);

            return false;
        }

        // Meter against the normalised address: "9876543210" and "+919876543210"
        // are one recipient, and keying on the raw input would open (and charge
        // for) two conversations with the same person.
        $recipient = $to;

        try {
            [$ok, $providerMessageId, $error] = match ($driver) {
                'meta' => $this->sendViaMeta($to, $intent),
                'msg91' => $this->sendViaMsg91($to, $intent),
                default => [false, null, 'Unknown WhatsApp driver "' . $driver . '"'],
            };
        } catch (\Throwable $e) {
            Log::warning('WhatsApp exception (' . $driver . '): ' . $e->getMessage());
            $this->meter->record('whatsapp', $recipient, MessageLog::STATUS_FAILED, $context, provider: $driver, error: $e->getMessage());

            return false;
        }

        $this->meter->record(
            'whatsapp',
            $recipient,
            $ok ? MessageLog::STATUS_SENT : MessageLog::STATUS_FAILED,
            $context,
            // wamid (Meta) or request id (MSG91) — what a later cost/status
            // backfill joins on.
            providerMessageId: $providerMessageId,
            provider: $driver,
            error: $ok ? null : $error,
        );

        if (! $ok) {
            Log::warning('WhatsApp send failed via ' . $driver . ': ' . (string) $error);
        }

        return $ok;
    }

    // -------------------------------------------------------------------------
    // Meta — WhatsApp Cloud API
    // -------------------------------------------------------------------------

    /**
     * @param  array{kind: string, ...}  $intent
     * @return array{0: bool, 1: string|null, 2: string|null} [ok, providerMessageId, error]
     */
    private function sendViaMeta(string $to, array $intent): array
    {
        $message = match ($intent['kind']) {
            // preview_url off: link previews on a ticket URL look like spam and
            // Meta fetches the page to build them.
            'text' => ['type' => 'text', 'text' => ['body' => $intent['body'], 'preview_url' => false]],
            'image' => ['type' => 'image', 'image' => ['link' => $intent['url'], 'caption' => $intent['caption']]],
            'template' => ['type' => 'template', 'template' => $this->metaTemplate($intent)],
            default => [],
        };

        if ($message === []) {
            return [false, null, 'Unsupported message kind "' . $intent['kind'] . '"'];
        }

        $version = (string) config('services.whatsapp.graph_version', 'v21.0');
        $phoneNumberId = (string) config('services.whatsapp.phone_number_id');

        $response = Http::withToken((string) config('services.whatsapp.access_token'))
            ->acceptJson()
            ->connectTimeout(5)->timeout(20)
            // Meta wants the number without the leading plus.
            ->post("https://graph.facebook.com/{$version}/{$phoneNumberId}/messages", array_merge([
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => ltrim($to, '+'),
            ], $message));

        if ($response->successful()) {
            return [true, $response->json('messages.0.id'), null];
        }

        // Meta returns a structured {error:{message,code,error_data}} — surface it,
        // since template and window rejections are the common failures.
        return [false, null, (string) ($response->json('error.message') ?? $response->body())];
    }

    /**
     * @param  array{kind: string, ...}  $intent
     * @return array<string, mixed>
     */
    private function metaTemplate(array $intent): array
    {
        $template = [
            'name' => $intent['name'],
            'language' => ['code' => $intent['language']],
        ];

        $components = [];

        if ($intent['variables'] !== []) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(
                    fn (string $value): array => ['type' => 'text', 'text' => $value],
                    $intent['variables'],
                ),
            ];
        }

        // The copy-code button on an authentication template. index is a string
        // ('0'), not an int — Meta is strict about that.
        if (($intent['button_code'] ?? null) !== null) {
            $components[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => [['type' => 'text', 'text' => $intent['button_code']]],
            ];
        }

        if ($components !== []) {
            $template['components'] = $components;
        }

        return $template;
    }

    // -------------------------------------------------------------------------
    // MSG91 — WhatsApp as a BSP
    // -------------------------------------------------------------------------

    /**
     * MSG91's outbound WhatsApp API.
     *
     * Two endpoints, not one: templates go to `/bulk/` (which takes a recipient
     * list and per-recipient variables, so one approved template can address many
     * people), while text and media go to the single-message endpoint. Sending a
     * template down the single endpoint is silently accepted and never delivered,
     * which is the failure mode this split exists to avoid.
     *
     * @param  array{kind: string, ...}  $intent
     * @return array{0: bool, 1: string|null, 2: string|null} [ok, providerMessageId, error]
     */
    private function sendViaMsg91(string $to, array $intent): array
    {
        $config = (array) config('services.whatsapp.msg91');
        $base = rtrim((string) ($config['base_url'] ?? 'https://control.msg91.com/api/v5'), '/');
        // MSG91 wants bare digits with the country code, no plus, on both the
        // sender and the recipient.
        $number = preg_replace('/[^0-9]/', '', (string) ($config['integrated_number'] ?? ''));
        $recipient = ltrim($to, '+');

        [$path, $payload] = match ($intent['kind']) {
            'template' => [
                '/whatsapp/whatsapp-outbound-message/bulk/',
                [
                    'integrated_number' => $number,
                    'content_type' => 'template',
                    'payload' => [
                        'messaging_product' => 'whatsapp',
                        'type' => 'template',
                        'template' => array_filter([
                            'name' => $intent['name'],
                            // "deterministic" pins the language instead of letting
                            // WhatsApp pick a translation we never wrote.
                            'language' => ['code' => $intent['language'], 'policy' => 'deterministic'],
                            'namespace' => $config['namespace'] ?? null,
                            'to_and_components' => [[
                                'to' => [$recipient],
                                'components' => $this->msg91Components(
                                    $intent['variables'],
                                    $intent['button_code'] ?? null,
                                ),
                            ]],
                        ], fn ($v) => $v !== null),
                    ],
                ],
            ],
            // Flat, and nothing like the template shape: `recipient_number` at the
            // top level and `text` as a plain string, NOT a Meta-style
            // payload.to/text.body envelope. Confirmed against the live API —
            // the nested form is rejected with "recipient_number not found in
            // request", which reads like a missing field rather than the wrong
            // shape it actually is.
            'text' => [
                '/whatsapp/whatsapp-outbound-message/',
                [
                    'integrated_number' => $number,
                    'content_type' => 'text',
                    'recipient_number' => $recipient,
                    'text' => $intent['body'],
                ],
            ],
            // Same flat convention. Unlike the two above this one is NOT confirmed
            // against the live API — their validator rejects on recipient_number
            // before it looks at anything else, so the media fields can't be probed
            // without actually sending. If it's wrong the send fails and
            // BookingNotifier falls back to text, which is why that fallback exists.
            'image' => [
                '/whatsapp/whatsapp-outbound-message/',
                [
                    'integrated_number' => $number,
                    'content_type' => 'media',
                    'recipient_number' => $recipient,
                    'media' => [
                        'type' => 'image',
                        'url' => $intent['url'],
                        'caption' => $intent['caption'],
                    ],
                ],
            ],
            default => ['', []],
        };

        if ($path === '') {
            return [false, null, 'Unsupported message kind "' . $intent['kind'] . '"'];
        }

        $response = Http::withHeaders([
            'authkey' => (string) ($config['auth_key'] ?? ''),
            'accept' => 'application/json',
        ])
            ->connectTimeout(5)->timeout(20)
            ->post($base . $path, $payload);

        return $this->readMsg91Response($response);
    }

    /**
     * Positional variables → MSG91's named component map.
     *
     * MSG91 does not take an ordered array like Meta does; it wants body_1,
     * body_2… keyed by position. The order here is still the order the template
     * was approved with, so the two drivers stay interchangeable for callers.
     *
     * @param  array<int, string>  $variables
     * @return array<string, array<string, string>>
     */
    private function msg91Components(array $variables, ?string $buttonCode = null): array
    {
        $components = [];
        $i = 1;

        foreach ($variables as $value) {
            $components['body_' . $i++] = ['type' => 'text', 'value' => $value];
        }

        // The copy-code button on an authentication template — same code as the
        // body, carried in the slot MSG91 maps to the button component.
        if ($buttonCode !== null) {
            $components['button_1'] = ['subtype' => 'url', 'type' => 'text', 'value' => $buttonCode];
        }

        return $components;
    }

    /**
     * Read a verdict out of an MSG91 response.
     *
     * Deliberately paranoid: MSG91 answers HTTP 200 with a rejection in the body
     * ({"type":"error"} on some endpoints, {"status":"error"} on others, and a
     * populated `errors`/`message` on others again), so the status code alone is
     * never the verdict. Anything that isn't recognisably a success is treated as
     * a failure with the raw body kept in the ledger — a send wrongly logged as
     * failed is a reporting bug; a rejection logged as sent is a customer who
     * never got their ticket and nothing to show for it.
     *
     * @return array{0: bool, 1: string|null, 2: string|null}
     */
    private function readMsg91Response(Response $response): array
    {
        $body = $response->json();
        $body = is_array($body) ? $body : [];

        // Both spellings appear across their v5 endpoints.
        $verdict = strtolower((string) ($body['type'] ?? $body['status'] ?? ''));
        $failed = $verdict === 'error' || $verdict === 'fail' || $verdict === 'failure';

        if ($response->successful() && ! $failed) {
            // The identifier really is called different things per endpoint, and
            // both of these are confirmed live: the bulk/template endpoint returns
            // `request_id` at the top level, the single-message endpoint returns
            // `data.message_uuid`. Take whichever is present rather than pinning to
            // one and storing null forever.
            $id = $body['request_id']
                ?? $body['data']['message_uuid']
                ?? $body['data']['request_id']
                ?? $body['messageId']
                ?? $body['message_id']
                ?? null;

            return [true, $id !== null ? (string) $id : null, null];
        }

        $error = $body['message'] ?? $body['errors'] ?? $response->body();

        return [false, null, is_string($error) ? $error : json_encode($error)];
    }
}
