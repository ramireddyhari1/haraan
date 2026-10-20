<?php

declare(strict_types=1);

namespace Tests\Feature\Hrms;

use App\Models\Hrms\EmployeeProfile;
use App\Models\Hrms\EmployeeShift;
use App\Models\Hrms\EmployeeShiftRoster;
use App\Models\User;
use App\Services\Hrms\AttendanceService;
use App\Support\BusinessClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Attendance runs on the venue's day and clock (IST), while instants stay UTC. The app
 * clock is UTC, so before this a 05:00 IST clock-in landed on yesterday, a 09:00 shift
 * started at 14:30 IST, and a night shift could not clock out after midnight.
 */
class AttendanceBusinessDayTest extends TestCase
{
    use RefreshDatabase;

    private AttendanceService $service;

    private EmployeeProfile $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AttendanceService();

        $user = User::factory()->create(['name' => 'Night Worker', 'email' => 'night@haraan.test', 'role' => 'EMPLOYEE']);
        $this->employee = EmployeeProfile::create([
            'user_id' => $user->id,
            'employee_code' => 'EMP-IST-1',
            'joining_date' => '2025-01-01',
            'employment_type' => 'FULL_TIME',
            'employment_status' => 'ACTIVE',
            'base_salary' => 20000,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Pin "now" to a wall-clock time in India. */
    private function atIst(string $local): void
    {
        Carbon::setTestNow(Carbon::parse($local, BusinessClock::zone())->utc());
    }

    private function rosterOn(string $date, string $start, int $grace = 10): void
    {
        $shift = EmployeeShift::create([
            'name' => 'Shift ' . $start, 'code' => 'S' . str_replace(':', '', $start), 'start_time' => $start,
            'end_time' => '23:00:00', 'grace_period_minutes' => $grace, 'half_day_threshold_minutes' => 240, 'is_active' => true,
        ]);
        EmployeeShiftRoster::create([
            'employee_profile_id' => $this->employee->id, 'employee_shift_id' => $shift->id,
            'roster_date' => $date, 'status' => 'scheduled',
        ]);
    }

    public function test_an_early_clock_in_is_dated_on_the_ist_day(): void
    {
        // 05:00 IST on the 6th is 23:30 UTC on the 5th.
        $this->atIst('2026-09-06 05:00:00');

        $attendance = $this->service->clockIn($this->employee, 0.0, 0.0);

        $this->assertSame('2026-09-06', $attendance->date->toDateString());
        $this->assertSame('2026-09-05 23:30:00', $attendance->clock_in_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_lateness_is_judged_against_the_shift_start_in_ist(): void
    {
        $this->rosterOn('2026-09-06', '09:00:00', grace: 10);

        // 09:30 IST is 20 minutes past the grace window. Read as UTC, the shift would have
        // started at 14:30 IST and this would have been "present".
        $this->atIst('2026-09-06 09:30:00');

        $this->assertSame('late', $this->service->clockIn($this->employee, 0.0, 0.0)->status);
    }

    public function test_on_time_within_grace_is_present(): void
    {
        $this->rosterOn('2026-09-06', '09:00:00', grace: 10);
        $this->atIst('2026-09-06 09:05:00');

        $this->assertSame('present', $this->service->clockIn($this->employee, 0.0, 0.0)->status);
    }

    public function test_a_night_shift_clocks_out_after_midnight(): void
    {
        // 23:00 → 06:00 IST crosses midnight in both zones (17:30 → 00:30 UTC).
        $this->atIst('2026-09-06 23:00:00');
        $in = $this->service->clockIn($this->employee, 0.0, 0.0);

        $this->atIst('2026-09-07 06:00:00');
        $out = $this->service->clockOut($this->employee, 0.0, 0.0);

        $this->assertSame($in->id, $out->id);
        $this->assertSame('2026-09-06', $out->date->toDateString());
        $this->assertSame(420, $out->total_work_minutes);
    }

    public function test_the_today_relation_follows_the_ist_day(): void
    {
        $this->atIst('2026-09-06 05:00:00');
        $this->service->clockIn($this->employee, 0.0, 0.0);

        $this->assertNotNull($this->employee->fresh()->todayAttendance);
    }

    public function test_a_regularisation_typed_in_ist_is_stored_as_the_right_instant(): void
    {
        $this->atIst('2026-09-07 12:00:00');
        $reg = $this->service->requestRegularisation(
            $this->employee,
            Carbon::parse('2026-09-06'),
            Carbon::parse('2026-09-06 09:00', BusinessClock::zone())->utc(),
            Carbon::parse('2026-09-06 18:00', BusinessClock::zone())->utc(),
            'forgot_punch',
            'Phone died',
        );

        $this->assertSame('03:30', $reg->requested_clock_in_at->utc()->format('H:i'));
        $this->assertSame('09:00 AM', BusinessClock::local($reg->requested_clock_in_at)->format('h:i A'));
    }
}
