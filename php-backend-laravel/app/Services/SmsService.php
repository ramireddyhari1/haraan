<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MessageLog;
use App\Support\MessageContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Plain SMS via Twilio — the fallback channel for ticket delivery while the WhatsApp sender is
 * pending approval (or whenever a WhatsApp send fails). Uses the same Twilio account as
 * WhatsAppService but an SMS-capable 'from' number. Best-effort: logs and returns bool, never throws.
 *
 * India note: transactional SMS to Indian numbers via Twilio needs a registered Sender ID + a
 * DLT-approved template; without that, carriers may block the message (Twilio surfaces the error).
 */
class SmsService
{
    private const API = 'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json';

    public function __construct(private readonly MessageMeter $meter) {}

    public function isConfigured(): bool
    {
        $c = config('services.whatsapp');

        return (bool) ($c['account_sid'] ?? null)
            && (bool) ($c['sms_from'] ?? null)
            && (((bool) ($c['api_key_sid'] ?? null) && (bool) ($c['api_key_secret'] ?? null))
                || (bool) ($c['auth_token'] ?? null));
    }

    public function sendSms(string $phone, string $message, ?MessageContext $context = null): bool
    {
        $enabled = filter_var(config('services.whatsapp.sms_enabled', false), FILTER_VALIDATE_BOOLEAN);
        if (! $enabled) {
            Log::info("SMS (disabled — not sent) to {$phone}: {$message}");
            $this->meter->record('sms', $phone, MessageLog::STATUS_DISABLED, $context);

            return false;
        }

        if (! $this->isConfigured()) {
            Log::warning('SMS not sent: Twilio SMS sender / credentials not configured.');
            $this->meter->record('sms', $phone, MessageLog::STATUS_UNCONFIGURED, $context);

            return false;
        }

        $to = $this->e164($phone);
        if (! preg_match('/^\+\d{8,15}$/', $to)) {
            Log::warning("SMS not sent: unroutable number {$phone}");
            $this->meter->record('sms', $phone, MessageLog::STATUS_UNROUTABLE, $context);

            return false;
        }

        $accountSid = (string) config('services.whatsapp.account_sid');

        try {
            $response = Http::withBasicAuth(...$this->authPair($accountSid))
                ->asForm()
                ->connectTimeout(5)->timeout(20)
                ->post(sprintf(self::API, $accountSid), [
                    'From' => $this->e164((string) config('services.whatsapp.sms_from')),
                    'To' => $to,
                    'Body' => $message,
                ]);

            if ($response->successful()) {
                // SMS bills per message, not per 24h window, so the meter records it
                // without opening a conversation (see MessageMeter).
                $this->meter->record('sms', $to, MessageLog::STATUS_SENT, $context, providerMessageId: $response->json('sid'));

                return true;
            }

            Log::warning('Twilio SMS send failed (' . $response->status() . '): ' . $response->body());
            $this->meter->record(
                'sms',
                $to,
                MessageLog::STATUS_FAILED,
                $context,
                error: 'HTTP ' . $response->status() . ': ' . $response->body(),
            );

            return false;
        } catch (\Throwable $e) {
            Log::warning('Twilio SMS exception: ' . $e->getMessage());
            $this->meter->record('sms', $to, MessageLog::STATUS_FAILED, $context, error: $e->getMessage());

            return false;
        }
    }

    /** @return array{0:string,1:string} */
    private function authPair(string $accountSid): array
    {
        $keySid = (string) config('services.whatsapp.api_key_sid');
        $keySecret = (string) config('services.whatsapp.api_key_secret');

        if ($keySid !== '' && $keySecret !== '') {
            return [$keySid, $keySecret];
        }

        return [$accountSid, (string) config('services.whatsapp.auth_token')];
    }

    /** Normalise to E.164, defaulting bare 10-digit numbers to the configured country (India). */
    private function e164(string $phone): string
    {
        $phone = trim($phone);
        if (str_starts_with($phone, '+')) {
            return '+' . preg_replace('/\D/', '', $phone);
        }

        $digits = ltrim(preg_replace('/\D/', '', $phone), '0');
        $cc = preg_replace('/\D/', '', (string) config('services.whatsapp.default_country', '91'));

        return strlen($digits) === 10 ? '+' . $cc . $digits : '+' . $digits;
    }
}
