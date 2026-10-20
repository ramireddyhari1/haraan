<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\WorkforceToday;
use App\Filament\Resources\AppUsers\AppUserResource;
use App\Filament\Resources\Partners\Pages\ListPartners;
use App\Models\Hrms\EmployeeAttendance;
use App\Models\Hrms\EmployeeLeaveRequest;
use App\Models\Hrms\EmployeeProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class PeopleAndWorkforceTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): void
    {
        $this->actingAs($this->user('admin@h.test', 'ADMIN'));
        Filament::setCurrentPanel(Filament::getPanel('control'));
    }

    private function user(string $email, ?string $role, array $extra = []): User
    {
        $u = User::create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => bcrypt('x'), 'status' => 'ACTIVE'] + $extra);
        // role has a column default; force NULL where the test needs it.
        $u->forceFill(['role' => $role])->save();

        return $u;
    }

    public function test_app_users_exclude_employees_and_keep_users_with_no_role(): void
    {
        $this->signIn();
        $this->user('player@h.test', 'USER');
        $this->user('norole@h.test', null);
        $this->user('worker@h.test', 'EMPLOYEE');
        $this->user('owner@h.test', 'PARTNER');

        $emails = AppUserResource::getEloquentQuery()->pluck('email')->sort()->values()->all();

        $this->assertSame(['norole@h.test', 'player@h.test'], $emails);
    }

    public function test_partners_list_shows_owners_by_default_and_reads_uppercase_status_as_active(): void
    {
        $this->signIn();
        $owner = $this->user('owner@h.test', 'PARTNER', ['partner_type' => 'venue']);
        $desk = $this->user('desk@h.test', 'PARTNER', ['parent_partner_id' => $owner->id]);

        Livewire::test(ListPartners::class)
            ->assertCanSeeTableRecords([$owner])
            ->assertCanNotSeeTableRecords([$desk])
            ->assertSee('Active')
            ->assertSee('Nothing listed yet')
            ->assertSee('no payout account')
            ->filterTable('account', 'staff')
            ->assertCanSeeTableRecords([$desk])
            ->assertSee('Desk staff of Owner');
    }

    public function test_workforce_today_counts_in_off_and_not_in(): void
    {
        $this->signIn();
        $make = fn (string $email, string $code): EmployeeProfile => EmployeeProfile::create([
            'user_id' => $this->user($email, 'EMPLOYEE')->id,
            'employee_code' => $code,
            'joining_date' => '2025-06-01',
            'employment_type' => 'full_time',
            'employment_status' => 'ACTIVE',
        ]);
        $in = $make('in@h.test', 'E1');
        $off = $make('off@h.test', 'E2');
        $make('late@h.test', 'E3');

        EmployeeAttendance::create(['employee_profile_id' => $in->id, 'date' => Carbon::today()->toDateString(), 'clock_in_at' => now(), 'status' => 'present']);
        $type = \App\Models\Hrms\EmployeeLeaveType::create(['name' => 'Sick Leave', 'code' => 'SL', 'annual_quota' => 12, 'is_paid' => true, 'is_active' => true]);
        EmployeeLeaveRequest::create([
            'employee_leave_type_id' => $type->id,
            'employee_profile_id' => $off->id, 'start_date' => Carbon::today()->toDateString(), 'end_date' => Carbon::today()->toDateString(),
            'total_days' => 1, 'reason' => 'Unwell', 'status' => 'approved',
        ]);

        $today = collect((new WorkforceToday())->getPanels())->firstWhere('title', 'Today');
        $stat = fn (string $label): string => collect($today['stats'])->firstWhere('label', $label)['value'];

        $this->assertSame('3', $stat('On the books'));
        $this->assertSame('1', $stat('Clocked in'));
        $this->assertSame('1', $stat('On leave'));
        $this->assertSame('1', $stat('Not in yet'));
        $this->assertSame('Late', $today['list']['rows'][0]['primary']);

        Livewire::test(WorkforceToday::class)->assertOk()->assertSee('Waiting on a manager');
    }
}
