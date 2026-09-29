<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local-only sign-ins for eyeballing the /partner console in both lanes.
 *
 *   php artisan db:seed --class=LocalPartnerConsoleSeeder
 *
 * venue lane : partner@haraan.com        / partner1234  (owns the seeded venue)
 * event lane : partner-events@haraan.test / partner1234  (takes over 5 events)
 *
 * Refuses to run outside the local environment — it rewrites passwords.
 */
class LocalPartnerConsoleSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->error('LocalPartnerConsoleSeeder only runs locally.');

            return;
        }

        User::where('email', 'partner@haraan.com')->update(['password' => Hash::make('partner1234')]);

        $host = User::firstOrNew(['email' => 'partner-events@haraan.test']);
        $host->forceFill([
            'name' => 'Nightfall Live',
            'role' => 'PARTNER',
            'partner_type' => 'event',
            'password' => Hash::make('partner1234'),
        ])->save();

        Event::orderBy('id')->limit(5)->update(['partner_id' => $host->id]);
    }
}
