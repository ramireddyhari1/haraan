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

    // WhatsApp via Meta's WhatsApp Cloud API (Graph). Twilio is gone: going direct
    // means ONE business verification, one app secret, one webhook signature scheme
    // and one template registry shared with Instagram — rather than Meta's approval
    // process plus a reseller's on top.
    //
    // NB there is no SMS here. Meta does WhatsApp, not SMS, so phone delivery is
    // WhatsApp or nothing and email is the parallel path (see BookingNotifier).
    'whatsapp' => [
        // Toggle: when false, WhatsAppService is a no-op (logs + ledger only).
        'enabled' => env('META_WHATSAPP_ENABLED', false),

        // From the Meta app dashboard → WhatsApp → API setup.
        'phone_number_id' => env('META_WHATSAPP_PHONE_NUMBER_ID'),
        'waba_id' => env('META_WHATSAPP_WABA_ID'),

        // A permanent system-user token. Sends messages as the business, so treat
        // it like a password.
        'access_token' => env('META_WHATSAPP_TOKEN'),

        'graph_version' => env('META_GRAPH_VERSION', 'v21.0'),

        // Country code prepended to bare 10-digit local numbers (India = 91).
        'default_country' => env('WHATSAPP_DEFAULT_COUNTRY', '91'),
    ],

    // Instagram DMs via the Meta Graph API. Reactive only: Instagram permits a
    // reply within 24h of the user's message and has no template escape hatch, so
    // there is no such thing as a cold Instagram DM.
    //
    // `app_secret` and `verify_token` cover BOTH Meta webhooks (Instagram and
    // WhatsApp Cloud) — one app, one signing secret, one handshake.
    'instagram' => [
        // App secret from the Meta app dashboard — signs every inbound webhook.
        'app_secret' => env('META_APP_SECRET'),

        // A string we choose; Meta echoes it back during the subscription handshake.
        'verify_token' => env('META_VERIFY_TOKEN'),

        'graph_version' => env('META_GRAPH_VERSION', 'v21.0'),

        // Local testing only — never false in production.
        'validate_signature' => env('META_VERIFY_WEBHOOK', true),
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

        // Set in the Razorpay dashboard when creating the webhook. Separate from
        // the API secret, and without it the webhook rejects everything — which
        // is the right failure, since it grants paid features.
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],

    // Anthropic Claude — powers the /partner support AI assistant (PartnerSupportAI).
    // Key NEVER reaches the frontend; the model answers server-side only. Read via
    // config() so it survives config:cache. Leave the key unset to disable the AI
    // panel gracefully (it falls back to "talk to the team").
    'anthropic' => [
        'key'   => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-4-8'),
    ],

    // Firebase phone-number sign-in for the public website login (SMS OTP).
    // These three values are the WEB app config from Firebase (Project settings →
    // General → your Web app). The api_key + auth_domain + project_id are all public
    // (they ship to the browser), so defaulting them to the known project is safe;
    // override via env if the web app is reconfigured. Leave api_key unset to hide
    // the phone option entirely (the login falls back to Google + email/password).
    'firebase' => [
        // Web app config from Firebase (Project settings → your Web app "haraan.app").
        // These are the WEB values — distinct from the Android key in google-services.json.
        'api_key'     => env('FIREBASE_WEB_API_KEY', 'AIzaSyDnXtdoL3Z7aIvC5sjhg-Jolq_xLnmLS-g'),
        'auth_domain' => env('FIREBASE_AUTH_DOMAIN', 'haraan-1a84a.firebaseapp.com'),
        'project_id'  => env('FIREBASE_PROJECT_ID', 'haraan-1a84a'),
        // appId identifies the web app to Firebase; the phone-auth reCAPTCHA/app-credential
        // check needs it (without it: auth/invalid-app-credential).
        'app_id'      => env('FIREBASE_APP_ID', '1:618469027917:web:0050ee1392e5e0f3fd6c04'),
    ],

    // Firebase Cloud Messaging — background push to the app's registered devices
    // (device_tokens). `credentials` is an absolute path to the service-account JSON
    // downloaded from Firebase (Project settings → Service accounts → Generate key);
    // the project id + private key are read from it. Leave the path unset (or the file
    // absent) and FcmClient reports not-configured — sending becomes a logged no-op,
    // so the in-app bell inbox and Reverb delivery keep working regardless.
    'fcm' => [
        'credentials' => env('FCM_CREDENTIALS_PATH'),
    ],

    // Google Maps Platform — one browser key, restricted by HTTP referrer, with
    // Maps JavaScript API + Places API + Maps Embed API enabled. Used client-side:
    // Places Autocomplete in the /control + /partner event/venue forms, and the
    // embedded map on the public event/venue detail pages. It's exposed to the
    // browser by design (that's what the referrer restriction is for). When unset,
    // the forms fall back to the manual pin/text entry and the public pages fall
    // back to a plain Maps search link — nothing breaks, it just isn't as good.
    'google_maps' => [
        'key' => env('GOOGLE_MAPS_API_KEY'),
    ],

];
