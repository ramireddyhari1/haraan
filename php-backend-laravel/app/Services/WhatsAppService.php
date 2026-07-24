<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MessageLog;
use App\Support\MessageContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp delivery via Twilio's REST API. Replaces the old self-hosted whatsapp-web.js bridge
 * (no QR to scan, no session to keep alive). Keeps the original interface — sendMessage() and
 * sendMedia() — so callers (BookingNotifier, auth controllers) are unchanged.
 *
 * Every send is best-effort and returns bool; a failure is logged, never thrown, so it can't
 * break a booking or an OTP flow. Requires a WhatsApp-enabled Twilio sender ('from') and, for
 * business-initiated messages outside a 24h session, an approved template on the Twilio side.
 *
 * Every attempt — including the ones that never reach Twilio — is written to the messaging
 * ledger via {@see MessageMeter}. Pass a {@see MessageContext} to attribute the send to a
 * partner; without one it's recorded as platform traffic and bills nobody.
 */
class WhatsAppService
{
    private const API = 'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json';

    public function __construct(private readonly MessageMeter $meter) {}

    public function sendMessage(string $phone, string $message, ?MessageContext $context = null): bool
    {
        return $this->dispatch($phone, $message, null, $context);
    }

    /**
     * Send an image (the ticket QR) with a caption. $mediaUrl must be a publicly reachable URL
     * that Twilio can fetch (e.g. the /t/{code}/qr.png route). Falls back to a text-only send if
     * no media URL is given.
     */
    public function sendMedia(string $phone, string $caption, string $mediaUrl, ?MessageContext $context = null): bool
    {
        return $this->dispatch($phone, $caption, $mediaUrl !== '' ? $mediaUrl : null, $context);
    }

    /** Whether Twilio WhatsApp is configured well enough to attempt a send. */
    public function isConfigured(): bool
    {
        $c = config('services.whatsapp');

        return (bool) ($c['account_sid'] ?? null)
            && (bool) ($c['from'] ?? null)
            && (((bool) ($c['api_key_sid'] ?? null) && (bool) ($c['api_key_secret'] ?? null))
                || (bool) ($c['auth_token'] ?? null));
    }

    private function dispatch(string $phone, string $body, ?string $mediaUrl, ?MessageContext $context = null): bool
    {
        $enabled = filter_var(config('services.whatsapp.enabled', false), FILTER_VALIDATE_BOOLEAN);

        if (! $enabled) {
            Log::info("WhatsApp (disabled — not sent) to {$phone}: {$body}");
            $this->meter->record('whatsapp', $phone, MessageLog::STATUS_DISABLED, $context);

            return false;
        }

        if (! $this->isConfigured()) {
            Log::warning('WhatsApp not sent: Twilio credentials / sender not configured.');
            $this->meter->record('whatsapp', $phone, MessageLog::STATUS_UNCONFIGURED, $context);

            return false;
        }

        $to = $this->toWhatsApp($phone);
        if ($to === null) {
            Log::warning("WhatsApp not sent: unroutable number {$phone}");
            $this->meter->record('whatsapp', $phone, MessageLog::STATUS_UNROUTABLE, $context);

            return false;
        }

        $accountSid = (string) config('services.whatsapp.account_sid');
        $from = 'whatsapp:' . $this->e164((string) config('services.whatsapp.from'));

        $payload = ['From' => $from, 'To' => $to, 'Body' => $body];
        if ($mediaUrl !== null) {
            $payload['MediaUrl'] = $mediaUrl;
        }

        // Meter against the normalised address, not the raw input: "9876543210" and
        // "+919876543210" are one recipient, and keying on the raw string would open
        // (and charge for) two conversations with the same person.
        $recipient = substr($to, strlen('whatsapp:'));

        try {
            $response = Http::withBasicAuth(...$this->authPair($accountSid))
                ->asForm()
                ->connectTimeout(5)->timeout(20)
                ->post(sprintf(self::API, $accountSid), $payload);

            if ($response->successful()) {
                // The Twilio SID is what a later cost backfill joins the ledger on.
                $this->meter->record(
                    'whatsapp',
                    $recipient,
                    MessageLog::STATUS_SENT,
                    $context,
                    providerMessageId: $response->json('sid'),
                );

                return true;
            }

            // Twilio returns a helpful {code,message} — surface it so sender/template issues are clear.
            Log::warning('Twilio WhatsApp send failed (' . $response->status() . '): ' . $response->body());
            $this->meter->record(
                'whatsapp',
                $recipient,
                MessageLog::STATUS_FAILED,
                $context,
                error: 'HTTP ' . $response->status() . ': ' . $response->body(),
            );

            return false;
        } catch (\Throwable $e) {
            Log::warning('Twilio WhatsApp exception: ' . $e->getMessage());
            $this->meter->record('whatsapp', $recipient, MessageLog::STATUS_FAILED, $context, error: $e->getMessage());

            return false;
        }
    }

    /**
     * Basic-auth pair: prefer the API key (sid+secret); fall back to account SID + auth token.
     *
     * @return array{0:string,1:string}
     */
    private function authPair(string $accountSid): array
    {
        $keySid = (string) config('services.whatsapp.api_key_sid');
        $keySecret = (string) config('services.whatsapp.api_key_secret');

        if ($keySid !== '' && $keySecret !== '') {
            return [$keySid, $keySecret];
        }

        return [$accountSid, (string) config('services.whatsapp.auth_token')];
    }

    /** Build the `whatsapp:+E164` recipient, or null if the number can't be normalised. */
    private function toWhatsApp(string $phone): ?string
    {
        $e164 = $this->e164($phone);

        return preg_match('/^\+\d{8,15}$/', $e164) ? 'whatsapp:' . $e164 : null;
    }

    /** Normalise a phone to E.164 (+<country><number>), defaulting bare 10-digit numbers to +91. */
    private function e164(string $phone): string
    {
        $phone = trim($phone);
        if (str_starts_with($phone, '+')) {
            return '+' . preg_replace('/\D/', '', $phone);
        }

        $digits = preg_replace('/\D/', '', $phone);
        $cc = preg_replace('/\D/', '', (string) config('services.whatsapp.default_country', '91'));

        // Trunk-prefixed local numbers (e.g. 0XXXXXXXXXX) — drop the leading zero(s).
        $digits = ltrim($digits, '0');

        // 10-digit local → prepend country code; already carries a country code → use as-is.
        if (strlen($digits) === 10) {
            return '+' . $cc . $digits;
        }

        return '+' . $digits;
    }
}
