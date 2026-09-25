<?php

declare(strict_types=1);

namespace App\Support\Membership;

use App\Models\AppSetting;

/**
 * Every tunable membership number and piece of copy, editable by an admin in /control
 * (Finance → Membership settings). A value saved there wins; the config/env value is only the
 * fallback for a key nobody has set, so a fresh deploy behaves exactly as configured.
 *
 * Stored in app_settings under the `membership` group, which AppSetting caches as one snapshot.
 */
final class MembershipSettings
{
    public const GROUP = 'membership';

    /**
     * Numeric settings: [config path, fallback, min, max, label, help].
     *
     * @var array<string, array{0: string, 1: int, 2: int, 3: int, 4: string, 5: string}>
     */
    public const NUMBERS = [
        'checkout_ttl_minutes' => ['membership.checkout_ttl_minutes', 30, 5, 240, 'Checkout expires after (minutes)', 'An unpaid checkout is abandoned after this long.'],
        'grace_hours' => ['membership.grace_hours', 48, 0, 336, 'Renewal grace period (hours)', 'How long a plan keeps working past its period end while a renewal is being confirmed.'],
        'total_count_month' => ['membership.total_count.month', 120, 1, 600, 'Monthly: billing cycles per subscription', 'Razorpay completes the subscription after this many charges; the member then subscribes again.'],
        'total_count_quarter' => ['membership.total_count.quarter', 40, 1, 200, '3 months: billing cycles per subscription', ''],
        'total_count_half_year' => ['membership.total_count.half_year', 20, 1, 100, '6 months: billing cycles per subscription', ''],
        'total_count_year' => ['membership.total_count.year', 10, 1, 50, 'Yearly: billing cycles per subscription', ''],
        'early_access_max_hours' => ['membership.early_access_max_hours', 72, 0, 720, 'Early ticket access: most hours allowed', 'Caps any plan’s early-access hours, including a plan set to unlimited.'],
        'priority_booking_max_days' => ['membership.priority_booking_max_days', 30, 0, 365, 'Priority venue booking: most extra days allowed', 'Caps any plan’s extra booking days, including a plan set to unlimited.'],
        'venue_booking_window_days' => ['venues.booking_window_days', 60, 1, 365, 'Default venue booking window (days ahead)', 'For venues that haven’t set their own window. Members’ priority days are added on top.'],
        'insight_sport_cooldown_days' => ['membership.insight_sport_cooldown_days', 7, 0, 90, 'Advanced insights: days before a chosen sport can be swapped', '0 turns the cooldown off.'],
    ];

    /**
     * Copy settings: [fallback, max length, label, help].
     *
     * @var array<string, array{0: string, 1: int, 2: string, 3: string}>
     */
    public const TEXTS = [
        'web_headline' => ['Membership', 60, 'Website page headline', 'The title on haraan.app/membership.'],
        'web_lede' => ['Pro and Hero, across Events, Pulse and Actionboard.', 160, 'Website page intro', 'One line under the headline.'],
        'app_store_note' => ['Plans can’t be bought in the app.', 160, 'App note when in-app checkout is off', 'Shown on the app’s Membership screen instead of a buy button.'],
    ];

    /** Feature flag (Platform → Feature flags) that lets the app sell plans itself. Off by default. */
    public const IN_APP_CHECKOUT_FLAG = 'membership_in_app_checkout';

    public static function int(string $key): int
    {
        [$path, $fallback, $min, $max] = self::NUMBERS[$key] ?? throw new \InvalidArgumentException("Unknown membership setting [{$key}].");

        $stored = AppSetting::get(self::storageKey($key));
        $value = $stored !== null && $stored !== '' && is_numeric($stored) ? (int) $stored : (int) config($path, $fallback);

        return max($min, min($max, $value));
    }

    public static function text(string $key): string
    {
        [$fallback] = self::TEXTS[$key] ?? throw new \InvalidArgumentException("Unknown membership setting [{$key}].");
        $stored = trim((string) AppSetting::get(self::storageKey($key)));

        return $stored !== '' ? $stored : $fallback;
    }

    /** Billing cycles for a price interval. */
    public static function totalCount(string $interval): int
    {
        return isset(self::NUMBERS['total_count_'.$interval]) ? self::int('total_count_'.$interval) : 12;
    }

    public static function storageKey(string $key): string
    {
        return self::GROUP.'.'.$key;
    }
}
