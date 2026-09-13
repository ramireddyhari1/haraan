<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ShiftSession;
use App\Models\User;
use App\Models\Venue;
use App\Services\BookingLedger;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ShiftApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Venue $venue;
    private BookingLedger $ledger;
    private ShiftService $shifts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = app(BookingLedger::class);
        $this->shifts = app(ShiftService::class);

        $this->owner = User::create([
            'name' => 'Kiran Kumar',
            'email' => 'kiran@turf.test',
            'password' => Hash::make('secret123'),
            'role' => 'PARTNER',
            'partner_type' => 'venue',
            'status' => 'active',
        ]);

        $this->venue = Venue::create([
            'name' => 'Kiran Turf Arena',
            'location' => 'Indiranagar',
            'price' => 1200,
            'is_active' => true,
            'is_bookable' => true,
            'partner_id' => $this->owner->id,
        ]);
    }

    private function partnerToken(User $user): string
    {
        $secret = (string) config('app.jwt_secret', env('JWT_SECRET', 'change_me'));
        return \App\Support\JwtService::issueForUser($user, $secret);
    }

    public function test_can_query_current_shift_when_none_open(): void
    {
        $token = $this->partnerToken($this->owner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/partner/venues/{$this->venue->id}/shift/current");

        $response->assertOk()
            ->assertJson([
                'has_open_shift' => false,
                'venue_id' => $this->venue->id,
                'venue_name' => $this->venue->name,
            ]);
    }

    public function test_can_open_shift_with_cash_float(): void
    {
        $token = $this->partnerToken($this->owner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/partner/venues/{$this->venue->id}/shift/open", [
                'opening_float' => 1500.0,
                'note' => 'Shift 1 morning start',
            ]);

        $response->assertOk()
            ->assertJson([
                'has_open_shift' => true,
                'shift' => [
                    'opening_float' => 1500.0,
                    'expected_cash' => 1500.0,
                    'is_open' => true,
                ],
            ]);

        $this->assertDatabaseHas('shift_sessions', [
            'venue_id' => $this->venue->id,
            'user_id' => $this->owner->id,
            'opening_float' => 1500.0,
            'closed_at' => null,
        ]);
    }

    public function test_cash_drop_decrements_expected_cash(): void
    {
        $token = $this->partnerToken($this->owner);

        // 1. Open shift with 1000 float
        $this->shifts->open($this->owner, (int) $this->venue->id, 1000.0);

        // 2. Take a cash booking of 1200
        $booking = Booking::create([
            'quantity' => 1,
            'total_amount' => 1200,
            'status' => 'CONFIRMED',
            'booking_type' => 'venue',
            'user_id' => $this->owner->id,
            'venue_id' => $this->venue->id,
            'slot_date' => today()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'channel' => 'offline',
        ]);
        $this->ledger->collect($booking, 1200, 'cash', $this->owner);

        // Expected before drop: 1000 + 1200 = 2200
        $check = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/partner/venues/{$this->venue->id}/shift/current");
        $check->assertOk()
            ->assertJsonPath('shift.expected_cash', 2200);

        // 3. Drop 500 for generator fuel
        $dropResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/partner/venues/{$this->venue->id}/shift/drop", [
                'amount' => 500.0,
                'category' => 'fuel',
                'reason' => 'Diesel for generator',
            ]);

        $dropResponse->assertOk()
            ->assertJsonPath('shift.total_drops', 500)
            ->assertJsonPath('shift.expected_cash', 1700);

        $this->assertDatabaseHas('shift_drops', [
            'amount' => 500.0,
            'category' => 'fuel',
        ]);
    }

    public function test_can_close_shift_and_log_variance(): void
    {
        $token = $this->partnerToken($this->owner);

        // Shift with 1000 float
        $shift = $this->shifts->open($this->owner, (int) $this->venue->id, 1000.0);

        // Booking 800 cash -> expected = 1800
        $booking = Booking::create([
            'quantity' => 1,
            'total_amount' => 800,
            'status' => 'CONFIRMED',
            'booking_type' => 'venue',
            'user_id' => $this->owner->id,
            'venue_id' => $this->venue->id,
            'slot_date' => today()->toDateString(),
            'start_time' => '14:00',
            'end_time' => '15:00',
            'channel' => 'offline',
        ]);
        $this->ledger->collect($booking, 800, 'cash', $this->owner);

        // Physical count: 1750 (50 short)
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/partner/venues/{$this->venue->id}/shift/close", [
                'counted_cash' => 1750.0,
                'note' => '50 short on change',
                'denominations' => ['500' => 3, '200' => 1, '50' => 1],
            ]);

        $response->assertOk()
            ->assertJson([
                'variance' => -50.0,
                'variance_label' => 'Short',
                'expected_cash' => 1800.0,
                'counted_cash' => 1750.0,
            ]);

        $this->assertNotNull($shift->fresh()->closed_at);
        $this->assertSame(-50.0, (float) $shift->fresh()->variance);
    }
}
