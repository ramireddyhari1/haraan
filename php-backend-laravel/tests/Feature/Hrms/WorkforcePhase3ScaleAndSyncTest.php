<?php

declare(strict_types=1);

namespace Tests\Feature\Hrms;

use App\Filament\Pages\Employee\MyTeamPage;
use App\Models\Hrms\Department;
use App\Models\Hrms\Designation;
use App\Models\Hrms\EmployeeAttendance;
use App\Models\Hrms\EmployeeProfile;
use App\Models\Hrms\EmployeeShift;
use App\Models\Hrms\EmployeeShiftRoster;
use App\Models\Hrms\EmployeeTask;
use App\Models\Hrms\WorkforceAuditLedger;
use App\Models\User;
use App\Models\Venue;
use App\Services\Hrms\PunchIngestionService;
use App\Services\Hrms\WorkforceAuditService;
use App\Support\JwtService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WorkforcePhase3ScaleAndSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $managerUser;
    private EmployeeProfile $managerEmployee;
    private User $workerUser;
    private EmployeeProfile $workerEmployee;
    private Venue $venue;
    private EmployeeShift $shift;
    private string $jwtSecret;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('employee'));
        $this->jwtSecret = (string) config('app.jwt_secret', env('JWT_SECRET', 'test_secret_key_1234567890123456'));
        config(['app.jwt_secret' => $this->jwtSecret]);

        $dept = Department::create(['name' => 'Operations', 'code' => 'OPS']);
        $mgrDesig = Designation::create(['name' => 'Venue Operations Lead', 'code' => 'VOL', 'department_id' => $dept->id]);
        $wrkDesig = Designation::create(['name' => 'Turf Operator', 'code' => 'TOP', 'department_id' => $dept->id]);

        $this->venue = Venue::create([
            'name' => 'Adyar Turf Ground',
            'category' => 'TURF',
            'location' => 'Adyar',
            'address' => '44 River View Rd',
            'city' => 'Chennai',
            'latitude' => 13.0012,
            'longitude' => 80.2565,
            'is_active' => true,
        ]);

        $this->shift = EmployeeShift::create([
            'name' => 'Morning Shift',
            'code' => 'MSHIFT',
            'start_time' => '06:00:00',
            'end_time' => '14:00:00',
            'grace_period_minutes' => 15,
            'half_day_threshold_hours' => 4.0,
            'full_day_threshold_hours' => 8.0,
            'is_active' => true,
        ]);

        // Manager
        $this->managerUser = User::factory()->create([
            'name' => 'Kavitha Lead',
            'email' => 'kavitha@haraan.com',
            'role' => 'EMPLOYEE',
            'status' => 'ACTIVE',
        ]);
        $this->managerEmployee = EmployeeProfile::create([
            'user_id' => $this->managerUser->id,
            'employee_code' => 'EMP-MGR-301',
            'department_id' => $dept->id,
            'designation_id' => $mgrDesig->id,
            'venue_id' => $this->venue->id,
            'employment_status' => 'active',
            'joining_date' => '2025-01-01',
        ]);

        // Worker reporting to Manager
        $this->workerUser = User::factory()->create([
            'name' => 'Suresh Operator',
            'email' => 'suresh@haraan.com',
            'role' => 'EMPLOYEE',
            'status' => 'ACTIVE',
        ]);
        $this->workerEmployee = EmployeeProfile::create([
            'user_id' => $this->workerUser->id,
            'employee_code' => 'EMP-WRK-302',
            'department_id' => $dept->id,
            'designation_id' => $wrkDesig->id,
            'venue_id' => $this->venue->id,
            'reporting_manager_id' => $this->managerUser->id,
            'employment_status' => 'active',
            'joining_date' => '2025-02-01',
        ]);

        // Assign roster for today
        EmployeeShiftRoster::create([
            'employee_profile_id' => $this->workerEmployee->id,
            'employee_shift_id' => $this->shift->id,
            'venue_id' => $this->venue->id,
            'roster_date' => Carbon::today()->toDateString(),
            'status' => 'scheduled',
        ]);
    }

    public function test_punch_ingestion_service_creates_attendance_and_cryptographic_audit_event(): void
    {
        $service = app(PunchIngestionService::class);
        $idempotencyKey = 'TXN-PUNCH-INGEST-001';

        $result = $service->ingestPunch(
            $this->workerEmployee,
            'in',
            13.0012,
            80.2565,
            $idempotencyKey,
            'gps_web',
            null,
            true
        );

        $this->assertEquals('accepted', $result['status']);
        $this->assertFalse($result['is_duplicate']);
        $this->assertEquals($idempotencyKey, $result['receipt']['idempotency_key']);

        // Verify Attendance record
        $attendance = EmployeeAttendance::where('employee_profile_id', $this->workerEmployee->id)
            ->whereDate('date', Carbon::today()->toDateString())
            ->first();

        $this->assertNotNull($attendance);
        $this->assertNotNull($attendance->clock_in_at);
        $this->assertEquals('inside', $attendance->clock_in_geofence_status);

        // Verify Cryptographic Audit Ledger
        $auditEvent = WorkforceAuditLedger::where('event_name', 'PUNCH_IN_INGESTED')
            ->where('actor_id', $this->workerUser->id)
            ->first();

        $this->assertNotNull($auditEvent);
        $this->assertNotEmpty($auditEvent->signature_hash);
        $this->assertEquals($idempotencyKey, $auditEvent->telemetry_metadata['idempotency_key']);

        // Verify Audit Hash Integrity
        $auditService = app(WorkforceAuditService::class);
        $verification = $auditService->verifyChainIntegrity();
        $this->assertTrue($verification['is_valid']);
        $this->assertNull($verification['tampered_record_id']);
    }

    public function test_punch_ingestion_deduplicates_duplicate_idempotency_key(): void
    {
        $service = app(PunchIngestionService::class);
        $idempotencyKey = 'TXN-REPLAY-PREVENTION-002';

        $firstResult = $service->ingestPunch(
            $this->workerEmployee,
            'in',
            13.0012,
            80.2565,
            $idempotencyKey,
            'gps_web',
            null,
            true
        );

        $this->assertEquals('accepted', $firstResult['status']);
        $this->assertFalse($firstResult['is_duplicate']);

        // Second ingestion with identical idempotency key
        $secondResult = $service->ingestPunch(
            $this->workerEmployee,
            'in',
            13.0012,
            80.2565,
            $idempotencyKey,
            'gps_web',
            null,
            true
        );

        $this->assertEquals('acknowledged', $secondResult['status']);
        $this->assertTrue($secondResult['is_duplicate']);
        $this->assertEquals($firstResult['receipt'], $secondResult['receipt']);

        // Only 1 audit event should exist for this idempotency key
        $auditCount = WorkforceAuditLedger::where('event_name', 'PUNCH_IN_INGESTED')
            ->where('actor_id', $this->workerUser->id)
            ->count();
        $this->assertEquals(1, $auditCount);
    }

    public function test_api_punch_endpoint_processes_valid_jwt_and_payload(): void
    {
        $token = JwtService::issueForUser($this->workerUser, $this->jwtSecret);

        $payload = [
            'punch_type' => 'in',
            'latitude' => 13.0012,
            'longitude' => 80.2565,
            'method' => 'mobile_app',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Idempotency-Key' => 'TXN-API-PUNCH-003',
        ])->postJson('/api/v1/workforce/punch', $payload);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'status' => 'accepted',
            'is_duplicate' => false,
        ]);

        // Replay identical request
        $replayResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Idempotency-Key' => 'TXN-API-PUNCH-003',
        ])->postJson('/api/v1/workforce/punch', $payload);

        $replayResponse->assertStatus(200);
        $replayResponse->assertJson([
            'success' => true,
            'status' => 'acknowledged',
            'is_duplicate' => true,
        ]);
    }

    public function test_api_sync_batch_endpoint_processes_chronological_offline_events(): void
    {
        $token = JwtService::issueForUser($this->workerUser, $this->jwtSecret);

        $task = EmployeeTask::create([
            'title' => 'Morning Pitch Dew Removal',
            'employee_profile_id' => $this->workerEmployee->id,
            'assigned_by' => $this->managerUser->id,
            'venue_id' => $this->venue->id,
            'status' => 'todo',
            'priority' => 'high',
            'progress_percent' => 0,
            'due_date' => Carbon::today()->toDateString(),
        ]);

        $events = [
            [
                'client_event_id' => 'OFFLINE-EVT-001',
                'event_type' => 'punch',
                'occurred_at' => Carbon::today()->setTime(6, 5, 0)->toIso8601String(),
                'employee_code' => $this->workerEmployee->employee_code,
                'payload' => [
                    'punch_type' => 'in',
                    'latitude' => 13.0012,
                    'longitude' => 80.2565,
                    'method' => 'turnstile_nfc',
                ],
            ],
            [
                'client_event_id' => 'OFFLINE-EVT-002',
                'event_type' => 'task_completion',
                'occurred_at' => Carbon::today()->setTime(8, 30, 0)->toIso8601String(),
                'payload' => [
                    'task_id' => $task->id,
                ],
            ],
            [
                'client_event_id' => 'OFFLINE-EVT-003',
                'event_type' => 'punch',
                'occurred_at' => Carbon::today()->setTime(14, 0, 0)->toIso8601String(),
                'employee_code' => $this->workerEmployee->employee_code,
                'payload' => [
                    'punch_type' => 'out',
                    'latitude' => 13.0012,
                    'longitude' => 80.2565,
                    'method' => 'turnstile_nfc',
                ],
            ],
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/workforce/sync-batch', [
            'device_id' => 'TURNSTILE-GATE-04',
            'events' => $events,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'total_received' => 3,
            'processed_count' => 3,
            'failed_count' => 0,
        ]);

        // Verify task completed
        $this->assertEquals('completed', $task->fresh()->status);
        $this->assertEquals(100, $task->fresh()->progress_percent);

        // Verify attendance completed with clock in and clock out
        $attendance = EmployeeAttendance::where('employee_profile_id', $this->workerEmployee->id)
            ->whereDate('date', Carbon::today()->toDateString())
            ->first();

        $this->assertNotNull($attendance);
        $this->assertNotNull($attendance->clock_in_at);
        $this->assertNotNull($attendance->clock_out_at);
    }

    public function test_mss_live_operations_stream_displays_reportee_telemetry_events(): void
    {
        // 1. Worker punches in via ingestion service
        $service = app(PunchIngestionService::class);
        $service->ingestPunch(
            $this->workerEmployee,
            'in',
            13.0012,
            80.2565,
            'TXN-STREAM-001',
            'gps_web',
            null,
            true
        );

        // 2. Manager accesses MSS MyTeamPage
        $this->actingAs($this->managerUser);

        $page = new MyTeamPage();
        $page->setTab('activity');

        $events = $page->getActivityEventsProperty();

        $this->assertNotEmpty($events);
        $latest = $events[0];

        $this->assertEquals('PUNCH_IN_INGESTED', $latest->event_name);
        $this->assertEquals($this->workerUser->id, $latest->actor_id);
        $this->assertEquals('TXN-STREAM-001', $latest->telemetry_metadata['idempotency_key']);
        $this->assertEquals('inside', $latest->telemetry_metadata['geofence_status']);
    }
}
