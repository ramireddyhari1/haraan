<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use App\Support\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Holes closed in the 2026-11-26 security pass. */
class AccessHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'USER', string $email = 'p@haraan.test'): User
    {
        return User::create([
            'name' => $role.' user', 'email' => $email, 'password' => Hash::make('secret123'),
            'role' => $role, 'status' => 'active',
        ]);
    }

    private function as(User $u): self
    {
        return $this->withHeader('Authorization', 'Bearer '.JwtService::issueForUser($u, (string) config('app.jwt_secret')));
    }

    public function test_setup_admin_route_is_gone_and_portal_needs_a_real_key(): void
    {
        config(['app.erp_portal_key' => '']);
        $this->get('/erp/setup-admin?key=letmein&email=x@y.z&password=p')->assertNotFound();
        $this->get('/erp?key=letmein')->assertNotFound();
        $this->assertNull(User::where('email', 'x@y.z')->first());

        // A signed-in ordinary user no longer passes the portal gate.
        $this->actingAs($this->user())->get('/erp')->assertNotFound();
    }

    public function test_a_player_cannot_edit_or_create_events(): void
    {
        $host = $this->user('PARTNER', 'host@haraan.test');
        $event = Event::forceCreate([
            'title' => 'Real Event', 'partner_id' => $host->id, 'price' => 500, 'status' => 'PUBLISHED',
            'total_slots' => 100, 'available_slots' => 100, 'description' => '', 'location' => '', 'venue' => '', 'time' => '7 PM', 'date' => '2026-12-20',
        ]);
        $player = $this->user('USER', 'player@haraan.test');

        $this->as($player)->putJson("/api/events/{$event->id}", ['price' => 1, 'title' => 'Hacked'])->assertForbidden();
        $this->assertSame('Real Event', $event->refresh()->title);
        $this->as($player)->postJson('/api/events', ['title' => 'Fake'])->assertForbidden();

        $this->as($host)->putJson("/api/events/{$event->id}", ['title' => 'Renamed'])->assertOk();
        $this->assertSame('Renamed', $event->refresh()->title);
    }

    public function test_a_user_cannot_read_someone_elses_booking(): void
    {
        $owner = $this->user('USER', 'owner@haraan.test');
        $other = $this->user('USER', 'other@haraan.test');
        $b = Booking::forceCreate([
            'user_id' => $owner->id, 'booking_type' => 'event', 'status' => 'CONFIRMED',
            'quantity' => 1, 'total_amount' => 100, 'ticket_code' => 'SECRETQR1',
        ]);

        $this->as($other)->getJson("/api/bookings/{$b->id}")->assertNotFound();
        $this->as($owner)->getJson("/api/bookings/{$b->id}")->assertOk()->assertJsonPath('data.ticketCode', 'SECRETQR1');
    }

    public function test_tokens_need_a_strong_secret_and_an_expiry(): void
    {
        $this->assertNull(JwtService::decode('a.b.c', 'change_me'));
        $this->expectException(\RuntimeException::class);
        JwtService::issue(['sub' => 1], 'short');
    }

    public function test_a_token_without_expiry_is_rejected(): void
    {
        $secret = (string) config('app.jwt_secret');
        $enc = fn (array $a): string => rtrim(strtr(base64_encode((string) json_encode($a)), '+/', '-_'), '=');
        $h = $enc(['alg' => 'HS256', 'typ' => 'JWT']);
        $p = $enc(['sub' => 1]);
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', "$h.$p", $secret, true)), '+/', '-_'), '=');
        $this->assertNull(JwtService::decode("$h.$p.$sig", $secret));
    }
}
