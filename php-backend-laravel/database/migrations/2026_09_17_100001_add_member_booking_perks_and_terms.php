<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Booking perks and longer billing terms for member plans.
 *
 *  - venues.booking_window_days: how far ahead customers may book this venue (null = the
 *    platform default in config/venues.php).
 *  - Two plan features, configured so `migrate` leaves them working: early ticket access
 *    (Free off, Pro 12h, Hero 24h) and priority venue booking (Free off, Pro +1 day,
 *    Hero +3 days). Existing rows are left alone, so re-running after /control edits changes
 *    nothing.
 *  - Launch prices: Pro ₹99 / ₹249 (3 mo) / ₹449 (6 mo) / ₹799 (yr), Hero ₹199 / ₹499 /
 *    ₹899 / ₹1,599. Only unlinked draft prices are touched; a price already linked to a
 *    Razorpay plan is immutable history and is never rewritten.
 */
return new class extends Migration
{
    /** @var array<string, array{name: string, description: string, unit: string, sort: int, plans: array<string, array{0: bool, 1: int|null}>}> */
    private const FEATURES = [
        'events.early_access' => [
            'name' => 'Early ticket access',
            'description' => 'Book tickets before sales open to everyone.',
            'unit' => 'hours early',
            'sort' => 80,
            'plans' => ['free' => [false, 0], 'pro' => [true, 12], 'hero' => [true, 24]],
        ],
        'venues.priority_booking_days' => [
            'name' => 'Priority venue booking',
            'description' => 'Book courts and turfs further ahead than everyone else.',
            'unit' => 'days earlier',
            'sort' => 90,
            'plans' => ['free' => [false, 0], 'pro' => [true, 1], 'hero' => [true, 3]],
        ],
    ];

    /** @var array<string, array<string, int>> plan => interval => paise */
    private const PRICES = [
        'pro' => ['month' => 9900, 'quarter' => 24900, 'half_year' => 44900, 'year' => 79900],
        'hero' => ['month' => 19900, 'quarter' => 49900, 'half_year' => 89900, 'year' => 159900],
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('venues', 'booking_window_days')) {
            Schema::table('venues', function (Blueprint $table) {
                $table->unsignedSmallInteger('booking_window_days')->nullable();
            });
        }

        $now = now();
        $plans = DB::table('member_plans')->pluck('id', 'code');

        foreach (self::FEATURES as $key => $feature) {
            if (! DB::table('member_features')->where('key', $key)->exists()) {
                DB::table('member_features')->insert([
                    'key' => $key, 'name' => $feature['name'], 'description' => $feature['description'],
                    'type' => 'limit', 'unit' => $feature['unit'], 'sort' => $feature['sort'], 'is_visible' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            foreach ($feature['plans'] as $code => [$enabled, $limit]) {
                $planId = $plans[$code] ?? null;
                if ($planId === null) {
                    continue;
                }
                $exists = DB::table('member_plan_entitlements')
                    ->where('plan_id', $planId)->where('feature_key', $key)->exists();
                if (! $exists) {
                    DB::table('member_plan_entitlements')->insert([
                        'plan_id' => $planId, 'feature_key' => $key, 'enabled' => $enabled,
                        'limit_value' => $limit, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }

        foreach (self::PRICES as $code => $byInterval) {
            $planId = $plans[$code] ?? null;
            if ($planId === null) {
                continue;
            }
            foreach ($byInterval as $interval => $paise) {
                $rows = DB::table('member_plan_prices')->where('plan_id', $planId)->where('interval', $interval);
                $draft = (clone $rows)->whereNull('razorpay_plan_id')->orderBy('id')->first();

                if ($draft !== null) {
                    DB::table('member_plan_prices')->where('id', $draft->id)
                        ->update(['amount_paise' => $paise, 'updated_at' => $now]);
                } elseif (! (clone $rows)->exists()) {
                    DB::table('member_plan_prices')->insert([
                        'plan_id' => $planId, 'interval' => $interval, 'amount_paise' => $paise,
                        'currency' => 'INR', 'is_active' => false, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $keys = array_keys(self::FEATURES);
        DB::table('member_plan_entitlements')->whereIn('feature_key', $keys)->delete();
        DB::table('member_entitlement_overrides')->whereIn('feature_key', $keys)->delete();
        DB::table('member_features')->whereIn('key', $keys)->delete();

        // Draft 3- and 6-month prices go; prices already linked to Razorpay stay as history.
        DB::table('member_plan_prices')->whereIn('interval', ['quarter', 'half_year'])->whereNull('razorpay_plan_id')->delete();

        if (Schema::hasColumn('venues', 'booking_window_days')) {
            Schema::table('venues', function (Blueprint $table) {
                $table->dropColumn('booking_window_days');
            });
        }
    }
};
