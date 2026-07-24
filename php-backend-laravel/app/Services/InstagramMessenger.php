<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ChannelConnection;
use App\Models\MessageLog;
use App\Support\MessageContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sending Instagram DMs through the Meta Graph API.
 *
 * Deliberately reply-only. Instagram allows a message ONLY within 24 hours of
 * the user's last one (the human-agent tag stretches that to 7 days for a real
 * person's answer, which is not what an automation is). There is no template
 * escape hatch as there is on WhatsApp, so there is no such thing as a cold
 * Instagram DM — and nothing in this class offers one.
 *
 * Mirrors WhatsAppService: best-effort, returns bool, never throws into a caller,
 * and every attempt lands in the messaging ledger.
 */
final class InstagramMessenger
{
    public function __construct(private readonly MessageMeter $meter) {}

    public function isConfigured(): bool
    {
        return filled(config('services.instagram.app_secret'));
    }

    /**
     * Reply to a user on the account they messaged.
     *
     * @param  string  $recipientId  the Instagram-scoped user id from the webhook
     */
    public function reply(ChannelConnection $connection, string $recipientId, string $text, ?MessageContext $context = null): bool
    {
        $context ??= new MessageContext($connection->partner_id, MessageContext::SERVICE);

        if (! $connection->isUsable()) {
            Log::warning('Instagram reply skipped: connection ' . $connection->id . ' is not usable.');
            $this->meter->record('instagram', $recipientId, MessageLog::STATUS_UNCONFIGURED, $context);

            return false;
        }

        $version = (string) config('services.instagram.graph_version', 'v21.0');
        $url = "https://graph.facebook.com/{$version}/{$connection->external_id}/messages";

        try {
            $response = Http::withToken((string) $connection->access_token)
                ->acceptJson()
                ->connectTimeout(5)->timeout(20)
                ->post($url, [
                    'recipient' => ['id' => $recipientId],
                    'message' => ['text' => $text],
                ]);

            if ($response->successful()) {
                $this->meter->record(
                    'instagram',
                    $recipientId,
                    MessageLog::STATUS_SENT,
                    $context,
                    providerMessageId: $response->json('message_id'),
                );

                return true;
            }

            $error = $response->json('error.message') ?? $response->body();
            Log::warning('Instagram send failed (' . $response->status() . '): ' . $error);

            // A dead or revoked token is worth recording on the connection, not
            // just in a log line — the admin screen is where someone will look.
            if (in_array($response->status(), [401, 403], true)) {
                $connection->update([
                    'status' => ChannelConnection::STATUS_ERROR,
                    'last_error' => mb_substr((string) $error, 0, 500),
                ]);
            }

            $this->meter->record('instagram', $recipientId, MessageLog::STATUS_FAILED, $context, error: (string) $error);

            return false;
        } catch (Throwable $e) {
            Log::warning('Instagram send exception: ' . $e->getMessage());
            $this->meter->record('instagram', $recipientId, MessageLog::STATUS_FAILED, $context, error: $e->getMessage());

            return false;
        }
    }
}
