<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $apiUrl = 'http://localhost:8080/api/send-message';

    /**
     * Send a WhatsApp message.
     *
     * @param string $phone The phone number with country code.
     * @param string $message The message text to send.
     * @return bool True if successful, false otherwise.
     */
    public function sendMessage(string $phone, string $message): bool
    {
        try {
            $response = Http::post($this->apiUrl, [
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
