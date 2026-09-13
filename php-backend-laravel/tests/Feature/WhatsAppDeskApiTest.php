<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Models\WhatsAppConversation;
use App\Support\JwtService;
use App\Services\WhatsAppDeskService;
use App\Services\WhatsAppReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class WhatsAppDeskApiTest extends TestCase
{
    use RefreshDatabase;

    private User $partner;
    private Venue $venue;
    private VenueCourt $court;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->partner = User::factory()->create([
            'role'         => 'partner',
            'partner_type' => 'venue',
            'status'       => 'active',
        ]);

        $this->venue = Venue::create([
            'name'        => 'Apex Arena Hyderabad',
            'location'    => 'Madhapur',
            'price'       => 1000,
            'is_active'   => true,
            'is_bookable' => true,
            'partner_id'  => $this->partner->id,
        ]);

        $this->court = VenueCourt::create([
            'venue_id'  => $this->venue->id,
            'name'      => 'Court 1 Turf',
            'kind'      => 'football',
            'price'     => 1200,
            'is_active' => true,
            'sports'    => ['football', 'cricket'],
        ]);

        $secret = config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me');
        $this->token = JwtService::issueForUser($this->partner, $secret);
    }

    public function test_inbound_message_intake_creates_conversation_and_extracts_intent(): void
    {
        $desk = app(WhatsAppDeskService::class);

        $conv = $desk->handleInboundMessage(
            '+919876543210',
            'Hi, is Court 1 available today from 6pm to 7pm for cricket?',
            'wamid_test_123',
            'Virat Kohli',
            $this->venue->id
        );

        $this->assertNotNull($conv);
        $this->assertEquals('+919876543210', $conv->phone_number);
        $this->assertEquals('Virat Kohli', $conv->customer_name);
        $this->assertEquals('needs_action', $conv->status);
        $this->assertTrue($conv->isWindowActive());

        $this->assertDatabaseHas('whatsapp_messages', [
            'conversation_id' => $conv->id,
            'direction'       => 'inbound',
            'body'            => 'Hi, is Court 1 available today from 6pm to 7pm for cricket?',
        ]);

        $this->assertDatabaseHas('whatsapp_intent_extractions', [
            'conversation_id' => $conv->id,
            'intent_type'     => 'booking_enquiry',
            'detected_sport'  => 'cricket',
        ]);
    }

    public function test_hold_slot_enforces_2_minute_ttl_and_blocks_conflicts(): void
    {
        $conv = WhatsAppConversation::create([
            'partner_id'        => $this->partner->id,
            'venue_id'          => $this->venue->id,
            'phone_number'      => '+919876543210',
            'customer_name'     => 'Rohit Sharma',
            'status'            => 'active',
            'window_expires_at' => now()->addHours(24),
        ]);

        $today = Carbon::today()->toDateString();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/partner/venues/{$this->venue->id}/whatsapp/conversations/{$conv->id}/hold-slot", [
                'court_id'   => $this->court->id,
                'slot_date'  => $today,
                'start_time' => '18:00:00',
                'end_time'   => '19:00:00',
                'price'      => 1200,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.seconds_remaining', WhatsAppReservationService::HOLD_DURATION_MINUTES * 60);

        $bookingId = $response->json('data.booking_id');
        $booking = Booking::find($bookingId);
        $this->assertEquals('hold', $booking->status);
        $this->assertNotNull($booking->reserved_until);

        // Booking holds strictly for 2 minutes: reserved_until is within 2 minutes of now
        $this->assertLessThanOrEqual(
            WhatsAppReservationService::HOLD_DURATION_MINUTES * 60,
            now()->diffInSeconds($booking->reserved_until)
        );

        // Conflict check: trying to hold the exact same slot again must fail with 422
        $conv2 = WhatsAppConversation::create([
            'partner_id'        => $this->partner->id,
            'venue_id'          => $this->venue->id,
            'phone_number'      => '+919999988888',
            'customer_name'     => 'Hardik Pandya',
            'status'            => 'active',
            'window_expires_at' => now()->addHours(24),
        ]);

        $conflictResp = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/partner/venues/{$this->venue->id}/whatsapp/conversations/{$conv2->id}/hold-slot", [
                'court_id'   => $this->court->id,
                'slot_date'  => $today,
                'start_time' => '18:00:00',
                'end_time'   => '19:00:00',
                'price'      => 1200,
            ]);

        $conflictResp->assertStatus(422);
    }

    public function test_send_payment_link_and_manual_paid_reconciliation(): void
    {
        $conv = WhatsAppConversation::create([
            'partner_id'        => $this->partner->id,
            'venue_id'          => $this->venue->id,
            'phone_number'      => '+919876543210',
            'customer_name'     => 'KL Rahul',
            'status'            => 'active',
            'window_expires_at' => now()->addHours(24),
        ]);

        $today = Carbon::today()->toDateString();

        // 1. Hold Slot
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/partner/venues/{$this->venue->id}/whatsapp/conversations/{$conv->id}/hold-slot", [
                'court_id'   => $this->court->id,
                'slot_date'  => $today,
                'start_time' => '20:00:00',
                'end_time'   => '21:00:00',
                'price'      => 1200,
            ])->assertStatus(200);

        // 2. Send Payment Link
        $linkResp = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/partner/venues/{$this->venue->id}/whatsapp/conversations/{$conv->id}/send-payment-link", [
                'amount' => 1200,
            ]);
        $linkResp->assertStatus(200);
        $linkResp->assertJsonPath('status', 'success');

        // 3. Mark as Paid (Cash)
        $paidResp = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/partner/venues/{$this->venue->id}/whatsapp/conversations/{$conv->id}/mark-paid", [
                'method' => 'cash',
            ]);
        $paidResp->assertStatus(200);
        $paidResp->assertJsonPath('data.status', 'confirmed');

        $conv->refresh();
        $this->assertEquals('converted', $conv->status);
        $this->assertNull($conv->active_booking_id);
    }
}
