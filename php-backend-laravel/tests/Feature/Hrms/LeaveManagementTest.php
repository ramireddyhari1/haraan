<?php

declare(strict_types=1);

namespace Tests\Feature\Hrms;

use App\Models\Hrms\Department;
use App\Models\Hrms\Designation;
use App\Models\Hrms\EmployeeLeaveBalance;
use App\Models\Hrms\EmployeeLeaveType;
use App\Models\Hrms\EmployeeProfile;
use App\Models\Hrms\HolidayCalendar;
use App\Models\User;
use App\Models\Venue;
use App\Services\Hrms\LeaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

class LeaveManagementTest extends TestCase
{
    use RefreshDatabase;

    private LeaveService $service;
    private User $manager;
    private User $user;
    private Venue $venue;
    private EmployeeProfile $employee;
    private EmployeeLeaveType $casualLeave;
    private EmployeeLeaveType $sickLeave;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LeaveService();

        $this->manager = User::factory()->create([
            'name' => 'Ops Manager',
            'email' => 'ops.manager@haraan.com',
            'role' => 'OPS',
        ]);

        $this->venue = Venue::create([
            'name' => 'Adyar Arena',
            'partner_id' => $this->manager->id,
            'category' => 'TURF',
            'location' => 'Adyar, Chennai',
            'address' => '12 LB Road',
            'city' => 'Chennai',
            'latitude' => 13.0012,
            'longitude' => 80.2565,
            'is_active' => true,
        ]);

        $dept = Department::create(['name' => 'Hospitality', 'code' => 'HSP']);
        $desig = Designation::create(['name' => 'Desk Exec', 'code' => 'DEX', 'department_id' => $dept->id]);

        $this->user = User::factory()->create([
            'name' => 'Leave Applicant',
            'email' => 'applicant@haraan.com',
            'role' => 'EMPLOYEE',
        ]);

        $this->employee = EmployeeProfile::create([
            'user_id' => $this->user->id,
            'employee_code' => 'EMP-LV-001',
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'venue_id' => $this->venue->id,
            'joining_date' => '2025-01-01',
            'employment_type' => 'FULL_TIME',
            'employment_status' => 'ACTIVE',
        ]);

        $this->casualLeave = EmployeeLeaveType::create([
            'name' => 'Casual Leave',
            'code' => 'CL',
            'annual_quota' => 12,
            'is_paid' => true,
        ]);

        $this->sickLeave = EmployeeLeaveType::create([
            'name' => 'Sick Leave',
            'code' => 'SL',
            'annual_quota' => 10,
            'is_paid' => true,
        ]);

        // Allocate 12 days CL for 2026
        EmployeeLeaveBalance::create([
            'employee_profile_id' => $this->employee->id,
            'employee_leave_type_id' => $this->casualLeave->id,
            'year' => 2026,
            'allocated_days' => 12,
            'used_days' => 0,
            'pending_days' => 0,
            'remaining_days' => 12,
        ]);
    }

    public function test_compute_working_days_excludes_public_holidays(): void
    {
        // Add a holiday on 2026-10-02 (Gandhi Jayanti)
        HolidayCalendar::create([
            'title' => 'Gandhi Jayanti',
            'date' => '2026-10-02',
            'applicable_venue_id' => null,
        ]);

        $start = Carbon::parse('2026-10-01');
        $end = Carbon::parse('2026-10-03'); // 3 days total: Oct 1, Oct 2, Oct 3

        $workingDays = $this->service->computeWorkingDays($start, $end, $this->venue->id);

        // 3 calendar days minus 1 holiday = 2 working days
        $this->assertEquals(2.0, $workingDays);
    }

    public function test_apply_leave_reserves_pending_days(): void
    {
        $start = Carbon::parse('2026-09-14');
        $end = Carbon::parse('2026-09-15'); // 2 days

        $request = $this->service->apply(
            $this->employee,
            $this->casualLeave,
            $start,
            $end,
            'Family function'
        );

        $this->assertNotNull($request->id);
        $this->assertEquals('pending', $request->status);
        $this->assertEquals(2.0, $request->total_days);

        $balance = EmployeeLeaveBalance::where('employee_profile_id', $this->employee->id)
            ->where('employee_leave_type_id', $this->casualLeave->id)
            ->where('year', 2026)
            ->first();

        $this->assertEquals(2.0, $balance->pending_days);
        $this->assertEquals(10.0, $balance->remaining_days);
    }

    public function test_insufficient_leave_balance_is_rejected(): void
    {
        $start = Carbon::parse('2026-09-01');
        $end = Carbon::parse('2026-09-20'); // 20 days requested, only 12 available

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient leave balance');

        $this->service->apply(
            $this->employee,
            $this->casualLeave,
            $start,
            $end,
            'Long vacation'
        );
    }

    public function test_approve_leave_deducts_pending_and_increments_used(): void
    {
        $start = Carbon::parse('2026-09-14');
        $end = Carbon::parse('2026-09-15'); // 2 days

        $request = $this->service->apply($this->employee, $this->casualLeave, $start, $end, 'Family trip');

        $this->service->approve($request, $this->manager, 'Approved by manager');

        $request->refresh();
        $this->assertEquals('approved', $request->status);
        $this->assertEquals($this->manager->id, $request->approver_id);
        $this->assertEquals('Approved by manager', $request->approver_notes);

        $balance = EmployeeLeaveBalance::where('employee_profile_id', $this->employee->id)
            ->where('employee_leave_type_id', $this->casualLeave->id)
            ->where('year', 2026)
            ->first();

        $this->assertEquals(0.0, $balance->pending_days);
        $this->assertEquals(2.0, $balance->used_days);
        $this->assertEquals(10.0, $balance->remaining_days);
    }

    public function test_reject_leave_restores_pending_days_to_balance(): void
    {
        $start = Carbon::parse('2026-09-14');
        $end = Carbon::parse('2026-09-15'); // 2 days

        $request = $this->service->apply($this->employee, $this->casualLeave, $start, $end, 'Personal chore');

        $this->service->reject($request, $this->manager, 'High booking volume expected');

        $request->refresh();
        $this->assertEquals('rejected', $request->status);

        $balance = EmployeeLeaveBalance::where('employee_profile_id', $this->employee->id)
            ->where('employee_leave_type_id', $this->casualLeave->id)
            ->where('year', 2026)
            ->first();

        $this->assertEquals(0.0, $balance->pending_days);
        $this->assertEquals(0.0, $balance->used_days);
        $this->assertEquals(12.0, $balance->remaining_days);
    }
}
