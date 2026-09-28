<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The two ActionBoard perks that used to ride on the admin-granted blue tick become member
 * plan features:
 *
 *  - matches.see_viewers: tap the "watching now" chip to see who is in the room.
 *  - matches.shot_plotting: the scorer is asked where each four and six went.
 *
 * Free off, Pro on, Hero on. The blue tick itself stays what it is — an identity mark granted
 * in /control — it just no longer unlocks anything. Existing rows are left alone, so
 * re-running after /control edits changes nothing.
 */
return new class extends Migration
{
    /** @var array<string, array{name: string, description: string, sort: int, plans: array<string, bool>}> */
    private const FEATURES = [
        'matches.see_viewers' => [
            'name' => "See who's watching",
            'description' => 'Tap the watching-now chip on any match to see who is in the room.',
            'sort' => 55,
            'plans' => ['free' => false, 'pro' => true, 'hero' => true],
        ],
        'matches.shot_plotting' => [
            'name' => 'Shot plotting',
            'description' => 'Plot where every four and six went, for a wagon wheel on your matches.',
            'sort' => 56,
            'plans' => ['free' => false, 'pro' => true, 'hero' => true],
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
