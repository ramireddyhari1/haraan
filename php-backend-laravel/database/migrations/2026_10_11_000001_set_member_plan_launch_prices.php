<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Final Pro/Hero launch prices (2026-10-11):
 *   Pro  ₹99/month, ₹259/3 months, ₹1,199/year
 *   Hero ₹249/month, ₹629/3 months, ₹2,400/year
 * The 6-month term is no longer offered.
 *
 * A price linked to a Razorpay plan is immutable (see MemberPlanPrice), so:
 *  - an unlinked draft at the interval just gets the new amount;
 *  - a linked price at a different amount is taken off sale and a new draft is added beside
 *    it (existing subscribers keep what they signed up at);
 *  - a linked price already at the right amount is left alone.
 * New drafts start off sale — /control → Member plans → Prices → "Create in Razorpay",
 * then "Put on sale".
 */
return new class extends Migration
{
    /** @var array<string, array<string, int>> plan => interval => paise */
    private const PRICES = [
        'pro' => ['month' => 9900, 'quarter' => 25900, 'year' => 119900],
        'hero' => ['month' => 24900, 'quarter' => 62900, 'year' => 240000],
    ];

    private const RETIRED = ['half_year'];

    public function up(): void
    {
        $now = now();
        $plans = DB::table('member_plans')->pluck('id', 'code');

        foreach (self::PRICES as $code => $byInterval) {
            $planId = $plans[$code] ?? null;
            if ($planId === null) {
                continue;
            }

            foreach ($byInterval as $interval => $paise) {
                $rows = fn () => DB::table('member_plan_prices')->where('plan_id', $planId)->where('interval', $interval);

                // Linked prices at another amount can't be edited — retire them from sale.
                $rows()->whereNotNull('razorpay_plan_id')->where('amount_paise', '!=', $paise)
                    ->update(['is_active' => false, 'updated_at' => $now]);

                if ($rows()->whereNotNull('razorpay_plan_id')->where('amount_paise', $paise)->exists()) {
                    // Already linked at this price; any stray draft would just be a duplicate.
                    $rows()->whereNull('razorpay_plan_id')->delete();

                    continue;
                }

                $draft = $rows()->whereNull('razorpay_plan_id')->orderBy('id')->first();
                if ($draft !== null) {
                    DB::table('member_plan_prices')->where('id', $draft->id)
                        ->update(['amount_paise' => $paise, 'updated_at' => $now]);
                    $rows()->whereNull('razorpay_plan_id')->where('id', '!=', $draft->id)->delete();
                } else {
                    DB::table('member_plan_prices')->insert([
                        'plan_id' => $planId, 'interval' => $interval, 'amount_paise' => $paise,
                        'currency' => 'INR', 'is_active' => false, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }

            // Terms no longer offered: drafts go, linked ones come off sale (subscribers keep them).
            DB::table('member_plan_prices')->where('plan_id', $planId)->whereIn('interval', self::RETIRED)
                ->whereNull('razorpay_plan_id')->delete();
            DB::table('member_plan_prices')->where('plan_id', $planId)->whereIn('interval', self::RETIRED)
                ->update(['is_active' => false, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        // Prices are catalogue data edited in /control; nothing to restore automatically.
    }
};
