<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\AppUsers\AppUserResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserNormalizationAndIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_indexes_exist(): void
    {
        $indexes = collect(Schema::getIndexes('users'))->pluck('name')->all();

        $this->assertContains('users_role_index', $indexes);
        $this->assertContains('users_status_index', $indexes);
        $this->assertContains('users_created_at_index', $indexes);
        $this->assertContains('users_district_index', $indexes);
    }

    public function test_role_and_status_mutators_normalize_to_uppercase_at_rest(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'status' => 'active',
        ]);

        $this->assertSame('USER', $user->fresh()->role);
        $this->assertSame('ACTIVE', $user->fresh()->status);

        $user->role = 'partner';
        $user->status = 'suspended';
        $user->save();

        $this->assertSame('PARTNER', $user->fresh()->role);
        $this->assertSame('SUSPENDED', $user->fresh()->status);
    }

    public function test_empty_or_null_role_and_status_fallback_to_defaults(): void
    {
        $user = new User();
        $user->name = 'Test User';
        $user->email = 'test_fallback@example.com';
        $user->password = bcrypt('secret1234');
        $user->role = null;
        $user->status = null;
        $user->save();

        $this->assertSame('USER', $user->fresh()->role);
        $this->assertSame('ACTIVE', $user->fresh()->status);
    }

    public function test_app_user_resource_query_scopes_to_app_users_only(): void
    {
        $appUser = User::factory()->create(['role' => 'USER']);
        $staff = User::factory()->create(['role' => 'ADMIN']);
        $partner = User::factory()->create(['role' => 'PARTNER']);

        $results = AppUserResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($appUser->id, $results);
        $this->assertNotContains($staff->id, $results);
        $this->assertNotContains($partner->id, $results);
    }

    public function test_staff_user_resource_query_scopes_to_staff_roles_only(): void
    {
        $appUser = User::factory()->create(['role' => 'USER']);
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $ops = User::factory()->create(['role' => 'OPS']);
        $partner = User::factory()->create(['role' => 'PARTNER']);

        $results = UserResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($admin->id, $results);
        $this->assertContains($ops->id, $results);
        $this->assertNotContains($appUser->id, $results);
        $this->assertNotContains($partner->id, $results);
    }
}
