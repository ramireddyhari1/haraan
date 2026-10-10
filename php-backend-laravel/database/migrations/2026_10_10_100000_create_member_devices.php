<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Signed-in devices, and how many a member's plan allows at once.
 *
 * One row per (member, app install) or (member, browser). The member app's JWT carries the
 * row's public_id as `sid`; a website session stores it. The auth middleware refuses a
 * signed-out row and holds a row over the plan's limit at the device chooser.
 *
 * The limit itself is the member feature `account.devices` (Free 1, Pro 1, Hero 3), edited
 * per plan in /control like every other entitlement. Existing rows are left alone, so a
 * re-run after /control edits changes nothing.
 */
return new class extends Migration
{
    private const KEY = 'account.devices';

    private const PLANS = ['free' => 1, 'pro' => 1, 'hero' => 3];

    public function up(): void
    {
        Schema::create('member_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('public_id', 40)->unique();
            $table->string('surface', 8);            // app | web
            $table->string('platform', 16);          // android | ios | web
            $table->string('install_id', 80)->nullable();
            $table->string('name', 120);
            $table->string('app_version', 32)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('status', 12)->default('active'); // active | pending | revoked
            $table->timestamp('signed_in_at');
            $table->timestamp('last_active_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason', 32)->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'install_id']);
        });

        $now = now();
        if (! DB::table('member_features')->where('key', self::KEY)->exists()) {
            DB::table('member_features')->insert([
                'key' => self::KEY,
                'name' => 'Signed-in devices',
                'description' => 'How many phones and browsers can stay signed in to your account at the same time.',
                'type' => 'limit', 'unit' => 'devices', 'sort' => 5, 'is_visible' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $plans = DB::table('member_plans')->pluck('id', 'code');
        foreach (self::PLANS as $code => $limit) {
            $planId = $plans[$code] ?? null;
            if ($planId === null) {
                continue;
            }
            $exists = DB::table('member_plan_entitlements')
                ->where('plan_id', $planId)->where('feature_key', self::KEY)->exists();
            if (! $exists) {
                DB::table('member_plan_entitlements')->insert([
                    'plan_id' => $planId, 'feature_key' => self::KEY, 'enabled' => true,
                    'limit_value' => $limit, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('member_plan_entitlements')->where('feature_key', self::KEY)->delete();
        DB::table('member_entitlement_overrides')->where('feature_key', self::KEY)->delete();
        DB::table('member_features')->where('key', self::KEY)->delete();
        Schema::dropIfExists('member_devices');
    }
};
