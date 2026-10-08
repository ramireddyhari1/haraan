<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBlock;
use App\Models\VenueCourt;
use App\Models\VenueSlot;
use App\Support\BusinessClock;
use App\Support\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The desk takes one court off sale for a window — maintenance, private hire — from the
 * walk-in sheet. It can never block over a customer, and the block stops walk-ins.
 */
final class PartnerCourtBlockTest extends TestCase
{
    use RefreshDatabase;

    private User $partner;

    private Venue $venue;

    private VenueCourt $court;

    private VenueCourt $other;

    private string $token;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();
        $this->partner = User::factory()->create(['role' => 'partner', 'partner_type' => 'venue', 'status' => 'active']);
        $this->venue = Venue::create([
            'name' => 'Block Turf', 'location' => 'Madhapur', 'price' => 500,
            'is_active' => true, 'is_bookable' => true, 'partner_id' => $this->partner->id,
        ]);
        $this->court = VenueCourt::create(['venue_id' => $this->venue->id, 'name' => 'VADI', 'price' => 500, 'is_active' => true]);
        $this->other = VenueCourt::create(['venue_id' => $this->venue->id, 'name' => 'padi', 'price' => 500, 'is_active' => true]);
        foreach (['6:00 AM', '7:00 AM', '8:00 AM'] as $t) {
            VenueSlot::create(['venue_id' => $this->venue->id, 'time' => $t, 'price' => 500, 'capacity' => 2]);
        }
        $this->date = BusinessClock::todayDate()->addDay()->toDateString();
        $secret = config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me');
        $this->token = JwtService::issueForUser($this->partner, $secret);
    }

    private function block(array $over = [])
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/partner/venues/{$this->venue->id}/court-blocks", array_merge([
                'court_id' => $this->court->id, 'date' => $this->date,
                'start' => '06:00', 'end' => '08:00', 'kind' => 'maintenance', 'note' => 'Net repair',
            ], $over));
    }

    public function test_blocks_a_court_window_and_the_grid_shows_it(): void
    {
        $this->block()->assertCreated()
            ->assertJsonPath('data.label', 'Net repair')
            ->assertJsonPath('data.removable', true);

        $grid = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/partner/venues/{$this->venue->id}/day?date={$this->date}")->assertOk()->json('slots');

        $cell = fn (int $i, int $court) => collect($grid[$i]['courts'])->firstWhere('court_id', $court);
        $this->assertFalse($cell(0, $this->court->id)['allowed']);
        $this->assertSame('Maintenance', $cell(1, $this->court->id)['block']['reason']);
        $this->assertNull($cell(2, $this->court->id)['block']);   // 8 AM is outside the window
        $this->assertTrue($cell(0, $this->other->id)['allowed']); // other court untouched
    }

    public function test_a_blocked_court_refuses_a_walk_in(): void
    {
        $this->block()->assertCreated();
        $seven = VenueSlot::where('time', '7:00 AM')->first();

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/partner/venues/{$this->venue->id}/bookings", [
                'slotId' => $seven->id, 'courtId' => $this->court->id, 'date' => $this->date,
                'guestName' => 'Desk', 'guestPhone' => '9000000001', 'paymentMethod' => 'cash',
            ])->assertStatus(409);
    }

    public function test_cannot_block_over_a_booking(): void
    {
        Booking::forceCreate([
            'booking_type' => 'venue', 'venue_id' => $this->venue->id, 'venue_court_id' => $this->court->id,
            'user_id' => User::factory()->create()->id, 'channel' => 'online', 'status' => 'CONFIRMED',
            'payment_status' => 'paid', 'slot_date' => $this->date, 'start_time' => '07:00', 'end_time' => '08:00',
            'quantity' => 1, 'total_amount' => 500, 'amount_paid' => 500,
        ]);

        $this->block()->assertStatus(409)->assertJsonPath('message', 'VADI is booked at 7:00 AM. Cancel or move that booking first.');
        $this->assertSame(0, VenueBlock::count());
    }

    public function test_rejects_past_days_backwards_windows_and_double_blocks(): void
    {
        $this->block(['date' => BusinessClock::todayDate()->subDay()->toDateString()])->assertStatus(422);
        $this->block(['start' => '08:00', 'end' => '07:00'])->assertStatus(422);
        $this->block()->assertCreated();
        $this->block(['start' => '07:00', 'end' => '08:00'])->assertStatus(409);
    }

    public function test_unblock_only_desk_made_blocks(): void
    {
        $id = $this->block()->json('data.id');
        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->deleteJson("/api/partner/venues/{$this->venue->id}/court-blocks/{$id}")->assertOk();
        $this->assertSame(0, VenueBlock::count());

        $weekly = VenueBlock::create([
            'venue_id' => $this->venue->id, 'venue_court_id' => $this->court->id, 'kind' => 'academy',
            'starts_on' => $this->date, 'ends_on' => BusinessClock::todayDate()->addMonth()->toDateString(),
            'weekday' => 1, 'start_time' => '06:00', 'end_time' => '07:00',
        ]);
        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->deleteJson("/api/partner/venues/{$this->venue->id}/court-blocks/{$weekly->id}")->assertStatus(403);
    }

    public function test_another_partners_venue_is_not_found(): void
    {
        $stranger = User::factory()->create(['role' => 'partner', 'partner_type' => 'venue', 'status' => 'active']);
        $secret = config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me');

        $this->withHeader('Authorization', 'Bearer '.JwtService::issueForUser($stranger, $secret))
            ->postJson("/api/partner/venues/{$this->venue->id}/court-blocks", [
                'court_id' => $this->court->id, 'date' => $this->date, 'start' => '06:00', 'end' => '07:00', 'kind' => 'private',
            ])->assertNotFound();
    }
}
