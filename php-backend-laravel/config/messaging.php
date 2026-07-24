<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Local timezone for scheduling
    |--------------------------------------------------------------------------
    |
    | Event dates/times and venue slot times are entered by admins as LOCAL wall
    | clock ("19:00" means 7pm in Hyderabad), but the app runs on UTC. The rest
    | of the codebase compares those naive values against now() directly, which
    | is harmless for "is this event upcoming?" and badly wrong for "remind them
    | 2 hours before" — it would fire 7.5 hours early.
    |
    | Journeys therefore interpret stored dates/times in this zone and convert to
    | UTC before scheduling. Change it if the business isn't operating on IST.
    |
    */
    'local_timezone' => env('MESSAGING_LOCAL_TZ', 'Asia/Kolkata'),

    /*
    |--------------------------------------------------------------------------
    | Inbound webhook
    |--------------------------------------------------------------------------
    |
    | Twilio POSTs inbound WhatsApp messages to /api/webhooks/twilio/whatsapp.
    | The endpoint is public, so the X-Twilio-Signature check is its only
    | authentication — leave validation on everywhere except local testing.
    |
    | 'url' overrides the URL used to recompute the signature. It must match what
    | Twilio called byte for byte; normally APP_URL + path is right, and this is
    | only needed if the public URL and APP_URL ever diverge.
    |
    */
    'webhook' => [
        'validate_signature' => env('MESSAGING_VERIFY_WEBHOOK', true),
        'url' => env('MESSAGING_WEBHOOK_URL', ''),
    ],

    'journeys' => [

        /*
        | MASTER SWITCH — off by default, on purpose.
        |
        | Enqueueing runs regardless (so the queue is populated and inspectable),
        | but nothing is DELIVERED until this is true. Deploying the journey
        | engine must never start messaging real customers as a side effect;
        | turning it on is a deliberate act.
        */
        'enabled' => env('MESSAGING_JOURNEYS_ENABLED', false),

        /** How far ahead to look when enqueueing. */
        'horizon_days' => (int) env('MESSAGING_JOURNEY_HORIZON_DAYS', 7),

        /** Hours after an event/slot ends before asking for a review. */
        'review_delay_hours' => (int) env('MESSAGING_REVIEW_DELAY_HOURS', 3),

        /**
         * Assumed run time for events, which carry no end time. Only used to
         * place the review request.
         */
        'assumed_duration_hours' => (int) env('MESSAGING_ASSUMED_DURATION_HOURS', 3),

        /**
         * Don't deliver a journey message outside these local hours — nobody
         * wants a review request at 3am. A due message waits for the window
         * rather than being dropped.
         */
        'quiet_hours' => [
            'start' => 21,  // 9pm
            'end' => 8,     // 8am
        ],

        /** Give up after this many delivery attempts. */
        'max_attempts' => 3,
    ],

];
