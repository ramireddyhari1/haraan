<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $apiUrl = 'http://localhost:8090/api/send-message';

    /**
     * Send a WhatsApp message.
     *
     * @param string $phone The phone number with country code.
     * @param string $message The message text to send.
     * @return bool True if successful, false otherwise.
     */
    public function sendMessage(string $phone, string $message): bool
    {
        // config() (not env()) so it survives `config:cache` — once config is cached, env()
        // returns null at runtime, which is what silently broke the bridge URL before.
        $bridgeEnabled = filter_var(config('services.whatsapp.bridge_enabled', false), FILTER_VALIDATE_BOOLEAN);

        // When the self-hosted bridge isn't enabled, local/dev has nothing on :8080 — the
        // call would hang and time out the OTP request. Skip it; the dev master code 000000
        // (see WhatsAppAuthController::verifyOtp) still lets you sign in.
        if (! $bridgeEnabled && app()->environment('local')) {
            Log::info("WhatsApp (local — not sent) to {$phone}: {$message}");
            return false;
        }

        $url = config('services.whatsapp.bridge_url', $this->apiUrl);

        try {
            $response = Http::connectTimeout(3)->timeout(20)->post($url, [
                'number' => $phone,
                'message' => $message,
            ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('WhatsApp API Error: ' . $response->body());
            return false;
        } catch (\Exception $e) {
            Log::error('WhatsApp Service Exception: ' . $e->getMessage());
            return false;
        }
    }
}
