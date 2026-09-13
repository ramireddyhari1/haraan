<?php

declare(strict_types=1);

namespace Tests\Feature\Hrms;

use App\Models\Hrms\Department;
use App\Models\Hrms\Designation;
use App\Models\Hrms\EmployeeProfile;
use App\Models\Hrms\EmployeeShift;
use App\Models\Hrms\EmployeeShiftRoster;
use App\Models\User;
use App\Models\Venue;
use App\Services\Hrms\RosterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

class RosterAndShiftConflictTest extends TestCase
{
    use RefreshDatabase;

    private RosterService $service;
    private User $partner;
    private Venue $venue;
    private EmployeeProfile $emp1;
    private EmployeeProfile $emp2;
    private EmployeeShift $morningShift;
    private EmployeeShift $eveningShift;
    private EmployeeShift $nightShift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new RosterService();

        $this->partner = User::factory()->create([
            'name' => 'Partner Lead',
            'email' => 'lead.partner@haraan.com',
            'role' => 'PARTNER',
            'partner_type' => 'VENUE',
        ]);

        $this->venue = Venue::create([
            'name' => 'Turf Arena',
            'partner_id' => $this->partner->id,
            'category' => 'TURF',
            'location' => 'Velachery, Chennai',
            'address' => '100 100 Feet Road',
            'city' => 'Chennai',
            'latitude' => 12.9800,
            'longitude' => 80.2200,
            'is_active' => true,
        ]);

        $dept = Department::create(['name' => 'Operations', 'code' => 'OPS']);
        $desig = Designation::create(['name' => 'Field Crew', 'code' => 'FCW', 'department_id' => $dept->id]);

        $user1 = User::factory()->create(['name' => 'Emp One', 'email' => 'emp1@haraan.com', 'role' => 'EMPLOYEE']);
        $this->emp1 = EmployeeProfile::create([
            'user_id' => $user1->id,
            'employee_code' => 'EMP-001',
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'venue_id' => $this->venue->id,
            'joining_date' => '2025-01-01',
            'employment_type' => 'FULL_TIME',
            'employment_status' => 'ACTIVE',
        ]);

        $user2 = User::factory()->create(['name' => 'Emp Two', 'email' => 'emp2@haraan.com', 'role' => 'EMPLOYEE']);
        $this->emp2 = EmployeeProfile::create([
            'user_id' => $user2->id,
            'employee_code' => 'EMP-002',
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'venue_id' => $this->venue->id,
            'joining_date' => '2025-01-01',
            'employment_type' => 'FULL_TIME',
            'employment_status' => 'ACTIVE',
        ]);

        $this->morningShift = EmployeeShift::create([
            'name' => 'Morning',
            'code' => 'MORN',
            'start_time' => '07:00:00',
            'end_time' => '15:30:00',
            'grace_period_minutes' => 15,
            'half_day_threshold_minutes' => 240,
            'is_night_shift' => false,
            'is_active' => true,
        ]);

        $this->eveningShift = EmployeeShift::create([
            'name' => 'Evening',
            'code' => 'EVE',
            'start_time' => '15:00:00',
            'end_time' => '23:30:00',
            'grace_period_minutes' => 15,
            'half_day_threshold_minutes' => 240,
            'is_night_shift' => false,
            'is_active' => true,
        ]);

        $this->nightShift = EmployeeShift::create([
            'name' => 'Night',
            'code' => 'NIGHT',
            'start_time' => '23:00:00',
            'end_time' => '07:30:00',
            'grace_period_minutes' => 15,
            'half_day_threshold_minutes' => 240,
            'is_night_shift' => true,
            'is_active' => true,
        ]);
    }

    public function test_assign_shift_succeeds_for_available_date(): void
    {
        $date = Carbon::parse('2026-09-10');
        $roster = $this->service->assignShift($this->emp1, $this->morningShift, $date, $this->venue);

        $this->assertNotNull($roster->id);
        $this->assertEquals($this->emp1->id, $roster->employee_profile_id);
        $this->assertEquals($this->morningShift->id, $roster->employee_shift_id);
        $this->assertEquals('2026-09-10', $roster->roster_date->toDateString());
        $this->assertEquals('scheduled', $roster->status);
    }

    public function test_assign_shift_rejects_duplicate_shift_on_same_date(): void
    {
        $date = Carbon::parse('2026-09-10');
        $this->service->assignShift($this->emp1, $this->morningShift, $date);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already scheduled for a shift on 2026-09-10');

        $this->service->assignShift($this->emp1, $this->eveningShift, $date);
    }

    public function test_insufficient_rest_interval_is_prevented(): void
    {
        // Day 1: Night Shift (23:00 to 07:30 next morning)
        $day1 = Carbon::parse('2026-09-10');
        $this->service->assignShift($this->emp1, $this->nightShift, $day1);

        // Day 2: Morning Shift (07:00 to 15:30) - zero/negative rest!
        $day2 = Carbon::parse('2026-09-11');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient rest interval');

        $this->service->assignShift($this->emp1, $this->morningShift, $day2);
    }

    public function test_request_swap_rejects_swap_with_oneself(): void
    {
        $date = Carbon::parse('2026-09-10');
        $roster = $this->service->assignShift($this->emp1, $this->morningShift, $date);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot request shift swap with yourself');

        $this->service->requestSwap($roster, $this->emp1, 'Need personal leave');
    }

    public function test_bilateral_shift_swap_approval_exchanges_assignments(): void
    {
        $date1 = Carbon::parse('2026-09-10');
        $date2 = Carbon::parse('2026-09-11');

        $roster1 = $this->service->assignShift($this->emp1, $this->morningShift, $date1);
        $roster2 = $this->service->assignShift($this->emp2, $this->eveningShift, $date2);

        // emp1 requests swap with emp2
        $swap = $this->service->requestSwap($roster1, $this->emp2, 'Exchange weekend shifts', $roster2);

        $this->assertEquals('pending', $swap->status);

        // Partner approves swap
        $this->service->approveSwap($swap, $this->partner);

        $swap->refresh();
        $roster1->refresh();
        $roster2->refresh();

        $this->assertEquals('approved', $swap->status);
        $this->assertEquals($this->partner->id, $swap->reviewed_by);

        // Verify bilateral exchange of profile IDs
        $this->assertEquals($this->emp2->id, $roster1->employee_profile_id);
        $this->assertEquals($this->emp1->id, $roster2->employee_profile_id);
        $this->assertEquals('swapped', $roster1->status);
        $this->assertEquals('swapped', $roster2->status);
    }

    public function test_reject_swap_marks_rejected_and_stores_reviewer(): void
    {
        $date = Carbon::parse('2026-09-10');
        $roster = $this->service->assignShift($this->emp1, $this->morningShift, $date);

        $swap = $this->service->requestSwap($roster, $this->emp2, 'Personal emergency');

        $this->service->rejectSwap($swap, $this->partner, 'Understaffed on morning shift');

        $swap->refresh();
        $this->assertEquals('rejected', $swap->status);
        $this->assertEquals($this->partner->id, $swap->reviewed_by);
        $this->assertStringContainsString('Understaffed on morning shift', $swap->reason);
    }
}
