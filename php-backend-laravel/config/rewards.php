<?php

/*
 * Post-match rewards — the few values that are NOT admin-editable, on purpose.
 *
 * Everything a business would tune (limits, switches, ad unit, copy) is in /control →
 * Platform rules → Post-match rewards. What's here is security plumbing: where Google publishes
 * the AdMob verification keys. Letting /control change that URL would let whoever controls it
 * sign their own "ad watched" callbacks, so it stays in code/config.
 */
return [
    'admob' => [
        'verifier_keys_url' => env('ADMOB_SSV_KEYS_URL', 'https://www.gstatic.com/admob/reward/verifier-keys.json'),
        'keys_cache_minutes' => 60 * 24,
    ],
];
