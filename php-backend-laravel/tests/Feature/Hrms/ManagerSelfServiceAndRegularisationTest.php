<?php

declare(strict_types=1);

namespace Tests\Feature\Hrms;

use App\Filament\Pages\Employee\MyTeamPage;
use App\Models\Hrms\Department;
use App\Models\Hrms\Designation;
use App\Models\Hrms\EmployeeAttendance;
use App\Models\Hrms\EmployeeAttendanceRegularisation;
use App\Models\Hrms\EmployeeLeaveRequest;
use App\Models\Hrms\EmployeeLeaveType;
use App\Models\Hrms\EmployeeProfile;
use App\Models\Hrms\EmployeeShift;
use App\Models\Hrms\EmployeeShiftRoster;
use App\Models\Hrms\EmployeeShiftSwap;
use App\Models\User;
use App\Models\Venue;
use App\Services\Hrms\AttendanceService;
use App\Services\Hrms\LeaveService;
use App\Services\Hrms\RosterService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ManagerSelfServiceAndRegularisationTest extends TestCase
{
    use RefreshDatabase;

    private User $managerUser;
    private EmployeeProfile $managerEmployee;
    private User $workerUser;
    private EmployeeProfile $workerEmployee;
    private Venue $venue;
    private EmployeeShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('employee'));

        $dept = Department::create(['name' => 'Operations', 'code' => 'OPS']);
        $mgrDesig = Designation::create(['name' => 'Shift Supervisor', 'code' => 'SUP', 'department_id' => $dept->id]);
        $workerDesig = Designation::create(['name' => 'Turf Assistant', 'code' => 'AST', 'department_id' => $dept->id]);

        $this->venue = Venue::create([
            'name' => 'Central Turf',
            'category' => 'TURF',
            'location' => 'Central Hub',
            'address' => '100 Stadium Way',
            'city' => 'Chennai',
            'latitude' => 13.0827,
            'longitude' => 80.2707,
            'is_active' => true,
        ]);

        $this->shift = EmployeeShift::create([
            'name' => 'Morning Shift',
            'code' => 'MORN',
            'start_time' => '07:00:00',
            'end_time' => '15:30:00',
            'grace_period_minutes' => 15,
            'half_day_threshold_minutes' => 240,
        ]);

        // Manager user & profile
        $this->managerUser = User::factory()->create([
            'name' => 'Vikram Manager',
            'email' => 'vikram@haraan.com',
            'role' => 'EMPLOYEE',
            'status' => 'ACTIVE',
        ]);

        $this->managerEmployee = EmployeeProfile::create([
            'user_id' => $this->managerUser->id,
            'employee_code' => 'EMP-MGR-001',
            'department_id' => $dept->id,
            'designation_id' => $mgrDesig->id,
            'venue_id' => $this->venue->id,
            'employment_status' => 'active',
            'base_salary' => 50000,
        ]);

        // Worker user & profile (reports to managerUser)
        $this->workerUser = User::factory()->create([
            'name' => 'Arun Worker',
            'email' => 'arun@haraan.com',
            'role' => 'EMPLOYEE',
            'status' => 'ACTIVE',
        ]);

        $this->workerEmployee = EmployeeProfile::create([
            'user_id' => $this->workerUser->id,
            'employee_code' => 'EMP-WRK-002',
            'department_id' => $dept->id,
            'designation_id' => $workerDesig->id,
            'reporting_manager_id' => $this->managerUser->id,
            'venue_id' => $this->venue->id,
            'employment_status' => 'active',
            'base_salary' => 25000,
        ]);
    }

    public function test_employee_can_submit_attendance_regularisation(): void
    {
        $service = app(AttendanceService::class);
        $targetDate = Carbon::yesterday();
        $in = Carbon::parse($targetDate->toDateString() . ' 09:00:00');
        $out = Carbon::parse($targetDate->toDateString() . ' 17:30:00');

        $reg = $service->requestRegularisation(
            $this->workerEmployee,
            $targetDate,
            $in,
            $out,
            'missed_punch',
            'Card reader was offline at gate 2'
        );

        $this->assertDatabaseHas('employee_attendance_regularisations', [
            'id' => $reg->id,
            'employee_profile_id' => $this->workerEmployee->id,
            'status' => 'pending',
            'reason_category' => 'missed_punch',
        ]);

        $this->assertTrue($reg->isPending());
    }

    public function test_manager_can_approve_reportee_regularisation_and_recalculate_attendance(): void
    {
        $service = app(AttendanceService::class);
        $targetDate = Carbon::yesterday();
        $in = Carbon::parse($targetDate->toDateString() . ' 08:30:00');
        $out = Carbon::parse($targetDate->toDateString() . ' 16:30:00');

        $reg = $service->requestRegularisation(
            $this->workerEmployee,
            $targetDate,
            $in,
            $out,
            'gps_drift',
            'GPS inaccuracy due to concrete roof'
        );

        $attendance = $service->approveRegularisation($reg, $this->managerUser, 'Verified with venue log');

        $this->assertEquals('approved', $reg->fresh()->status);
        $this->assertEquals($this->managerUser->id, $reg->fresh()->reviewed_by);
        $this->assertNotNull($reg->fresh()->reviewed_at);

        $this->assertDatabaseHas('employee_attendances', [
            'id' => $attendance->id,
            'employee_profile_id' => $this->workerEmployee->id,
            'status' => 'present',
            'is_verified' => true,
            'total_work_minutes' => 480,
        ]);
    }

    public function test_manager_can_reject_regularisation(): void
    {
        $service = app(AttendanceService::class);
        $targetDate = Carbon::yesterday();
        $in = Carbon::parse($targetDate->toDateString() . ' 08:00:00');
        $out = Carbon::parse($targetDate->toDateString() . ' 16:00:00');

        $reg = $service->requestRegularisation(
            $this->workerEmployee,
            $targetDate,
            $in,
            $out,
            'other',
            'Unjustified claim'
        );

        $service->rejectRegularisation($reg, $this->managerUser, 'No CCTV evidence found');

        $this->assertEquals('rejected', $reg->fresh()->status);
        $this->assertEquals($this->managerUser->id, $reg->fresh()->reviewed_by);
    }

    public function test_manager_can_approve_reportee_leave_via_mss(): void
    {
        $leaveType = EmployeeLeaveType::create([
            'name' => 'Casual Leave',
            'code' => 'CL',
            'annual_quota' => 12,
            'is_paid' => true,
        ]);

        $leaveService = app(LeaveService::class);
        $startDate = Carbon::today()->addDays(2);
        $endDate = Carbon::today()->addDays(3);

        $leaveReq = $leaveService->apply(
            $this->workerEmployee,
            $leaveType,
            $startDate,
            $endDate,
            'Personal family commitment'
        );

        $this->assertEquals('pending', $leaveReq->status);

        $this->actingAs($this->managerUser);

        $page = new MyTeamPage();
        $page->approveLeave($leaveReq->id, 'Approved for family function');

        $this->assertEquals('approved', $leaveReq->fresh()->status);
        $this->assertEquals($this->managerUser->id, $leaveReq->fresh()->approver_id);
    }

    public function test_manager_can_approve_reportee_shift_swap(): void
    {
        $targetWorker = User::factory()->create(['name' => 'Bala Peer', 'email' => 'bala@haraan.com', 'role' => 'EMPLOYEE', 'status' => 'ACTIVE']);
        $targetProfile = EmployeeProfile::create([
            'user_id' => $targetWorker->id,
            'employee_code' => 'EMP-WRK-003',
            'venue_id' => $this->venue->id,
            'employment_status' => 'active',
        ]);

        $tomorrow = Carbon::tomorrow();
        $roster1 = EmployeeShiftRoster::create([
            'employee_profile_id' => $this->workerEmployee->id,
            'employee_shift_id' => $this->shift->id,
            'venue_id' => $this->venue->id,
            'roster_date' => $tomorrow->toDateString(),
            'status' => 'scheduled',
        ]);

        $rosterService = app(RosterService::class);
        $swap = $rosterService->requestSwap($roster1, $targetProfile, 'Medical appointment');

        $this->assertEquals('pending', $swap->status);

        $this->actingAs($this->managerUser);

        $page = new MyTeamPage();
        $page->approveSwap($swap->id);

        $this->assertEquals('approved', $swap->fresh()->status);
        $this->assertEquals($this->managerUser->id, $swap->fresh()->reviewed_by);
        $this->assertEquals('swapped', $roster1->fresh()->status);
        $this->assertEquals($targetProfile->id, $roster1->fresh()->employee_profile_id);
    }

    public function test_my_team_page_accessible_only_to_managers(): void
    {
        // Manager can access
        $this->actingAs($this->managerUser);
        $this->assertTrue(MyTeamPage::canAccess());

        $response = $this->get('/employee/my-team');
        $response->assertOk();

        // Regular worker without reportees cannot access
        $this->actingAs($this->workerUser);
        $this->assertFalse(MyTeamPage::canAccess());

        $response = $this->get('/employee/my-team');
        $response->assertForbidden();
    }
}
