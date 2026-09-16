<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sport-specific advanced insights for member plans.
 *
 *  - member_sport_selections: the sports a member chose for advanced insights, when their
 *    plan gives a fixed number of sports (Pro: 3). `created_at` is when each sport was
 *    chosen — it orders which selections count if a limit is lowered, and dates the change
 *    cooldown. A plan with every sport (Hero) needs no rows.
 *  - The `insights.advanced_sports` feature and its value on each existing plan, inserted
 *    here so a deploy's `migrate` leaves the gate configured: Free off, Pro 3, Hero all.
 *    Existing rows are left alone, so re-running after /control edits changes nothing.
 */
return new class extends Migration {
    private const KEY = 'insights.advanced_sports';

    public function up(): void
    {
        Schema::create('member_sport_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('sport', 30);
            $table->timestamps();

            $table->unique(['user_id', 'sport']);
        });

        $now = now();

        if (! DB::table('member_features')->where('key', self::KEY)->exists()) {
            DB::table('member_features')->insert([
                'key' => self::KEY,
                'name' => 'Advanced insights',
                'description' => 'Match insights and AI reads for the sports you choose.',
                'type' => 'limit',
                'unit' => 'sports',
                'sort' => 15,
                'is_visible' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // [enabled, limit] — null limit = every sport.
        $values = ['free' => [false, 0], 'pro' => [true, 3], 'hero' => [true, null]];

        foreach (DB::table('member_plans')->get(['id', 'code']) as $plan) {
            if (DB::table('member_plan_entitlements')->where('plan_id', $plan->id)->where('feature_key', self::KEY)->exists()) {
                continue;
            }

            // A plan created in /control before this feature existed starts with it off.
            [$enabled, $limit] = $values[$plan->code] ?? [false, 0];

            DB::table('member_plan_entitlements')->insert([
                'plan_id' => $plan->id,
                'feature_key' => self::KEY,
                'enabled' => $enabled,
                'limit_value' => $limit,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('member_plan_entitlements')->where('feature_key', self::KEY)->delete();
        DB::table('member_features')->where('key', self::KEY)->delete();
        Schema::dropIfExists('member_sport_selections');
    }
};
