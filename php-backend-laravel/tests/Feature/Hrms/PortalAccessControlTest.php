<?php

declare(strict_types=1);

namespace Tests\Feature\Hrms;

use App\Models\Hrms\Department;
use App\Models\Hrms\Designation;
use App\Models\Hrms\EmployeeProfile;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $partner;
    private User $employeeUser;
    private User $regularUser;
    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@haraan.com',
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
        ]);

        $this->partner = User::factory()->create([
            'name' => 'Venue Partner',
            'email' => 'partner@haraan.com',
            'role' => 'PARTNER',
            'status' => 'ACTIVE',
            'partner_type' => 'VENUE',
        ]);

        $this->venue = Venue::create([
            'name' => 'City Turf',
            'partner_id' => $this->partner->id,
            'category' => 'TURF',
            'location' => 'Mylapore, Chennai',
            'address' => '50 RK Mutt Road',
            'city' => 'Chennai',
            'latitude' => 13.0336,
            'longitude' => 80.2687,
            'is_active' => true,
        ]);

        $dept = Department::create(['name' => 'Ground Crew', 'code' => 'GC']);
        $desig = Designation::create(['name' => 'Groundskeeper', 'code' => 'GK', 'department_id' => $dept->id]);

        $this->employeeUser = User::factory()->create([
            'name' => 'Ground Staff Member',
            'email' => 'groundstaff@haraan.com',
            'role' => 'EMPLOYEE',
            'status' => 'ACTIVE',
        ]);

        EmployeeProfile::create([
            'user_id' => $this->employeeUser->id,
            'employee_code' => 'EMP-SEC-001',
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'venue_id' => $this->venue->id,
            'joining_date' => '2025-01-01',
            'employment_type' => 'FULL_TIME',
            'employment_status' => 'ACTIVE',
        ]);

        $this->regularUser = User::factory()->create([
            'name' => 'App Customer',
            'email' => 'customer@haraan.com',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_unauthenticated_user_redirected_from_employee_portal(): void
    {
        $response = $this->get('/employee');
        $response->assertRedirect('/employee/login');
    }

    public function test_active_employee_can_access_employee_dashboard(): void
    {
        $response = $this->actingAs($this->employeeUser)->get('/employee');
        $response->assertOk();
    }

    public function test_user_without_employee_profile_forbidden_from_employee_portal(): void
    {
        $response = $this->actingAs($this->regularUser)->get('/employee');
        $response->assertForbidden();
    }

    public function test_admin_can_access_admin_hrms_resources(): void
    {
        $response = $this->actingAs($this->admin)->get('/control/hrms/employees');
        $response->assertOk();
    }

    public function test_regular_employee_forbidden_from_admin_hrms_resources(): void
    {
        $response = $this->actingAs($this->employeeUser)->get('/control/hrms/employees');
        $response->assertForbidden();
    }

    public function test_partner_can_access_partner_hrms_pages(): void
    {
        $response = $this->actingAs($this->partner)->get('/partner/partner-roster-page');
        $response->assertOk();
    }

    public function test_employee_forbidden_from_partner_portal(): void
    {
        $response = $this->actingAs($this->employeeUser)->get('/partner/partner-roster-page');
        $response->assertForbidden();
    }
}
