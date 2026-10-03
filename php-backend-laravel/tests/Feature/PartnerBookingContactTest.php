<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Support\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The partner's bookings feed carries the customer's contact, so the desk can call or
 * WhatsApp them from a payment — and a walk-in's contact is the guest's, never the
 * partner account the row is filed under.
 */
final class PartnerBookingContactTest extends TestCase
{
    use RefreshDatabase;

    private User $partner;

    private Venue $venue;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->partner = User::factory()->create([
            'role' => 'partner', 'partner_type' => 'venue', 'status' => 'active',
            'phone' => '+919000000001', 'email' => 'owner@venue.test',
        ]);
        $this->venue = Venue::create([
            'name' => 'Contact Turf', 'location' => 'Vaddeswaram', 'price' => 500,
            'is_active' => true, 'is_bookable' => true, 'partner_id' => $this->partner->id,
        ]);
        $secret = config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me');
        $this->token = JwtService::issueForUser($this->partner, $secret);
    }

    private function feed(): array
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/partner/bookings')->assertOk()->json('data');
    }

    private function book(array $attrs): Booking
    {
        return Booking::create($attrs + [
            'quantity' => 1, 'total_amount' => 500, 'status' => 'CONFIRMED', 'booking_type' => 'venue',
            'venue_id' => $this->venue->id, 'slot_date' => now()->toDateString(),
            'start_time' => '19:00', 'end_time' => '20:00',
        ]);
    }

    public function test_an_online_booking_carries_the_customers_own_phone_and_email(): void
    {
        $player = User::factory()->create(['phone' => '+919876543210', 'email' => 'player@mail.test']);
        $this->book(['user_id' => $player->id, 'channel' => 'online']);

        $row = $this->feed()[0];
        $this->assertSame('+919876543210', $row['phone']);
        $this->assertSame('player@mail.test', $row['email']);
    }

    public function test_a_walk_in_shows_the_guests_number_never_the_partners(): void
    {
        $this->book(['user_id' => $this->partner->id, 'channel' => 'offline', 'guest_name' => 'Sova', 'guest_phone' => '9123456780']);

        $row = $this->feed()[0];
        $this->assertSame('9123456780', $row['phone']);
        $this->assertNull($row['email']);
    }

    public function test_a_walk_in_with_no_number_has_no_contact_rather_than_the_partners(): void
    {
        $this->book(['user_id' => $this->partner->id, 'channel' => 'offline', 'guest_name' => 'Walk-in']);

        $row = $this->feed()[0];
        $this->assertNull($row['phone']);
        $this->assertNull($row['email']);
    }
}
