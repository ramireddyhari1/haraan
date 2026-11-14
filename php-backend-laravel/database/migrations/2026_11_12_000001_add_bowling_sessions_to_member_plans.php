<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bowling sessions — a named practice session filmed with the LBW camera, no match needed,
 * each ball's tracked video kept on that phone under the session's name. Hero only.
 *
 * Editable per plan in /control like every other feature; existing rows are left alone, so
 * re-running after /control edits changes nothing.
 */
return new class extends Migration
{
    private const KEY = 'tracking.bowling_sessions';

    private const PLANS = ['free' => false, 'pro' => false, 'hero' => true];

    public function up(): void
    {
        $now = now();
        if (! DB::table('member_features')->where('key', self::KEY)->exists()) {
            DB::table('member_features')->insert([
                'key' => self::KEY,
                'name' => 'Bowling sessions',
                'description' => 'Start a named practice session with the LBW camera. Every ball is tracked and its video kept on your phone, by session.',
                'type' => 'boolean', 'unit' => null, 'sort' => 94, 'is_visible' => true,
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
