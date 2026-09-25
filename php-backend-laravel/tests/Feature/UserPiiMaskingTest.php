<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Resources\UserAdminResource;
use App\Models\AdminAction;
use App\Models\User;
use App\Support\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserPiiMaskingTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attrs = []): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'name' => "User {$n}",
            'email' => "user{$n}@example.com",
            'phone' => "+91987654321{$n}",
            'password' => Hash::make('secret-password'),
            'role' => 'USER',
            'status' => 'ACTIVE',
            'date_of_birth' => '1995-04-12',
            'is_guest' => false,
        ], $attrs));
    }

    private function auth(User $user): array
    {
        $secret = (string) (config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me'));

        return ['Authorization' => 'Bearer ' . JwtService::issueForUser($user->fresh(), $secret)];
    }

    public function test_masking_helper_methods(): void
    {
        $this->assertSame('*********3210', UserAdminResource::maskPhone('+919876543210'));
        $this->assertSame('****', UserAdminResource::maskPhone('1234'));
        $this->assertNull(UserAdminResource::maskPhone(null));

        $this->assertSame('h***n@example.com', UserAdminResource::maskEmail('hariharan@example.com'));
        $this->assertSame('j***@example.com', UserAdminResource::maskEmail('jo@example.com'));
        $this->assertNull(UserAdminResource::maskEmail(null));

        $this->assertSame('1995-**-**', UserAdminResource::maskDate('1995-04-12'));
        $this->assertNull(UserAdminResource::maskDate(null));
    }

    public function test_non_super_admin_request_receives_masked_pii(): void
    {
        $targetUser = $this->user([
            'name' => 'Target User',
            'email' => 'target.user@example.com',
            'phone' => '+919988776655',
            'date_of_birth' => '1992-06-15',
        ]);

        $regularUser = $this->user(['role' => 'USER']);

        $request = Request::create('/api/users/' . $targetUser->id);
        $request->setUserResolver(fn () => $regularUser);

        $resource = new UserAdminResource($targetUser);
        $data = $resource->toArray($request);

        $this->assertTrue($data['is_pii_masked']);
        $this->assertNotEquals('target.user@example.com', $data['email']);
        $this->assertStringContainsString('***', $data['email']);
        $this->assertNotEquals('+919988776655', $data['phone']);
        $this->assertStringEndsWith('6655', $data['phone']);
        $this->assertSame('1992-**-**', $data['date_of_birth']);
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('token_version', $data);
    }

    public function test_user_viewing_self_receives_unmasked_pii(): void
    {
        $user = $this->user([
            'email' => 'myself@example.com',
            'phone' => '+919988776655',
            'date_of_birth' => '1990-01-01',
        ]);

        $request = Request::create('/api/users/' . $user->id);
        $request->setUserResolver(fn () => $user);

        $resource = new UserAdminResource($user);
        $data = $resource->toArray($request);

        $this->assertFalse($data['is_pii_masked']);
        $this->assertSame('myself@example.com', $data['email']);
        $this->assertSame('+919988776655', $data['phone']);
        $this->assertSame('1990-01-01', $data['date_of_birth']);
    }

    public function test_super_admin_viewing_user_receives_unmasked_pii_and_logs_audit_entry(): void
    {
        $admin = $this->user(['role' => 'ADMIN']);
        $targetUser = $this->user([
            'email' => 'real.email@example.com',
            'phone' => '+919123456789',
            'date_of_birth' => '1988-11-20',
        ]);

        $response = $this->withHeaders($this->auth($admin))
            ->getJson("/api/users/{$targetUser->id}");

        $response->assertOk();
        $data = $response->json('data');

        $this->assertFalse($data['is_pii_masked']);
        $this->assertSame('real.email@example.com', $data['email']);
        $this->assertSame('+919123456789', $data['phone']);
        $this->assertSame('1988-11-20', $data['date_of_birth']);

        // Check that user.pii_viewed audit action was logged
        $this->assertDatabaseHas('admin_actions', [
            'action' => 'user.pii_viewed',
            'subject_type' => 'User',
            'subject_id' => $targetUser->id,
        ]);
    }
}
