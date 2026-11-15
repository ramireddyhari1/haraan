<?php

declare(strict_types=1);

namespace Tests\Feature\Hrms;

use App\Filament\Resources\Hrms\EmployeeResource;
use App\Filament\Resources\Hrms\Pages\CreateEmployee;
use App\Filament\Resources\Hrms\Pages\EditEmployee;
use App\Models\Hrms\EmployeeProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * /control/hrms/employees/create used to expose 9 of employee_profiles' 27 columns,
 * and could only attach an EXISTING user — onboarding a new hire meant creating
 * them somewhere else first. These pin the full form and the inline account flow.
 */
class EmployeeCreateFormTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@example.test',
            'password' => bcrypt('secret'), 'role' => 'ADMIN', 'status' => 'active',
        ]);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('control'));

        return $admin;
    }

    public function test_create_page_renders(): void
    {
        $this->admin();

        Livewire::test(CreateEmployee::class)->assertSuccessful();
    }

    public function test_full_profile_is_saved(): void
    {
        $this->admin();
        $staff = User::create(['name' => 'Ravi Kumar', 'email' => 'ravi@example.test', 'password' => 'secret123']);

        Livewire::test(CreateEmployee::class)
            ->fillForm([
                'user_id' => $staff->id,
                'employee_code' => 'hrn-emp-2001',
                'employment_status' => 'probation',
                'employment_type' => 'part_time',
                'joining_date' => '2026-11-01',
                'base_salary' => 22000,
                'hourly_rate' => 150,
                'geofence_radius_meters' => 300,
                'dob' => '1998-04-12',
                'gender' => 'male',
                'blood_group' => 'O+',
                'marital_status' => 'single',
                'residential_address' => '12 MG Road, Kadapa',
                'emergency_contact_name' => 'Lakshmi',
                'emergency_contact_relation' => 'Mother',
                'emergency_contact_phone' => '9876543210',
                'bank_name' => 'SBI',
                'bank_account_no' => '123456789012',
                'bank_ifsc' => 'sbin0001234',
                'bank_upi_id' => 'ravi@okaxis',
                'pan_number' => 'abcde1234f',
                'aadhaar_number' => '1234 5678 9012',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $p = EmployeeProfile::where('user_id', $staff->id)->firstOrFail();
        $this->assertSame('HRN-EMP-2001', $p->employee_code);
        $this->assertSame('part_time', $p->employment_type);
        $this->assertSame('O+', $p->blood_group);
        $this->assertSame('Mother', $p->emergency_contact_relation);
        $this->assertSame('SBIN0001234', $p->bank_ifsc);
        $this->assertSame('ABCDE1234F', $p->pan_number);
        $this->assertSame('123456789012', $p->aadhaar_number);
        $this->assertSame('1998-04-12', $p->dob->toDateString());
    }

    public function test_bad_kyc_values_are_rejected(): void
    {
        $this->admin();
        $staff = User::create(['name' => 'X', 'email' => 'x@example.test', 'password' => 'secret123']);

        Livewire::test(CreateEmployee::class)
            ->fillForm([
                'user_id' => $staff->id,
                'pan_number' => 'ABC123',
                'bank_ifsc' => 'SBIN123',
                'aadhaar_number' => '1234',
                'emergency_contact_phone' => '12345',
            ])
            ->call('create')
            ->assertHasFormErrors(['pan_number', 'bank_ifsc', 'aadhaar_number', 'emergency_contact_phone']);
    }

    public function test_a_person_cannot_get_two_employee_records(): void
    {
        $this->admin();
        $staff = User::create(['name' => 'Y', 'email' => 'y@example.test', 'password' => 'secret123']);
        EmployeeProfile::create(['user_id' => $staff->id, 'employee_code' => 'HRN-EMP-1001']);

        Livewire::test(CreateEmployee::class)
            ->fillForm(['user_id' => $staff->id, 'employee_code' => 'HRN-EMP-1002'])
            ->call('create')
            ->assertHasFormErrors(['user_id' => 'unique']);
    }

    public function test_new_staff_account_can_be_created_inline(): void
    {
        $this->admin();

        Livewire::test(CreateEmployee::class)
            ->callFormComponentAction('user_id', 'createOption', [
                'name' => 'Priya Nair',
                'email' => 'Priya@Haraan.test',
                'phone' => '9123456780',
                'password' => 'welcome123',
            ])
            ->assertHasNoFormComponentActionErrors();

        $u = User::where('email', 'priya@haraan.test')->firstOrFail();
        $this->assertSame('Priya Nair', $u->name);
        $this->assertTrue(\Hash::check('welcome123', $u->password));
    }

    public function test_employee_code_is_sequential(): void
    {
        $u = User::create(['name' => 'Z', 'email' => 'z@example.test', 'password' => 'secret123']);
        EmployeeProfile::create(['user_id' => $u->id, 'employee_code' => 'HRN-EMP-1041']);

        $this->assertSame('HRN-EMP-1042', EmployeeResource::nextEmployeeCode());
    }

    public function test_legacy_uppercase_status_loads_on_edit(): void
    {
        $this->admin();
        $u = User::create(['name' => 'W', 'email' => 'w@example.test', 'password' => 'secret123']);
        $p = EmployeeProfile::create(['user_id' => $u->id, 'employee_code' => 'HRN-EMP-1050', 'employment_status' => 'ACTIVE']);

        Livewire::test(EditEmployee::class, ['record' => $p->getRouteKey()])
            ->assertFormSet(['employment_status' => 'active'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('active', $p->fresh()->employment_status);
    }
}
