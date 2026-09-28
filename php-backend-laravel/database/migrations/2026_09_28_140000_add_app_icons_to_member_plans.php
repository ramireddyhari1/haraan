<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Member home-screen icons, as two ordinary plan features:
 *
 *  - app.icon_pro:  the midnight-navy Pro icon. Pro on, Hero on.
 *  - app.icon_hero: the onyx-and-gold Hero icon. Hero only.
 *
 * Free has neither. Values are editable per plan in /control like every other feature; existing
 * rows are left alone, so re-running after /control edits changes nothing.
 */
return new class extends Migration
{
    /** @var array<string, array{name: string, description: string, sort: int, plans: array<string, bool>}> */
    private const FEATURES = [
        'app.icon_pro' => [
            'name' => 'Pro app icon',
            'description' => 'Put the midnight-navy Pro icon on your home screen.',
            'sort' => 91,
            'plans' => ['free' => false, 'pro' => true, 'hero' => true],
        ],
        'app.icon_hero' => [
            'name' => 'Hero app icon',
            'description' => 'Put the onyx-and-gold Hero icon on your home screen.',
            'sort' => 92,
            'plans' => ['free' => false, 'pro' => false, 'hero' => true],
        ],
    ];

    public function up(): void
    {
        $now = now();
        $plans = DB::table('member_plans')->pluck('id', 'code');

        foreach (self::FEATURES as $key => $feature) {
            if (! DB::table('member_features')->where('key', $key)->exists()) {
                DB::table('member_features')->insert([
                    'key' => $key, 'name' => $feature['name'], 'description' => $feature['description'],
                    'type' => 'boolean', 'unit' => null, 'sort' => $feature['sort'], 'is_visible' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            foreach ($feature['plans'] as $code => $enabled) {
                $planId = $plans[$code] ?? null;
                if ($planId === null) {
                    continue;
                }
                $exists = DB::table('member_plan_entitlements')
                    ->where('plan_id', $planId)->where('feature_key', $key)->exists();
                if (! $exists) {
                    DB::table('member_plan_entitlements')->insert([
                        'plan_id' => $planId, 'feature_key' => $key, 'enabled' => $enabled,
                        'limit_value' => null, 'created_at' => $now, 'updated_at' => $now,
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
    }
};
