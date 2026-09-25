<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The switch that lets the Android app sell Pro and Hero itself. Off: the app shows plans
 * and members buy on haraan.app (Google Play billing policy). Admins flip it in
 * Finance → Membership settings or Platform → Feature flags.
 */
return new class extends Migration
{
    private const KEY = 'membership_in_app_checkout';

    public function up(): void
    {
        if (DB::table('feature_flags')->where('key', self::KEY)->exists()) {
            return;
        }

        DB::table('feature_flags')->insert([
            'key' => self::KEY,
            'name' => 'Membership: in-app checkout',
            'description' => 'Lets the Android app sell Pro and Hero directly. Off = the app shows plans only.',
            'enabled' => false,
            'rollout_percentage' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('feature_flags')->where('key', self::KEY)->delete();
    }
};
