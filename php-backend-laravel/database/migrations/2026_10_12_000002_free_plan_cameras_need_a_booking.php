<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Second-screen cameras are bought, not free: Pro and Hero include them, and anyone else gets
 * them during a venue slot they booked (CameraAccess). So the Free plan's "Camera angles per
 * match" goes from 1 to 0.
 *
 * Only a value still at the old default is changed — a number an admin already set in
 * /control is theirs. down() puts the default back the same way.
 */
return new class extends Migration
{
    private const KEY = 'matches.camera_angles';

    public function up(): void
    {
        $this->setFree(from: 1, to: 0);
    }

    public function down(): void
    {
        $this->setFree(from: 0, to: 1);
    }

    private function setFree(int $from, int $to): void
    {
        $free = DB::table('member_plans')->where('code', 'free')->value('id');
        if ($free === null) {
            return;
        }

        DB::table('member_plan_entitlements')
            ->where('plan_id', $free)
            ->where('feature_key', self::KEY)
            ->where('limit_value', $from)
            ->update(['limit_value' => $to, 'updated_at' => now()]);
    }
};
