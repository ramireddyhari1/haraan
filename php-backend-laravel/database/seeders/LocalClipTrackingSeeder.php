<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MemberPlan;
use App\Models\User;
use App\Services\Membership\MemberEntitlements;
use App\Services\Membership\MemberSubscriptions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local-only Hero member for trying "Track a clip" in the app against the local server.
 *
 *   php artisan db:seed --class=LocalClipTrackingSeeder
 *
 * cliptest@haraan.test / cliptest1234 — on Hero for 30 days (an admin grant), so the
 * `tracking.clip_upload` feature is on.
 *
 * Refuses to run outside the local environment — it sets a password.
 */
class LocalClipTrackingSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->error('LocalClipTrackingSeeder only runs locally.');

            return;
        }

        $member = User::firstOrNew(['email' => 'cliptest@haraan.test']);
        $member->forceFill([
            'name' => 'Clip Tester',
            'password' => Hash::make('cliptest1234'),
            'email_verified_at' => $member->email_verified_at ?? now(),
        ])->save();

        $hero = MemberPlan::where('code', 'hero')->firstOrFail();
        app(MemberSubscriptions::class)->grant($member, $hero, now()->addDays(30), $member, 'local: Track a clip');
        MemberEntitlements::flush();
    }
}
