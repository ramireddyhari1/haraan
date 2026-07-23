<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Google Sign-In. `client_id` is the OAuth **Web application** client ID from the
    // Google Cloud Console — it's the audience the mobile ID token is minted for, and
    // GoogleAuthController rejects any token whose `aud` doesn't match it.
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // WhatsApp delivery via Twilio (replaces the old self-hosted whatsapp-web.js bridge).
    // All values read through config() so they survive `config:cache`.
    'whatsapp' => [
        // Toggle: when false, WhatsAppService is a no-op (logs only) — e.g. local dev.
        'enabled' => env('TWILIO_WHATSAPP_ENABLED', false),

        // Twilio auth: prefer an API key (SID + secret) over the account Auth Token.
        // The account SID is always required (it's in the REST resource path).
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'api_key_sid' => env('TWILIO_API_KEY_SID'),
        'api_key_secret' => env('TWILIO_API_KEY_SECRET'),

        // The WhatsApp-enabled sender, E.164 without the "whatsapp:" prefix (we add it).
        // e.g. +16293174010, or the Twilio sandbox +14155238886 while testing.
        'from' => env('TWILIO_WHATSAPP_FROM'),

        // Country code prepended to bare 10-digit local numbers (India = 91).
        'default_country' => env('TWILIO_DEFAULT_COUNTRY', '91'),

        // SMS fallback: when a WhatsApp send fails (e.g. sender not yet approved), the ticket
        // is delivered as a plain SMS instead. Uses the same Twilio account; 'sms_from' must be
        // an SMS-capable Twilio number (the purchased +1 number works).
        'sms_enabled' => env('TWILIO_SMS_ENABLED', false),
        'sms_from' => env('TWILIO_SMS_FROM'),
    ],

    // Public QR image generator for ticket QRs (/t/{code}/qr.png). Swappable if the default
    // service is ever unavailable; must accept ?data=&size=WxH and return a PNG.
    'qr' => [
        'endpoint' => env('QR_ENDPOINT', 'https://api.qrserver.com/v1/create-qr-code/'),
    ],

    // Razorpay Standard Checkout. `key` (public key id) is safe to expose to the
    // browser; `secret` NEVER reaches the frontend — it signs orders and verifies the
    // payment signature server-side only. Read via config() so it survives config:cache.
    'razorpay' => [
        'key'    => env('RAZORPAY_KEY_ID'),
        'secret' => env('RAZORPAY_KEY_SECRET'),
    ],

];
