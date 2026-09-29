<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * matches.shot_types: the scorer is asked which shot each four and six was, for the
 * Insights tab's shots section. Same plans as shot plotting (Free off, Pro on, Hero on) and
 * edited in /control like every other plan feature. Existing rows are left alone, so
 * re-running after /control edits changes nothing.
 */
return new class extends Migration
{
    private const KEY = 'matches.shot_types';

    private const PLANS = ['free' => false, 'pro' => true, 'hero' => true];

    public function up(): void
    {
        $now = now();
        if (! DB::table('member_features')->where('key', self::KEY)->exists()) {
            DB::table('member_features')->insert([
                'key' => self::KEY,
                'name' => 'Shot types',
                'description' => 'Name the shot behind every four and six — cover drive, pull, sweep — for a shots breakdown on your matches.',
                'type' => 'boolean', 'unit' => null, 'sort' => 57, 'is_visible' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $plans = DB::table('member_plans')->pluck('id', 'code');
        foreach (self::PLANS as $code => $enabled) {
            $planId = $plans[$code] ?? null;
            if ($planId === null) {
                continue;
            }
            $exists = DB::table('member_plan_entitlements')
                ->where('plan_id', $planId)->where('feature_key', self::KEY)->exists();
            if (! $exists) {
                DB::table('member_plan_entitlements')->insert([
                    'plan_id' => $planId, 'feature_key' => self::KEY, 'enabled' => $enabled,
                    'limit_value' => null, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('member_plan_entitlements')->where('feature_key', self::KEY)->delete();
        DB::table('member_entitlement_overrides')->where('feature_key', self::KEY)->delete();
        DB::table('member_features')->where('key', self::KEY)->delete();
    }
};
