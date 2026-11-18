<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The app-icon library, run from /control → Finance → App icons.
 *
 * Every launcher icon the Android app can wear is baked into the APK as a disabled
 * activity-alias; one row here per alias (the keys must match AppIconSlot in AppIcons.kt).
 * Which plan may pick which, and which one goes on a member's home screen automatically,
 * is the admin's call, with no app update needed to move an icon between plans.
 *
 * Seeded to behave exactly as before: Pro/Hero pickable by whichever plans had the old
 * app.icon_pro / app.icon_hero features on, nothing auto-applied, and the nine new icons
 * given to nobody, so nobody sees them until an admin hands one out.
 */
return new class extends Migration
{
    /** key => name, in display order. Must match the manifest aliases. */
    private const SLOTS = [
        'default' => 'Haraan', 'pro' => 'Pro', 'hero' => 'Hero',
        'onyx' => 'Onyx', 'paper' => 'Paper', 'turf' => 'Turf', 'leather' => 'Leather',
        'festive' => 'Festive', 'sunrise' => 'Sunrise', 'monsoon' => 'Monsoon',
        'royal' => 'Royal', 'silver' => 'Silver',
    ];

    public function up(): void
    {
        Schema::create('app_icon_slots', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 32)->unique();
            $table->string('name', 40);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            // A seasonal window. Outside it the icon isn't offered, and a home screen wearing it
            // goes back to the member's usual icon. Both null = always.
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('app_icon_plan_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('slot_key', 32);
            $table->foreignId('plan_id')->constrained('member_plans')->cascadeOnDelete();
            $table->boolean('can_pick')->default(false);
            $table->boolean('is_auto')->default(false);
            $table->timestamps();
            $table->unique(['slot_key', 'plan_id']);
        });

        $now = now();
        $sort = 0;
        foreach (self::SLOTS as $key => $name) {
            DB::table('app_icon_slots')->insert([
                'key' => $key, 'name' => $name, 'is_active' => true, 'sort' => ++$sort,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $legacy = ['pro' => 'app.icon_pro', 'hero' => 'app.icon_hero'];
        foreach (DB::table('member_plans')->pluck('id') as $planId) {
            $rules = ['default' => true];
            foreach ($legacy as $slot => $feature) {
                $rules[$slot] = (bool) DB::table('member_plan_entitlements')
                    ->where('plan_id', $planId)->where('feature_key', $feature)->value('enabled');
            }
            foreach ($rules as $slot => $canPick) {
                DB::table('app_icon_plan_rules')->insert([
                    'slot_key' => $slot, 'plan_id' => $planId, 'can_pick' => $canPick, 'is_auto' => false,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_icon_plan_rules');
        Schema::dropIfExists('app_icon_slots');
    }
};
