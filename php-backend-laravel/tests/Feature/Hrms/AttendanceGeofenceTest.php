<?php

declare(strict_types=1);

namespace Tests\Feature\Hrms;

use App\Models\Hrms\Department;
use App\Models\Hrms\Designation;
use App\Models\Hrms\EmployeeAttendance;
use App\Models\Hrms\EmployeeProfile;
use App\Models\Hrms\EmployeeShift;
use App\Models\User;
use App\Models\Venue;
use App\Services\Hrms\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

class AttendanceGeofenceTest extends TestCase
{
    use RefreshDatabase;

    private AttendanceService $service;
    private User $user;
    private Venue $venue;
    private EmployeeProfile $employee;
    private EmployeeShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new AttendanceService();

        $partner = User::factory()->create([
            'name' => 'Venue Partner',
            'email' => 'partner.test@haraan.com',
            'role' => 'PARTNER',
            'partner_type' => 'VENUE',
        ]);

        // Marina Beach Turf - Lat: 13.0827, Lon: 80.2707
        $this->venue = Venue::create([
            'name' => 'Marina Turf Club',
            'partner_id' => $partner->id,
            'category' => 'TURF',
            'location' => 'Marina, Chennai',
            'address' => 'Beach Road, Chennai',
            'city' => 'Chennai',
            'latitude' => 13.0827,
            'longitude' => 80.2707,
            'is_active' => true,
        ]);

        $dept = Department::create(['name' => 'Ops', 'code' => 'OPS']);
        $desig = Designation::create(['name' => 'Crew', 'code' => 'CRW', 'department_id' => $dept->id]);

        $this->user = User::factory()->create([
            'name' => 'Test Employee',
            'email' => 'worker.test@haraan.com',
            'role' => 'EMPLOYEE',
        ]);

        $this->employee = EmployeeProfile::create([
            'user_id' => $this->user->id,
            'employee_code' => 'EMP-TEST-001',
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'venue_id' => $this->venue->id,
            'joining_date' => '2025-01-01',
            'employment_type' => 'FULL_TIME',
            'employment_status' => 'ACTIVE',
            'base_salary' => 30000,
            'geofence_radius_meters' => 200,
        ]);

        $this->shift = EmployeeShift::create([
            'name' => 'Morning',
            'code' => 'MORN',
            'start_time' => '08:00:00',
            'end_time' => '16:30:00',
            'grace_period_minutes' => 15,
            'half_day_threshold_minutes' => 240,
            'is_active' => true,
        ]);
    }

    public function test_haversine_distance_calculation_is_accurate(): void
    {
        // Points ~111 meters apart (0.001 degree latitude)
        $distance = $this->service->calculateDistanceMeters(13.0827, 80.2707, 13.0837, 80.2707);

        $this->assertGreaterThan(100, $distance);
        $this->assertLessThan(120, $distance);

        // Same point distance is 0
        $zeroDistance = $this->service->calculateDistanceMeters(13.0827, 80.2707, 13.0827, 80.2707);
        $this->assertEquals(0.0, $zeroDistance);
    }

    public function test_clock_in_within_geofence_marks_inside_status(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-05 07:55:00'));

        // Coordinate right next to venue (< 50m)
        $attendance = $this->service->clockIn(
            $this->employee,
            13.0828,
            80.2708,
            'gps_web'
        );

        $this->assertNotNull($attendance->id);
        $this->assertEquals('inside', $attendance->clock_in_geofence_status);
        $this->assertLessThanOrEqual(200, $attendance->clock_in_distance_meters);
        $this->assertNotNull($attendance->clock_in_at);
        $this->assertEquals('2026-09-05', $attendance->date->toDateString());
    }

    public function test_clock_in_far_from_venue_marks_outside_geofence_status(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-05 07:58:00'));

        // Coordinate 5km away (Guindy area)
        $attendance = $this->service->clockIn(
            $this->employee,
            13.0067,
            80.2030,
            'gps_web'
        );

        $this->assertEquals('outside', $attendance->clock_in_geofence_status);
        $this->assertGreaterThan(200, $attendance->clock_in_distance_meters);
    }

    public function test_double_clock_in_on_same_day_throws_exception(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-05 08:00:00'));

        $this->service->clockIn($this->employee, 13.0827, 80.2707);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Employee already clocked in today');

        $this->service->clockIn($this->employee, 13.0827, 80.2707);
    }

    public function test_break_tracking_computes_duration_correctly(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-05 08:00:00'));
        $this->service->clockIn($this->employee, 13.0827, 80.2707);

        // Start lunch break at 13:00
        Carbon::setTestNow(Carbon::parse('2026-09-05 13:00:00'));
        $this->service->startBreak($this->employee, 'lunch');

        // End lunch break at 13:45 (45 minutes)
        Carbon::setTestNow(Carbon::parse('2026-09-05 13:45:00'));
        $attendance = $this->service->endBreak($this->employee);

        $this->assertEquals(45, $attendance->total_break_minutes);
        $this->assertCount(1, $attendance->break_logs);
        $this->assertEquals('lunch', $attendance->break_logs[0]['type']);
        $this->assertEquals(45, $attendance->break_logs[0]['duration_minutes']);
    }

    public function test_dynamic_qr_token_generates_and_verifies_successfully(): void
    {
        $token = $this->service->generateVenueQrToken($this->venue->id, 60);

        $this->assertNotEmpty($token);
        $this->assertTrue($this->service->verifyVenueQrToken($this->venue->id, $token, 60));

        // Verifying for a different venue returns false
        $this->assertFalse($this->service->verifyVenueQrToken(99999, $token, 60));
    }

    public function test_expired_qr_token_fails_verification(): void
    {
        $token = $this->service->generateVenueQrToken($this->venue->id, 60);

        // Advance clock past TTL
        Carbon::setTestNow(Carbon::now()->addSeconds(65));

        $this->assertFalse($this->service->verifyVenueQrToken($this->venue->id, $token, 60));
    }
}
