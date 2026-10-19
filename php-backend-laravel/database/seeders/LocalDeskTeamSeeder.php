<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\ShiftSession;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local-only desk team, for eyeballing the partner app's Desk team screen with
 * every state on it: someone on shift who has taken money, a manager who was
 * active earlier, an invite nobody has accepted, and a paused login.
 *
 *   php artisan db:seed --class=LocalDeskTeamSeeder
 *
 * owner : desk-owner@haraan.test / deskowner123   (venue lane, owns "Kadapa Arena")
 *
 * Refuses to run outside the local environment.
 */
class LocalDeskTeamSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->error('LocalDeskTeamSeeder only runs locally.');

            return;
        }

        $owner = User::query()->updateOrCreate(['email' => 'desk-owner@haraan.test'], [
            'name' => 'Ravi Kumar',
            'password' => Hash::make('deskowner123'),
            'role' => 'PARTNER',
            'status' => 'ACTIVE',
            'partner_type' => 'venue',
        ]);

        $venue = Venue::query()->where('partner_id', $owner->id)->first() ?? tap(new Venue())->forceFill([
            'name' => 'Kadapa Arena',
            'location' => 'Kadapa',
            'partner_id' => $owner->id,
        ])->save();
        $venue = Venue::query()->where('partner_id', $owner->id)->firstOrFail();
        $second = Venue::query()->where('partner_id', $owner->id)->where('name', 'Riverside Turf')->first();
        if ($second === null) {
            (new Venue())->forceFill(['name' => 'Riverside Turf', 'location' => 'Kadapa', 'partner_id' => $owner->id])->save();
        }

        $make = function (string $email, string $name, array $perms, array $extra = []) use ($owner): User {
            return User::query()->updateOrCreate(['email' => $email], array_merge([
                'name' => $name,
                'password' => Hash::make('deskstaff123'),
                'role' => 'PARTNER',
                'status' => 'ACTIVE',
                'partner_type' => 'venue',
                'parent_partner_id' => $owner->id,
                'staff_permissions' => $perms,
            ], $extra));
        };

        $priya = $make('desk-priya@haraan.test', 'Priya Sharma', ['bookings', 'checkin'], ['last_seen_at' => now()->subMinutes(4)]);
        $arjun = $make('desk-arjun@haraan.test', 'Arjun Reddy', ['bookings', 'checkin', 'pricing', 'reports'], ['last_seen_at' => now()->subHours(3)]);
        $make('desk-sana@haraan.test', 'Sana Begum', ['checkin'], ['last_seen_at' => null]);
        $make('desk-kiran@haraan.test', 'Kiran Naidu', ['reports'], ['status' => 'SUSPENDED', 'last_seen_at' => now()->subDays(6)]);
        $priya->assignedVenues()->sync([$venue->id]);

        if (! ShiftSession::query()->where('user_id', $priya->id)->whereNull('closed_at')->exists()) {
            ShiftSession::query()->forceCreate([
                'venue_id' => $venue->id, 'user_id' => $priya->id, 'opened_by' => $priya->id,
                'opened_at' => now()->subHours(2)->subMinutes(10), 'opening_float' => 500,
            ]);
        }

        if (! BookingPayment::query()->where('collected_by', $priya->id)->whereDate('collected_at', today())->exists()) {
            foreach ([[1200, 'cash'], [800, 'upi'], [1600, 'upi']] as [$amount, $method]) {
                $b = new Booking();
                $b->forceFill([
                    'user_id' => $owner->id, 'venue_id' => $venue->id, 'total_amount' => $amount,
                    'status' => 'CONFIRMED', 'channel' => 'offline', 'quantity' => 1, 'guest_name' => 'Walk-in',
                ])->save();
                BookingPayment::query()->forceCreate([
                    'booking_id' => $b->id, 'amount' => $amount, 'method' => $method,
                    'collected_by' => $priya->id, 'collected_at' => now()->subMinutes(random_int(5, 110)),
                ]);
            }
            $b = new Booking();
            $b->forceFill([
                'user_id' => $owner->id, 'venue_id' => $venue->id, 'total_amount' => 600,
                'status' => 'CONFIRMED', 'channel' => 'offline', 'quantity' => 1, 'guest_name' => 'Walk-in',
            ])->save();
            BookingPayment::query()->forceCreate([
                'booking_id' => $b->id, 'amount' => 600, 'method' => 'cash',
                'collected_by' => $arjun->id, 'collected_at' => now()->subHours(3),
            ]);
        }

        $this->command?->info("Desk team seeded for owner #{$owner->id}.");
    }
}
