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

    // Self-hosted WhatsApp bridge (whatsapp-web.js). Read via config() — NOT env() — at
    // runtime, so it survives `config:cache` (env() returns null once config is cached).
    'whatsapp' => [
        'bridge_enabled' => env('WHATSAPP_BRIDGE_ENABLED', false),
        'bridge_url' => env('WHATSAPP_BRIDGE_URL', 'http://localhost:8090/api/send-message'),
    ],

];
