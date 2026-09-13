<?php

declare(strict_types=1);

namespace Tests\Feature\Hrms;

use App\Models\Hrms\Department;
use App\Models\Hrms\Designation;
use App\Models\Hrms\EmployeeAttendance;
use App\Models\Hrms\EmployeeAttendanceRegularisation;
use App\Models\Hrms\EmployeeProfile;
use App\Models\Hrms\EmployeeShift;
use App\Models\Hrms\WorkforceAuditLedger;
use App\Models\User;
use App\Models\Venue;
use App\Services\Hrms\StatutoryPayrollService;
use App\Services\Hrms\WorkforceAuditService;
use App\Services\Hrms\WorkforceHealthService;
use App\Support\JwtService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WorkforceProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private EmployeeProfile $employee;
    private Venue $venue;
    private string $jwtSecret;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('employee'));
        $this->jwtSecret = (string) config('app.jwt_secret', env('JWT_SECRET', 'test_secret_key_1234567890123456'));
        config(['app.jwt_secret' => $this->jwtSecret]);

        $dept = Department::create(['name' => 'Engineering', 'code' => 'ENG']);
        $desig = Designation::create(['name' => 'Site Reliability Engineer', 'code' => 'SRE', 'department_id' => $dept->id]);

        $this->venue = Venue::create([
            'name' => 'Central Hub',
            'category' => 'TURF',
            'location' => 'Velachery',
            'address' => '100 Feet Bypass Rd',
            'city' => 'Chennai',
            'latitude' => 12.9815,
            'longitude' => 80.2180,
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'name' => 'SRE Lead',
            'email' => 'sre@haraan.com',
            'role' => 'EMPLOYEE',
            'status' => 'ACTIVE',
        ]);

        $this->employee = EmployeeProfile::create([
            'user_id' => $this->user->id,
            'employee_code' => 'EMP-SRE-001',
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'venue_id' => $this->venue->id,
            'employment_status' => 'active',
            'base_salary' => 50000.0,
        ]);
    }

    public function test_configuration_driven_rules_override_statutory_rates(): void
    {
        $payrollService = app(StatutoryPayrollService::class);
        $month = Carbon::now()->format('Y-m');
        $startDate = Carbon::parse("{$month}-01")->startOfMonth();

        // Seed an attendance record with 2 hours overtime (600 mins total, 480 threshold)
        EmployeeAttendance::create([
            'employee_profile_id' => $this->employee->id,
            'date' => $startDate->toDateString(),
            'check_in' => (clone $startDate)->setTime(9, 0),
            'check_out' => (clone $startDate)->setTime(19, 0),
            'status' => 'present',
            'total_work_minutes' => 600,
        ]);

        // 1. Default configuration: PF employee rate 12% (0.12), OT 2.0x
        $defaultPayroll = $payrollService->calculateForEmployee($this->employee, $month);
        $expectedPfDefault = round($defaultPayroll->basic_salary * 0.12, 2);
        $this->assertEquals($expectedPfDefault, (float) $defaultPayroll->pf_deduction);

        // 2. Runtime config override: Increase PF employee rate to 15% (0.15) & OT to 3.0x
        config([
            'workforce.statutory.pf_employee_rate' => 0.15,
            'workforce.statutory.overtime_rate_multiplier' => 3.0,
        ]);

        $customPayroll = $payrollService->calculateForEmployee($this->employee, $month);
        $expectedPfCustom = round($customPayroll->basic_salary * 0.15, 2);
        $this->assertEquals($expectedPfCustom, (float) $customPayroll->pf_deduction);
        $this->assertGreaterThan((float) $defaultPayroll->overtime_amount, (float) $customPayroll->overtime_amount);
    }

    public function test_workforce_health_service_reports_healthy_subsystems(): void
    {
        $healthService = app(WorkforceHealthService::class);
        $health = $healthService->checkHealth();

        $this->assertEquals('healthy', $health['status']);
        $this->assertArrayHasKey('database', $health['checks']);
        $this->assertArrayHasKey('cache_idempotency_store', $health['checks']);
        $this->assertArrayHasKey('cryptographic_audit_ledger', $health['checks']);
        $this->assertArrayHasKey('sla_backlog', $health['checks']);

        $this->assertEquals('pass', $health['checks']['database']['status']);
        $this->assertEquals('pass', $health['checks']['cache_idempotency_store']['status']);
        $this->assertEquals('pass', $health['checks']['cryptographic_audit_ledger']['status']);
        $this->assertEquals('pass', $health['checks']['sla_backlog']['status']);
        $this->assertGreaterThan(0, $health['duration_ms']);
    }

    public function test_workforce_health_service_flags_degraded_on_sla_backlog(): void
    {
        // Seed 11 stale regularisations exceeding the SLA threshold (default 24h)
        for ($i = 1; $i <= 11; $i++) {
            $reg = EmployeeAttendanceRegularisation::create([
                'employee_profile_id' => $this->employee->id,
                'date' => Carbon::today()->subDays(3)->toDateString(),
                'requested_clock_in_at' => Carbon::today()->subDays(3)->setTime(9, 0),
                'requested_clock_out_at' => Carbon::today()->subDays(3)->setTime(17, 0),
                'reason_category' => 'other',
                'reason' => "Stale request {$i}",
                'status' => 'pending',
                'approval_tier' => 1,
            ]);
            // Force created_at to 48 hours ago
            $reg->created_at = Carbon::now()->subHours(48);
            $reg->save();
        }

        $healthService = app(WorkforceHealthService::class);
        $health = $healthService->checkHealth();

        $this->assertEquals('degraded', $health['status']);
        $this->assertEquals('warn', $health['checks']['sla_backlog']['status']);
        $this->assertGreaterThanOrEqual(11, $health['checks']['sla_backlog']['stale_count']);
    }

    public function test_artisan_health_check_command_runs_successfully(): void
    {
        $this->artisan('workforce:health-check')
            ->expectsOutputToContain('HARAAN Workforce Operating System — Production Health Review')
            ->assertExitCode(0);

        $this->artisan('workforce:health-check', ['--json' => true])
            ->expectsOutputToContain('"status": "healthy"')
            ->assertExitCode(0);
    }

    public function test_api_health_endpoint_requires_authentication_and_returns_diagnostics(): void
    {
        // 1. Unauthenticated request must be rejected with 401
        $guestResponse = $this->getJson('/api/v1/workforce/health');
        $guestResponse->assertStatus(401);

        // 2. Authenticated request with JWT token succeeds
        $token = JwtService::issueForUser($this->user, $this->jwtSecret);

        $authResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/workforce/health');

        $authResponse->assertStatus(200);
        $authResponse->assertJsonStructure([
            'status',
            'timestamp',
            'duration_ms',
            'checks' => [
                'database',
                'cache_idempotency_store',
                'cryptographic_audit_ledger',
                'sla_backlog',
            ],
        ]);
        $this->assertEquals('healthy', $authResponse->json('status'));
    }

    public function test_rate_limiting_headers_are_applied(): void
    {
        $token = JwtService::issueForUser($this->user, $this->jwtSecret);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/workforce/health');

        $response->assertStatus(200);
        $this->assertTrue($response->headers->has('X-RateLimit-Limit'));
        $this->assertTrue($response->headers->has('X-RateLimit-Remaining'));
    }
}
