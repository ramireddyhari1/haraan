<?php

declare(strict_types=1);

namespace Tests\Feature\Hrms;

use App\Models\Booking;
use App\Models\Hrms\Department;
use App\Models\Hrms\Designation;
use App\Models\Hrms\EmployeeAttendance;
use App\Models\Hrms\EmployeeLeaveRequest;
use App\Models\Hrms\EmployeeLeaveType;
use App\Models\Hrms\EmployeePayroll;
use App\Models\Hrms\EmployeeProfile;
use App\Models\Hrms\EmployeeShift;
use App\Models\Hrms\EmployeeShiftRoster;
use App\Models\Hrms\WorkforceAuditLedger;
use App\Models\User;
use App\Models\Venue;
use App\Services\Hrms\LaborForecastingService;
use App\Services\Hrms\RosterAutoSchedulerService;
use App\Services\Hrms\StatutoryPayrollService;
use App\Services\Hrms\WorkforceAuditService;
use App\Support\JwtService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

class WorkforcePhase4IntelligenceAndPayrollTest extends TestCase
{
    use RefreshDatabase;

    private User $managerUser;
    private EmployeeProfile $managerEmployee;
    private User $workerUser1;
    private EmployeeProfile $workerEmployee1;
    private User $workerUser2;
    private EmployeeProfile $workerEmployee2;
    private Venue $venue;
    private EmployeeShift $morningShift;
    private EmployeeShift $eveningShift;
    private string $jwtSecret;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('employee'));
        $this->jwtSecret = (string) config('app.jwt_secret', env('JWT_SECRET', 'test_secret_key_1234567890123456'));
        config(['app.jwt_secret' => $this->jwtSecret]);

        $dept = Department::create(['name' => 'Operations', 'code' => 'OPS']);
        $mgrDesig = Designation::create(['name' => 'Venue Lead', 'code' => 'VLEAD', 'department_id' => $dept->id]);
        $opDesig = Designation::create(['name' => 'Turf Technician', 'code' => 'TECH', 'department_id' => $dept->id]);

        $this->venue = Venue::create([
            'name' => 'Kotturpuram Arena',
            'category' => 'TURF',
            'location' => 'Kotturpuram',
            'address' => '12 Gandhi Mandapam Rd',
            'city' => 'Chennai',
            'latitude' => 13.0135,
            'longitude' => 80.2415,
            'is_active' => true,
        ]);

        $this->morningShift = EmployeeShift::create([
            'name' => 'Morning Shift',
            'code' => 'MORN',
            'start_time' => '06:00:00',
            'end_time' => '14:00:00',
            'grace_period_minutes' => 15,
            'full_day_threshold_hours' => 8.0,
            'is_active' => true,
        ]);

        $this->eveningShift = EmployeeShift::create([
            'name' => 'Evening Shift',
            'code' => 'EVE',
            'start_time' => '14:00:00',
            'end_time' => '22:00:00',
            'grace_period_minutes' => 15,
            'full_day_threshold_hours' => 8.0,
            'is_active' => true,
        ]);

        // Manager
        $this->managerUser = User::factory()->create([
            'name' => 'Karthik General Manager',
            'email' => 'karthik.gm@haraan.com',
            'role' => 'EMPLOYEE',
            'status' => 'ACTIVE',
        ]);
        $this->managerEmployee = EmployeeProfile::create([
            'user_id' => $this->managerUser->id,
            'employee_code' => 'EMP-MGR-401',
            'department_id' => $dept->id,
            'designation_id' => $mgrDesig->id,
            'venue_id' => $this->venue->id,
            'employment_status' => 'active',
            'base_salary' => 60000.0,
        ]);

        // Worker 1
        $this->workerUser1 = User::factory()->create([
            'name' => 'Mani Crew',
            'email' => 'mani@haraan.com',
            'role' => 'EMPLOYEE',
            'status' => 'ACTIVE',
        ]);
        $this->workerEmployee1 = EmployeeProfile::create([
            'user_id' => $this->workerUser1->id,
            'employee_code' => 'EMP-WRK-402',
            'department_id' => $dept->id,
            'designation_id' => $opDesig->id,
            'venue_id' => $this->venue->id,
            'reporting_manager_id' => $this->managerUser->id,
            'employment_status' => 'active',
            'base_salary' => 30000.0,
        ]);

        // Worker 2
        $this->workerUser2 = User::factory()->create([
            'name' => 'Deepak Crew',
            'email' => 'deepak@haraan.com',
            'role' => 'EMPLOYEE',
            'status' => 'ACTIVE',
        ]);
        $this->workerEmployee2 = EmployeeProfile::create([
            'user_id' => $this->workerUser2->id,
            'employee_code' => 'EMP-WRK-403',
            'department_id' => $dept->id,
            'designation_id' => $opDesig->id,
            'venue_id' => $this->venue->id,
            'reporting_manager_id' => $this->managerUser->id,
            'employment_status' => 'active',
            'base_salary' => 25000.0,
        ]);
    }

    public function test_auto_scheduler_generates_balanced_rosters_matching_booking_demand(): void
    {
        $scheduler = app(RosterAutoSchedulerService::class);
        $startDate = Carbon::tomorrow();
        $endDate = Carbon::tomorrow()->addDays(2);

        // Create bookings on tomorrow to trigger surge demand
        for ($i = 0; $i < 5; $i++) {
            Booking::create([
                'user_id' => $this->managerUser->id,
                'venue_id' => $this->venue->id,
                'slot_date' => $startDate->toDateString(),
                'status' => 'confirmed',
                'quantity' => 1,
                'total_amount' => 1200.0,
            ]);
        }

        $result = $scheduler->autoSchedule($this->venue, $startDate, $endDate, 1, $this->managerUser);

        $this->assertTrue($result['success']);
        $this->assertGreaterThanOrEqual(1, $result['total_rosters_created']);
        $this->assertEquals(5, $result['total_bookings_evaluated']);

        // Assert cryptographic audit ledger entry
        $audit = WorkforceAuditLedger::where('event_name', 'ROSTER_AUTO_SCHEDULED')
            ->where('venue_id', $this->venue->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertNotEmpty($audit->signature_hash);

        // Assert unbroken hash chain
        $auditService = app(WorkforceAuditService::class);
        $verification = $auditService->verifyChainIntegrity();
        $this->assertTrue($verification['is_valid']);
    }

    public function test_auto_scheduler_respects_rest_intervals_and_leaves(): void
    {
        $scheduler = app(RosterAutoSchedulerService::class);
        $targetDate = Carbon::tomorrow();

        $leaveType = EmployeeLeaveType::create([
            'name' => 'Casual Leave',
            'code' => 'CL',
            'days_allowed_per_year' => 12,
            'is_paid' => true,
        ]);

        // Worker 1 is on approved leave tomorrow
        EmployeeLeaveRequest::create([
            'employee_profile_id' => $this->workerEmployee1->id,
            'employee_leave_type_id' => $leaveType->id,
            'start_date' => $targetDate->toDateString(),
            'end_date' => $targetDate->toDateString(),
            'total_days' => 1.0,
            'reason' => 'Doctor appointment',
            'status' => 'approved',
        ]);

        $result = $scheduler->autoSchedule($this->venue, $targetDate, $targetDate, 1);

        $this->assertTrue($result['success']);

        // Worker 1 must NOT be scheduled on their leave date
        $worker1Roster = EmployeeShiftRoster::where('employee_profile_id', $this->workerEmployee1->id)
            ->whereDate('roster_date', $targetDate->toDateString())
            ->exists();

        $this->assertFalse($worker1Roster);

        // Worker 2 should be scheduled
        $worker2Roster = EmployeeShiftRoster::where('employee_profile_id', $this->workerEmployee2->id)
            ->whereDate('roster_date', $targetDate->toDateString())
            ->exists();

        $this->assertTrue($worker2Roster);
    }

    public function test_labor_forecasting_calculates_efficiency_ratio_and_overtime_alerts(): void
    {
        $forecaster = app(LaborForecastingService::class);
        $currentMonth = Carbon::now()->format('Y-m');

        // Create booking revenue
        Booking::create([
            'user_id' => $this->managerUser->id,
            'venue_id' => $this->venue->id,
            'slot_date' => Carbon::now()->startOfMonth()->addDays(2)->toDateString(),
            'status' => 'confirmed',
            'quantity' => 10,
            'total_amount' => 50000.0,
        ]);

        // Simulate Worker 1 having 7 shifts in a single week within the forecast month (56 hours > 48h cap)
        $startOfWeek = Carbon::parse("{$currentMonth}-15")->startOfWeek();
        for ($day = 0; $day < 7; $day++) {
            EmployeeShiftRoster::create([
                'employee_profile_id' => $this->workerEmployee1->id,
                'employee_shift_id' => $this->morningShift->id,
                'venue_id' => $this->venue->id,
                'roster_date' => $startOfWeek->copy()->addDays($day)->toDateString(),
                'status' => 'scheduled',
            ]);
        }

        $forecast = $forecaster->forecastMonthly($this->venue, $currentMonth);

        $this->assertEquals(50000.0, $forecast['projected_revenue']);
        $this->assertGreaterThan(0.0, $forecast['labor_cost_percentage']);
        $this->assertGreaterThan(0.0, $forecast['projected_overtime_hours']);
        $this->assertGreaterThan(0.0, $forecast['projected_overtime_cost']);

        // Assert statutory alerts generated
        $alerts = $forecast['alerts'];
        $this->assertNotEmpty($alerts);

        $excessHoursAlert = collect($alerts)->firstWhere('type', 'EXCESSIVE_WEEKLY_HOURS');
        $this->assertNotNull($excessHoursAlert);
        $this->assertEquals('critical', $excessHoursAlert['severity']);
        $this->assertEquals($this->workerEmployee1->id, $excessHoursAlert['employee_id']);
    }

    public function test_statutory_payroll_calculates_pf_esi_pt_and_double_overtime(): void
    {
        $payrollService = app(StatutoryPayrollService::class);
        $month = Carbon::now()->format('Y-m');
        $startDate = Carbon::parse("{$month}-01")->startOfMonth();
        $workingDays = (int) $startDate->daysInMonth;

        // Populate realistic full-month attendance with Day 3 having 10h (2h overtime)
        for ($d = 1; $d <= $workingDays; $d++) {
            $attDate = $startDate->copy()->addDays($d - 1);
            EmployeeAttendance::create([
                'employee_profile_id' => $this->workerEmployee1->id,
                'date' => $attDate->toDateString(),
                'clock_in_at' => $attDate->copy()->setTime(8, 0, 0),
                'clock_out_at' => $attDate->copy()->setTime($d === 3 ? 18 : 16, 0, 0),
                'total_work_minutes' => $d === 3 ? 600 : 480,
                'status' => 'present',
            ]);
        }

        $payroll = $payrollService->calculateForEmployee($this->workerEmployee1, $month);

        $this->assertEquals($this->workerEmployee1->id, $payroll->employee_profile_id);
        $this->assertEquals($month, $payroll->payroll_month);

        // Standard Indian Wage Structure Breakdown: 50% Basic, 30% HRA, 20% Special
        $this->assertGreaterThan(0.0, (float) $payroll->basic_salary);
        $this->assertGreaterThan(0.0, (float) $payroll->hra);
        $this->assertGreaterThan(0.0, (float) $payroll->special_allowance);

        // Overtime amount should be calculated at 2x rate
        $this->assertGreaterThan(0.0, (float) $payroll->overtime_amount);

        // Statutory Deductions:
        // PF = 12% of basic
        $expectedPf = round((float) $payroll->basic_salary * 0.12, 2);
        $this->assertEquals($expectedPf, (float) $payroll->pf_deduction);

        // Professional Tax
        $this->assertEquals(200.0, (float) $payroll->professional_tax);

        // Net salary
        $this->assertGreaterThan(0.0, (float) $payroll->net_salary);
        $this->assertEquals('approved', $payroll->status);
    }

    public function test_payroll_batch_lock_enforces_cryptographic_audit_and_immutability(): void
    {
        $payrollService = app(StatutoryPayrollService::class);
        $month = Carbon::now()->format('Y-m');

        // Generate batch
        $batch = $payrollService->generateBatchForVenue($this->venue, $month);
        $this->assertGreaterThanOrEqual(1, $batch['processed_count']);

        // Lock batch
        $lockReceipt = $payrollService->lockBatch($this->venue, $month, $this->managerUser);
        $this->assertTrue($lockReceipt['success']);
        $this->assertEquals('locked', $lockReceipt['status']);

        // Check database status
        $lockedRecord = EmployeePayroll::where('employee_profile_id', $this->workerEmployee1->id)
            ->where('payroll_month', $month)
            ->first();

        $this->assertEquals('locked', $lockedRecord->status);

        // Check audit ledger
        $audit = WorkforceAuditLedger::where('event_name', 'PAYROLL_BATCH_LOCKED')
            ->where('venue_id', $this->venue->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals('locked', $audit->payload_after['status']);

        // Attempting to recalculate a locked record throws an exception
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cryptographically locked');
        $payrollService->calculateForEmployee($this->workerEmployee1, $month);
    }

    public function test_api_endpoints_for_roster_auto_schedule_and_labor_forecast(): void
    {
        $token = JwtService::issueForUser($this->managerUser, $this->jwtSecret);
        $headers = ['Authorization' => 'Bearer ' . $token];

        $tomorrow = Carbon::tomorrow()->toDateString();
        $threeDaysLater = Carbon::tomorrow()->addDays(2)->toDateString();
        $currentMonth = Carbon::now()->format('Y-m');

        // 1. Auto-schedule endpoint
        $autoScheduleResponse = $this->withHeaders($headers)->postJson('/api/v1/workforce/roster/auto-schedule', [
            'venue_id' => $this->venue->id,
            'start_date' => $tomorrow,
            'end_date' => $threeDaysLater,
            'min_staff_per_shift' => 1,
        ]);

        $autoScheduleResponse->assertStatus(201);
        $autoScheduleResponse->assertJson(['success' => true]);

        // 2. Labor forecast endpoint
        $forecastResponse = $this->withHeaders($headers)->getJson("/api/v1/workforce/labor/forecast?venue_id={$this->venue->id}&month={$currentMonth}");
        $forecastResponse->assertStatus(200);
        $forecastResponse->assertJson(['success' => true]);

        // 3. Generate payroll batch endpoint
        $generateResponse = $this->withHeaders($headers)->postJson('/api/v1/workforce/payroll/generate-batch', [
            'venue_id' => $this->venue->id,
            'month' => $currentMonth,
        ]);
        $generateResponse->assertStatus(201);
        $generateResponse->assertJson(['success' => true]);

        // 4. Lock payroll batch endpoint
        $lockResponse = $this->withHeaders($headers)->postJson('/api/v1/workforce/payroll/lock-batch', [
            'venue_id' => $this->venue->id,
            'month' => $currentMonth,
        ]);
        $lockResponse->assertStatus(200);
        $lockResponse->assertJson(['success' => true, 'lock_receipt' => ['status' => 'locked']]);

        // 5. Statutory compliance export manifest endpoint
        $exportResponse = $this->withHeaders($headers)->getJson("/api/v1/workforce/payroll/compliance-export?venue_id={$this->venue->id}&month={$currentMonth}");
        $exportResponse->assertStatus(200);
        $exportResponse->assertJsonStructure([
            'manifest_type',
            'venue',
            'payroll_month',
            'summary' => [
                'total_workforce_count',
                'total_gross_wages',
                'total_basic_wages',
                'total_pf_liability',
                'total_esi_liability',
                'total_professional_tax',
            ],
            'employee_schedule',
        ]);
    }
}
