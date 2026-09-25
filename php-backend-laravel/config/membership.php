<?php

return [
    /*
     * Hours a paid plan keeps working past current_period_end while its renewal is still
     * being confirmed (a late webhook, or Razorpay retrying a failed charge). Long enough to
     * absorb a slow bank; short enough that a halted card doesn't mean a free month.
     */
    'grace_hours' => (int) env('MEMBERSHIP_GRACE_HOURS', 48),

    /* Minutes a created-but-unpaid checkout stays valid before it is abandoned. */
    'checkout_ttl_minutes' => (int) env('MEMBERSHIP_CHECKOUT_TTL_MINUTES', 30),

    /*
     * Days a sport chosen for advanced insights must be held before it can be swapped out.
     * Without it a fixed-sports plan becomes every sport by re-picking before each match.
     * 0 disables the cooldown.
     */
    'insight_sport_cooldown_days' => (int) env('MEMBERSHIP_INSIGHT_SPORT_COOLDOWN_DAYS', 7),

    /*
     * Ceilings for booking perks set to "unlimited" on a plan: an early-access head start and
     * extra venue booking days can't be endless, or a member could bypass a sale or a venue's
     * calendar entirely. A numeric plan value above these is capped too.
     */
    'early_access_max_hours' => (int) env('MEMBERSHIP_EARLY_ACCESS_MAX_HOURS', 72),
    'priority_booking_max_days' => (int) env('MEMBERSHIP_PRIORITY_BOOKING_MAX_DAYS', 30),

    /*
     * Billing cycles a subscription is created for. Razorpay marks it `completed` after the
     * last one; the member then simply subscribes again.
     */
    'total_count' => [
        'month' => (int) env('MEMBERSHIP_TOTAL_COUNT_MONTHLY', 120),
        'quarter' => (int) env('MEMBERSHIP_TOTAL_COUNT_QUARTERLY', 40),
        'half_year' => (int) env('MEMBERSHIP_TOTAL_COUNT_HALF_YEARLY', 20),
        'year' => (int) env('MEMBERSHIP_TOTAL_COUNT_YEARLY', 10),
    ],
];
