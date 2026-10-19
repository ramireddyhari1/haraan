<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AcademyFeePayment;
use App\Models\BatchAttendance;
use App\Models\BatchEnrollment;
use App\Models\CustomerPackage;
use App\Models\PackageRedemption;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBatch;
use App\Models\VenuePackage;
use App\Support\BusinessClock;
use Illuminate\Database\Seeder;

/**
 * Local-only Academy + Packages data for the desk owner from LocalDeskTeamSeeder,
 * with every state on screen: a batch on today, one on other days, a paused one;
 * students overdue, due this week and paid ahead; offers with passes part-used and
 * one about to expire.
 *
 *   php artisan db:seed --class=LocalDeskTeamSeeder
 *   php artisan db:seed --class=LocalAcademyPackagesSeeder
 */
class LocalAcademyPackagesSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->error('LocalAcademyPackagesSeeder only runs locally.');

            return;
        }

        $owner = User::query()->where('email', 'desk-owner@haraan.test')->firstOrFail();
        $venue = Venue::query()->where('partner_id', $owner->id)->orderBy('id')->firstOrFail();
        $today = BusinessClock::todayDate();
        $dow = $today->format('D');
        $notToday = collect(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'])->reject(fn ($d) => $d === $dow)->take(3)->values()->all();

        $batch = fn (array $a) => VenueBatch::query()->updateOrCreate(
            ['partner_id' => $owner->id, 'name' => $a['name']],
            $a + ['partner_id' => $owner->id, 'venue_id' => $venue->id, 'created_at' => $today->copy()->subDays(40)],
        );

        $morning = $batch(['name' => 'Juniors Badminton', 'coach_name' => 'Ravi', 'sport' => 'Badminton', 'days' => null,
            'start_time' => '06:00', 'end_time' => '07:30', 'monthly_fee' => 1500, 'capacity' => 8, 'is_active' => true]);
        $evening = $batch(['name' => 'Evening Cricket Nets', 'coach_name' => 'Suresh', 'sport' => 'Cricket', 'days' => [$dow, ...array_slice($notToday, 0, 2)],
            'start_time' => '17:00', 'end_time' => '18:30', 'monthly_fee' => 2500, 'capacity' => 12, 'is_active' => true]);
        $batch(['name' => 'Weekend Football', 'coach_name' => 'Imran', 'sport' => 'Football', 'days' => $notToday,
            'start_time' => '07:00', 'end_time' => '08:30', 'monthly_fee' => 1800, 'capacity' => 16, 'is_active' => true]);
        $batch(['name' => 'Summer Skating', 'coach_name' => 'Meena', 'sport' => 'Skating', 'days' => ['Sat'],
            'start_time' => '16:00', 'end_time' => '17:00', 'monthly_fee' => 1200, 'capacity' => 10, 'is_active' => false]);

        // Students: [name, phone, paid_until offset days, came today?]
        $kids = [
            [$morning, 'Aarav Reddy', '9000000101', 40, true],
            [$morning, 'Diya Sharma', '9000000102', 3, true],
            [$morning, 'Kabir Naidu', '9000000103', -6, false],
            [$morning, 'Ira Kumar', '9000000104', 18, true],
            [$morning, 'Vihaan Rao', '9000000105', -1, false],
            [$evening, 'Arjun Patel', '9000000201', 25, false],
            [$evening, 'Rohan Verma', '9000000202', 2, false],
            [$evening, 'Sai Teja', '9000000203', 60, false],
        ];
        foreach ($kids as [$b, $name, $phone, $offset, $came]) {
            $e = BatchEnrollment::query()->updateOrCreate(
                ['venue_batch_id' => $b->id, 'student_phone' => $phone],
                ['partner_id' => $owner->id, 'student_name' => $name, 'is_active' => true,
                    'paid_until' => $today->copy()->addDays($offset)->toDateString(), 'amount_paid' => $b->monthly_fee * 2],
            );
            if (! AcademyFeePayment::query()->where('batch_enrollment_id', $e->id)->exists()) {
                foreach ([[2, 'cash', 62], [1, 'upi', 30]] as [$m, $method, $ago]) {
                    AcademyFeePayment::query()->create([
                        'batch_enrollment_id' => $e->id, 'partner_id' => $owner->id, 'venue_id' => $venue->id,
                        'amount' => $b->monthly_fee * $m, 'method' => $method, 'months' => $m,
                        'paid_until' => $today->copy()->addDays($offset)->toDateString(),
                        'collected_by' => $owner->id, 'collected_at' => now()->subDays($ago),
                    ]);
                }
                $e->forceFill(['amount_paid' => (int) AcademyFeePayment::query()->where('batch_enrollment_id', $e->id)->sum('amount')])->save();
            }
            // Past attendance: most sessions, the odd miss; today as given.
            for ($d = 1; $d <= 12; $d++) {
                $date = $today->copy()->subDays($d);
                if ($b->runsOn($date) && (($d + strlen($name)) % 4 !== 0)) {
                    BatchAttendance::query()->firstOrCreate(['batch_enrollment_id' => $e->id, 'date' => $date->toDateString()]);
                }
            }
            if ($came && ! BatchAttendance::query()->where('batch_enrollment_id', $e->id)->whereDate('date', $today->toDateString())->exists()) {
                BatchAttendance::query()->create(['batch_enrollment_id' => $e->id, 'date' => $today->toDateString()]);
            }
        }

        // Packages.
        $ten = VenuePackage::query()->updateOrCreate(['partner_id' => $owner->id, 'name' => '10 Hour Pass'],
            ['price' => 4000, 'sessions' => 10, 'validity_days' => 90, 'is_active' => true, 'venue_id' => null]);
        $five = VenuePackage::query()->updateOrCreate(['partner_id' => $owner->id, 'name' => '5 Morning Slots'],
            ['price' => 1800, 'sessions' => 5, 'validity_days' => 30, 'is_active' => true, 'venue_id' => $venue->id]);
        VenuePackage::query()->updateOrCreate(['partner_id' => $owner->id, 'name' => 'Monsoon 20 Pack'],
            ['price' => 7000, 'sessions' => 20, 'validity_days' => 120, 'is_active' => false, 'venue_id' => null]);

        $holders = [
            [$ten, 'Dev Malhotra', '9000000301', 6, 60, 'upi'],
            [$ten, 'Nisha Iyer', '9000000302', 2, 85, 'cash'],
            [$five, 'Karthik S', '9000000303', 3, 26, 'cash'],
        ];
        foreach ($holders as [$pkg, $name, $phone, $used, $ageDays, $method]) {
            $cp = CustomerPackage::query()->firstOrCreate(
                ['venue_package_id' => $pkg->id, 'customer_phone' => $phone],
                ['partner_id' => $owner->id, 'sold_at_venue_id' => $venue->id, 'customer_name' => $name,
                    'sessions_total' => $pkg->sessions, 'amount_paid' => $pkg->price, 'payment_method' => $method,
                    'collected_by' => $owner->id, 'expires_at' => now()->subDays($ageDays)->addDays((int) $pkg->validity_days)],
            );
            $cp->forceFill(['created_at' => now()->subDays($ageDays)])->save();
            for ($i = $cp->redemptions()->count(); $i < $used; $i++) {
                $r = PackageRedemption::query()->create(['customer_package_id' => $cp->id, 'booking_id' => null, 'redeemed_at_venue_id' => $venue->id]);
                $r->forceFill(['created_at' => now()->subDays(max(1, $ageDays - 4 - $i * 5))])->save();
            }
        }

        $this->command?->info('Academy + packages seeded.');
    }
}
