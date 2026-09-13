<?php

declare(strict_types=1);

namespace Tests\Feature\Hrms;

use App\Models\Hrms\Department;
use App\Models\Hrms\Designation;
use App\Models\Hrms\EmployeeAttendance;
use App\Models\Hrms\EmployeeAttendanceRegularisation;
use App\Models\Hrms\EmployeeDelegation;
use App\Models\Hrms\EmployeeLeaveRequest;
use App\Models\Hrms\EmployeeLeaveType;
use App\Models\Hrms\EmployeeProfile;
use App\Models\Hrms\WorkforceAuditLedger;
use App\Models\User;
use App\Models\Venue;
use App\Services\Hrms\ApprovalEngineService;
use App\Services\Hrms\AttendanceService;
use App\Services\Hrms\WorkforceAuditService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class WorkforcePhase2SecurityAndAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $managerUser;
    private EmployeeProfile $managerProfile;
    private User $assistantUser;
    private EmployeeProfile $assistantProfile;
    private User $workerUser;
    private EmployeeProfile $workerProfile;
    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('employee'));

        $dept = Department::create(['name' => 'Operations', 'code' => 'OPS']);
        $mgrDesig = Designation::create(['name' => 'Venue Manager', 'code' => 'VMGR', 'department_id' => $dept->id]);
        $astDesig = Designation::create(['name' => 'Assistant Lead', 'code' => 'ASTL', 'department_id' => $dept->id]);
        $wrkDesig = Designation::create(['name' => 'Ground Crew', 'code' => 'CREW', 'department_id' => $dept->id]);

        $this->venue = Venue::create([
            'name' => 'Marina Sports Complex',
            'category' => 'TURF',
            'location' => 'Marina',
            'city' => 'Chennai',
            'is_active' => true,
        ]);

        // 1. Manager
        $this->managerUser = User::factory()->create([
            'name' => 'Rajesh Manager',
            'email' => 'rajesh.mgr@haraan.com',
            'role' => 'EMPLOYEE',
            'status' => 'ACTIVE',
        ]);
        $this->managerProfile = EmployeeProfile::create([
            'user_id' => $this->managerUser->id,
            'employee_code' => 'EMP-P2-001',
            'department_id' => $dept->id,
            'designation_id' => $mgrDesig->id,
            'venue_id' => $this->venue->id,
            'employment_status' => 'active',
        ]);

        // 2. Assistant Lead (for delegation tests)
        $this->assistantUser = User::factory()->create([
            'name' => 'Karthik Assistant',
            'email' => 'karthik.ast@haraan.com',
            'role' => 'EMPLOYEE',
            'status' => 'ACTIVE',
        ]);
        $this->assistantProfile = EmployeeProfile::create([
            'user_id' => $this->assistantUser->id,
            'employee_code' => 'EMP-P2-002',
            'department_id' => $dept->id,
            'designation_id' => $astDesig->id,
            'venue_id' => $this->venue->id,
            'employment_status' => 'active',
        ]);

        // 3. Worker (reports to Rajesh Manager)
        $this->workerUser = User::factory()->create([
            'name' => 'Deva Worker',
            'email' => 'deva.wrk@haraan.com',
            'role' => 'EMPLOYEE',
            'status' => 'ACTIVE',
        ]);
        $this->workerProfile = EmployeeProfile::create([
            'user_id' => $this->workerUser->id,
            'employee_code' => 'EMP-P2-003',
            'department_id' => $dept->id,
            'designation_id' => $wrkDesig->id,
            'reporting_manager_id' => $this->managerUser->id,
            'venue_id' => $this->venue->id,
            'employment_status' => 'active',
        ]);
    }

    public function test_audit_ledger_creates_cryptographic_hash_chain(): void
    {
        $auditService = app(WorkforceAuditService::class);

        $event1 = $auditService->recordEvent(
            'TEST_EVENT_ONE',
            $this->workerProfile,
            null,
            ['step' => 1],
            $this->managerUser,
            $this->venue->id
        );

        $event2 = $auditService->recordEvent(
            'TEST_EVENT_TWO',
            $this->workerProfile,
            ['step' => 1],
            ['step' => 2],
            $this->managerUser,
            $this->venue->id
        );

        $this->assertEquals(str_repeat('0', 64), $event1->previous_event_hash);
        $this->assertEquals($event1->signature_hash, $event2->previous_event_hash);
        $this->assertNotEmpty($event2->signature_hash);

        // Verify chain integrity passes
        $verification = $auditService->verifyChainIntegrity();
        $this->assertTrue($verification['is_valid']);
        $this->assertGreaterThanOrEqual(2, $verification['verified_count']);
        $this->assertNull($verification['tampered_record_id']);
    }

    public function test_audit_ledger_detects_tampered_record(): void
    {
        $auditService = app(WorkforceAuditService::class);

        $event1 = $auditService->recordEvent('ORIGINAL_PUNCH', $this->workerProfile, null, ['time' => '09:00']);
        $event2 = $auditService->recordEvent('ORIGINAL_LEAVE', $this->workerProfile, null, ['days' => 1.0]);

        // Maliciously tamper with event1 directly in database
        $event1->payload_after = ['time' => '07:00 (FALSIFIED)'];
        $event1->saveQuietly();

        $verification = $auditService->verifyChainIntegrity();
        $this->assertFalse($verification['is_valid']);
        $this->assertEquals($event1->id, $verification['tampered_record_id']);
        $this->assertStringContainsString('Signature mismatch', $verification['reason']);
    }

    public function test_generic_approval_engine_multi_tier_workflow(): void
    {
        $attendanceService = app(AttendanceService::class);
        $approvalEngine = app(ApprovalEngineService::class);

        $targetDate = Carbon::yesterday();
        // 5 hours difference -> triggers Tier 2 approval requirement (> 120 min)
        $in = Carbon::parse($targetDate->toDateString() . ' 08:00:00');
        $out = Carbon::parse($targetDate->toDateString() . ' 13:00:00');

        $reg = $attendanceService->requestRegularisation(
            $this->workerProfile,
            $targetDate,
            $in,
            $out,
            'outdoor_duty',
            'Field prep at tournament grounds'
        );

        $this->assertEquals(2, $reg->approval_tier);
        $this->assertEquals('pending', $reg->status);

        // Tier 1 approval by Shift Manager
        $approvalEngine->processApproval($reg, $this->managerUser, 1, 'Supervisor endorsed');

        $this->assertEquals('tier2_pending', $reg->fresh()->status);
        $this->assertEquals($this->managerUser->id, $reg->fresh()->tier1_approved_by);
        $this->assertNotNull($reg->fresh()->tier1_approved_at);

        // Tier 2 approval by Venue GM / Super Admin
        $superAdmin = User::factory()->create(['role' => 'ADMIN', 'status' => 'ACTIVE']);
        $approvalEngine->processApproval($reg->fresh(), $superAdmin, 2, 'Final GM sign-off');

        $this->assertEquals('approved', $reg->fresh()->status);
        $this->assertEquals($superAdmin->id, $reg->fresh()->tier2_approved_by);
        $this->assertNotNull($reg->fresh()->tier2_approved_at);

        // Downstream attendance record created & verified
        $attendance = EmployeeAttendance::where('employee_profile_id', $this->workerProfile->id)->first();
        $this->assertNotNull($attendance);
        $this->assertEquals($targetDate->toDateString(), Carbon::parse($attendance->date)->toDateString());
        $this->assertTrue((bool) $attendance->is_verified);
        $this->assertEquals(300, $attendance->total_work_minutes);
        $this->assertEquals('present', $attendance->status);
    }

    public function test_delegation_guardrails_prevent_self_and_circular_delegations(): void
    {
        // 1. Self delegation fails
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Self-delegation is strictly prohibited.');

        EmployeeDelegation::validateGuardrails(
            $this->managerUser->id,
            $this->managerUser->id,
            Carbon::today()->toDateString(),
            Carbon::tomorrow()->toDateString()
        );
    }

    public function test_delegation_allows_acting_delegate_to_approve_subordinate(): void
    {
        // Manager Rajesh delegates authority to Assistant Karthik
        EmployeeDelegation::create([
            'delegator_id' => $this->managerUser->id,
            'delegatee_id' => $this->assistantUser->id,
            'start_date' => Carbon::yesterday()->toDateString(),
            'end_date' => Carbon::tomorrow()->toDateString(),
            'scope' => 'all',
            'max_approval_tier' => 1,
            'is_active' => true,
            'reason' => 'Annual leave coverage',
        ]);

        $leaveType = EmployeeLeaveType::create([
            'name' => 'Casual Leave',
            'code' => 'CL-P2',
            'annual_quota' => 12,
            'is_paid' => true,
        ]);

        $leaveReq = EmployeeLeaveRequest::create([
            'employee_profile_id' => $this->workerProfile->id,
            'employee_leave_type_id' => $leaveType->id,
            'start_date' => Carbon::today()->addDays(2)->toDateString(),
            'end_date' => Carbon::today()->addDays(3)->toDateString(),
            'total_days' => 1.0,
            'reason' => 'Family visit',
            'approval_tier' => 1,
            'status' => 'pending',
        ]);

        $approvalEngine = app(ApprovalEngineService::class);

        // Assistant Karthik has delegated authority
        $this->assertTrue($approvalEngine->canUserApprove($this->assistantUser, $leaveReq, 1));

        $approvalEngine->processApproval($leaveReq, $this->assistantUser, 1, 'Approved via delegation');
        $this->assertEquals('approved', $leaveReq->fresh()->status);
    }

    public function test_abac_policy_prevents_unauthorized_peer_approvals(): void
    {
        $peerUser = User::factory()->create(['name' => 'Peer Worker', 'email' => 'peer@haraan.com', 'role' => 'EMPLOYEE']);
        $peerProfile = EmployeeProfile::create([
            'user_id' => $peerUser->id,
            'employee_code' => 'EMP-PEER-001',
            'venue_id' => $this->venue->id,
            'employment_status' => 'active',
        ]);

        $reg = EmployeeAttendanceRegularisation::create([
            'employee_profile_id' => $this->workerProfile->id,
            'date' => Carbon::yesterday()->toDateString(),
            'requested_clock_in_at' => Carbon::yesterday()->setTime(9, 0),
            'requested_clock_out_at' => Carbon::yesterday()->setTime(17, 0),
            'reason_category' => 'missed_punch',
            'reason' => 'Missed punch at gate',
            'approval_tier' => 1,
            'status' => 'pending',
        ]);

        // Peer worker CANNOT approve
        $this->actingAs($peerUser);
        $this->assertFalse($peerUser->can('approve', $reg));

        // Direct manager CAN approve
        $this->actingAs($this->managerUser);
        $this->assertTrue($this->managerUser->can('approve', $reg));
    }

    public function test_escalate_command_flags_stale_approvals(): void
    {
        // Create an old pending regularisation older than 36 hours
        $oldReg = new EmployeeAttendanceRegularisation([
            'employee_profile_id' => $this->workerProfile->id,
            'date' => Carbon::now()->subDays(3)->toDateString(),
            'requested_clock_in_at' => Carbon::now()->subDays(3)->setTime(9, 0),
            'requested_clock_out_at' => Carbon::now()->subDays(3)->setTime(17, 0),
            'reason_category' => 'missed_punch',
            'reason' => 'Stale unreviewed dispute',
            'approval_tier' => 1,
            'status' => 'pending',
        ]);
        $oldReg->timestamps = false;
        $oldReg->created_at = Carbon::now()->subHours(36);
        $oldReg->updated_at = Carbon::now()->subHours(36);
        $oldReg->save();

        $this->artisan('workforce:escalate-pending-approvals', ['--hours' => 24])
            ->expectsOutputToContain('Scanning workforce requests pending over 24 hours')
            ->expectsOutputToContain('Successfully escalated 1 request(s)')
            ->assertExitCode(0);

        $this->assertEquals('escalated', $oldReg->fresh()->status);
        $this->assertNotNull($oldReg->fresh()->escalated_at);
        $this->assertStringContainsString('SLA_BREACH_AUTOMATIC_ESCALATION', $oldReg->fresh()->escalation_reason);

        // Verify audit log recorded
        $this->assertDatabaseHas('workforce_audit_ledger', [
            'entity_type' => EmployeeAttendanceRegularisation::class,
            'entity_id' => $oldReg->id,
            'event_name' => 'APPROVAL_SLA_BREACH_ESCALATED',
        ]);
    }
}
