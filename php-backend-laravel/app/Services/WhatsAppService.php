<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MessageLog;
use App\Support\MessageContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp delivery via Meta's WhatsApp Cloud API (Graph). Replaces Twilio, which
 * replaced a self-hosted whatsapp-web.js bridge.
 *
 * Going direct to Meta means one business verification, one app secret, one
 * webhook signature scheme and one template registry shared with Instagram —
 * instead of Meta's approval process plus a reseller's on top of it.
 *
 * The public interface is unchanged from the Twilio implementation on purpose:
 * sendMessage() / sendMedia() / sendTemplate() / isConfigured(), all best-effort
 * returning bool, never throwing into a booking or an OTP flow. Every attempt —
 * including the ones that never leave the building — lands in the messaging
 * ledger via {@see MessageMeter}.
 *
 * Note there is no SMS sibling any more: Meta does WhatsApp, not SMS. Phone
 * delivery is WhatsApp or nothing, with email as the parallel path.
 */
class WhatsAppService
{
    public function __construct(private readonly MessageMeter $meter) {}

    public function sendMessage(string $phone, string $message, ?MessageContext $context = null): bool
    {
        return $this->dispatch($phone, $context, [
            'type' => 'text',
            // preview_url off: link previews on a ticket URL look like spam and
            // Meta fetches the page to build them.
            'text' => ['body' => $message, 'preview_url' => false],
        ]);
    }

    /**
     * Send an image (the ticket QR) with a caption. $mediaUrl must be publicly
     * reachable — Meta fetches it server-side. Falls back to text if empty.
     */
    public function sendMedia(string $phone, string $caption, string $mediaUrl, ?MessageContext $context = null): bool
    {
        if ($mediaUrl === '') {
            return $this->sendMessage($phone, $caption, $context);
        }

        return $this->dispatch($phone, $context, [
            'type' => 'image',
            'image' => ['link' => $mediaUrl, 'caption' => $caption],
        ]);
    }

    /**
     * Send a pre-approved template — the only legal way to reach someone outside
     * the 24-hour service window.
     *
     * Meta identifies a template by NAME + language, not by an opaque id, and
     * body variables are positional {{1}}, {{2}}… in order.
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
        $template = [
            'name' => $templateName,
            'language' => ['code' => $language],
        ];

        if ($variables !== []) {
            $template['components'] = [[
                'type' => 'body',
                'parameters' => array_values(array_map(
                    fn ($value): array => ['type' => 'text', 'text' => (string) $value],
                    $variables,
                )),
            ]];
        }

        return $this->dispatch($phone, $context, [
            'type' => 'template',
            'template' => $template,
        ]);
    }

    /** Whether the Cloud API is configured well enough to attempt a send. */
    public function isConfigured(): bool
    {
        $c = config('services.whatsapp');

        return filled($c['phone_number_id'] ?? null) && filled($c['access_token'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $message  the type-specific part of the payload
     */
    private function dispatch(string $phone, ?MessageContext $context, array $message): bool
    {
        $enabled = filter_var(config('services.whatsapp.enabled', false), FILTER_VALIDATE_BOOLEAN);

        if (! $enabled) {
            Log::info("WhatsApp (disabled — not sent) to {$phone}");
            $this->meter->record('whatsapp', $phone, MessageLog::STATUS_DISABLED, $context);

            return false;
        }

        if (! $this->isConfigured()) {
            Log::warning('WhatsApp not sent: Meta phone number id / access token not configured.');
            $this->meter->record('whatsapp', $phone, MessageLog::STATUS_UNCONFIGURED, $context);

            return false;
        }

        $to = $this->e164($phone);

        if (! preg_match('/^\+\d{8,15}$/', $to)) {
            Log::warning("WhatsApp not sent: unroutable number {$phone}");
            $this->meter->record('whatsapp', $phone, MessageLog::STATUS_UNROUTABLE, $context);

            return false;
        }

        // Meter against the normalised address: "9876543210" and "+919876543210"
        // are one recipient, and keying on the raw input would open (and charge
        // for) two conversations with the same person.
        $recipient = $to;

        $version = (string) config('services.whatsapp.graph_version', 'v21.0');
        $phoneNumberId = (string) config('services.whatsapp.phone_number_id');
        $url = "https://graph.facebook.com/{$version}/{$phoneNumberId}/messages";

        // Meta wants the number without the leading plus.
        $payload = array_merge([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => ltrim($to, '+'),
        ], $message);

        try {
            $response = Http::withToken((string) config('services.whatsapp.access_token'))
                ->acceptJson()
                ->connectTimeout(5)->timeout(20)
                ->post($url, $payload);

            if ($response->successful()) {
                $this->meter->record(
                    'whatsapp',
                    $recipient,
                    MessageLog::STATUS_SENT,
                    $context,
                    // wamid — what a later cost/status backfill joins on.
                    providerMessageId: $response->json('messages.0.id'),
                );

                return true;
            }

            // Meta returns a structured {error:{message,code,error_data}} — surface
            // it, since template and window rejections are the common failures.
            $error = $response->json('error.message') ?? $response->body();
            Log::warning('WhatsApp Cloud send failed (' . $response->status() . '): ' . $error);
            $this->meter->record('whatsapp', $recipient, MessageLog::STATUS_FAILED, $context, error: (string) $error);

            return false;
        } catch (\Throwable $e) {
            Log::warning('WhatsApp Cloud exception: ' . $e->getMessage());
            $this->meter->record('whatsapp', $recipient, MessageLog::STATUS_FAILED, $context, error: $e->getMessage());

            return false;
        }
    }

    /** Normalise a phone to E.164, defaulting bare 10-digit numbers to +91. */
    private function e164(string $phone): string
    {
        $phone = trim($phone);

        if (str_starts_with($phone, '+')) {
            return '+' . preg_replace('/\D/', '', $phone);
        }

        $digits = preg_replace('/\D/', '', $phone);
        $cc = preg_replace('/\D/', '', (string) config('services.whatsapp.default_country', '91'));

        // Trunk-prefixed local numbers (0XXXXXXXXXX) — drop the leading zero(s).
        $digits = ltrim((string) $digits, '0');

        return strlen($digits) === 10 ? '+' . $cc . $digits : '+' . $digits;
    }
}
