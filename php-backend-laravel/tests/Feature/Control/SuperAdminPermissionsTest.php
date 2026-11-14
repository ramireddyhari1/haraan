<?php

namespace Tests\Feature\Control;

use App\Filament\Resources\Ads\AdResource;
use App\Filament\Resources\Coupons\CouponResource;
use App\Filament\Resources\FeatureFlags\FeatureFlagResource;
use App\Filament\Resources\HomeBlocks\HomeBlockResource;
use App\Filament\Resources\Payouts\PayoutResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * ADMIN and COADMIN get full control of /control from the `role` column alone —
 * no spatie `super_admin` role needed for the Shield "Verb:Model" permissions.
 */
class SuperAdminPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role, string $status = 'ACTIVE'): User
    {
        $u = new User();
        $u->forceFill([
            'name' => "Staff {$role}",
            'email' => strtolower($role) . uniqid() . '@example.test',
            'password' => 'secret-password',
            'role' => $role,
            'status' => $status,
        ])->save();

        return $u->fresh();
    }

    public static function superRoles(): array
    {
        return [['ADMIN'], ['COADMIN'], ['coadmin']];
    }

    #[DataProvider('superRoles')]
    public function test_super_admins_can_manage_shield_gated_screens(string $role): void
    {
        Filament::setCurrentPanel(Filament::getPanel('control'));
        $this->actingAs($this->staff($role));

        foreach ([AdResource::class, CouponResource::class, FeatureFlagResource::class, HomeBlockResource::class, PayoutResource::class] as $resource) {
            $this->assertTrue($resource::canViewAny(), "{$role} cannot list " . class_basename($resource));
            $this->assertTrue(Gate::allows('Update:' . class_basename($resource::getModel())), "{$role} cannot update " . class_basename($resource));
        }
    }

    public function test_department_roles_and_suspended_admins_get_no_blanket_permissions(): void
    {
        foreach ([$this->staff('MARKETING'), $this->staff('OPS'), $this->staff('COADMIN', 'SUSPENDED')] as $user) {
            $this->assertFalse(Gate::forUser($user)->allows('Delete:Payout'), "{$user->role}/{$user->status} got Delete:Payout");
        }
    }

    public function test_non_permission_abilities_are_left_to_their_policies(): void
    {
        // Plain abilities (no "Verb:Model" colon) are not short-circuited.
        $this->assertFalse(Gate::forUser($this->staff('ADMIN'))->allows('some-undefined-ability'));
    }
}
