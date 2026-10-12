<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Track a clip — a delivery filmed on any phone, picked from the gallery and run through the
 * match camera's ball tracking — as an ordinary plan feature: Hero only.
 *
 * Editable per plan in /control like every other feature; existing rows are left alone, so
 * re-running after /control edits changes nothing.
 */
return new class extends Migration
{
    private const KEY = 'tracking.clip_upload';

    private const PLANS = ['free' => false, 'pro' => false, 'hero' => true];

    public function up(): void
    {
        $now = now();
        if (! DB::table('member_features')->where('key', self::KEY)->exists()) {
            DB::table('member_features')->insert([
                'key' => self::KEY,
                'name' => 'Track a clip',
                'description' => 'Pick a video from your phone and see every ball tracked: speed, length, line and the 3D replay.',
                'type' => 'boolean', 'unit' => null, 'sort' => 93, 'is_visible' => true,
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
