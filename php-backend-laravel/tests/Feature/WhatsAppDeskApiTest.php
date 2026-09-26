<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppPaymentLink;
use App\Models\WhatsAppQuickReply;
use App\Services\BookingService;
use App\Services\InboundMessages;
use App\Services\WhatsAppIntentEngine;
use App\Services\WhatsAppReservationService;
use App\Support\BusinessClock;
use App\Support\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The WhatsApp Desk end to end: a customer's message reaches the right venue's desk,
 * a hold is a real booking every other channel respects, the payment link is a real
 * Razorpay link, and payment — by link, webhook or at the counter — confirms it.
 */
final class WhatsAppDeskApiTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'rzp_desk_webhook';

    private const CUSTOMER = '+919876543210';

    private User $partner;

    private Venue $venue;

    private VenueCourt $court;

    private string $token;

    /** What Razorpay reports for a payment link when asked. */
    private string $linkStatus = 'created';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => 'rzp_test_secret',
            'services.razorpay.webhook_secret' => self::WEBHOOK_SECRET,
            'services.whatsapp.enabled' => true,
            'services.whatsapp.driver' => 'meta',
            'services.whatsapp.phone_number_id' => '123456789',
            'services.whatsapp.access_token' => 'meta-token',
        ]);

        Http::fake(function (HttpRequest $request) {
            $url = $request->url();

            if (str_contains($url, 'api.razorpay.com/v1/payment_links') && $request->method() === 'POST') {
                return Http::response(['id' => 'plink_Desk123', 'short_url' => 'https://rzp.io/i/desk123'], 200);
            }

            if (str_contains($url, 'api.razorpay.com/v1/payment_links/')) {
                return Http::response([
                    'id' => 'plink_Desk123',
                    'status' => $this->linkStatus,
                    'amount_paid' => $this->linkStatus === 'paid' ? 120000 : 0,
                    'payments' => $this->linkStatus === 'paid'
                        ? [['payment_id' => 'pay_Desk999', 'status' => 'captured']]
                        : [],
                ], 200);
            }

            return Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200);
        });

        $this->partner = User::factory()->create([
            'role' => 'partner',
            'partner_type' => 'venue',
            'status' => 'active',
            'phone' => '+919000000001',
        ]);

        $this->venue = Venue::create([
            'name' => 'Apex Arena',
            'location' => 'Madhapur',
            'address' => '12 Hitech City Road, Madhapur',
            'price' => 1000,
            'is_active' => true,
            'is_bookable' => true,
            'partner_id' => $this->partner->id,
        ]);

        $this->court = VenueCourt::create([
            'venue_id' => $this->venue->id,
            'name' => 'Court 1',
            'kind' => 'football',
            'price' => 1200,
            'is_active' => true,
            'sports' => ['football', 'cricket'],
        ]);

        $secret = config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me');
        $this->token = JwtService::issueForUser($this->partner, $secret);
    }

    private function api(string $method, string $path, array $body = [])
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->json($method, "/api/partner/venues/{$this->venue->id}/whatsapp{$path}", $body);
    }

    private function chat(): WhatsAppConversation
    {
        return WhatsAppConversation::create([
            'partner_id' => $this->partner->id,
            'venue_id' => $this->venue->id,
            'phone_number' => self::CUSTOMER,
            'customer_name' => 'Rohit',
            'status' => 'needs_action',
            'window_expires_at' => now()->addHours(20),
        ]);
    }

    private function tomorrow(): string
    {
        return BusinessClock::todayDate()->addDay()->toDateString();
    }

    private function hold(WhatsAppConversation $conv, string $start = '18:00', string $end = '19:00')
    {
        return $this->api('POST', "/conversations/{$conv->id}/hold-slot", [
            'court_id' => $this->court->id,
            'slot_date' => $this->tomorrow(),
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }

    // --- Intake --------------------------------------------------------------

    public function test_a_returning_customers_message_reaches_their_venues_desk(): void
    {
        Booking::create([
            'booking_type' => 'venue', 'venue_id' => $this->venue->id, 'venue_court_id' => $this->court->id,
            'user_id' => $this->partner->id, 'channel' => 'offline', 'guest_phone' => '98765 43210',
            'slot_date' => now()->subDays(3)->toDateString(), 'start_time' => '07:00', 'end_time' => '08:00',
            'status' => 'CONFIRMED', 'total_amount' => 1200, 'quantity' => 1,
        ]);

        app(InboundMessages::class)->handle('whatsapp', self::CUSTOMER, 'Is court 1 free tomorrow 7-8 pm?', 'wamid.in1', null, 'Rohit S');

        $conv = WhatsAppConversation::where('venue_id', $this->venue->id)->where('phone_number', self::CUSTOMER)->first();
        $this->assertNotNull($conv, 'the message should land on the venue desk');
        $this->assertSame((int) $this->partner->id, (int) $conv->partner_id);
        $this->assertSame('Rohit S', $conv->customer_name);
        $this->assertSame('needs_action', $conv->status);
        $this->assertSame(1, $conv->unread_count);

        $intent = $conv->latestIntent()->firstOrFail();
        $this->assertSame('booking_enquiry', $intent->intent_type);
        $this->assertSame($this->court->id, (int) $intent->resolved_court_id);
        $this->assertSame($this->tomorrow(), substr((string) $intent->detected_date, 0, 10));
        $this->assertSame('19:00', substr((string) $intent->detected_start_time, 0, 5));

        // The webhook retrying the same message doesn't record it twice.
        app(InboundMessages::class)->handle('whatsapp', self::CUSTOMER, 'Is court 1 free tomorrow 7-8 pm?', 'wamid.in1');
        $this->assertSame(1, $conv->messages()->count());
    }

    public function test_a_message_naming_the_venue_reference_reaches_it(): void
    {
        app(InboundMessages::class)->handle('whatsapp', '+919111111111', "Hi, I'd like to book #V{$this->venue->id}");

        $this->assertDatabaseHas('whatsapp_conversations', ['venue_id' => $this->venue->id, 'phone_number' => '+919111111111']);
    }

    public function test_a_stranger_is_not_dropped_into_some_venues_desk(): void
    {
        // A second partner with a venue — the old intake put strangers in "the first venue".
        $other = User::factory()->create(['role' => 'partner', 'partner_type' => 'venue', 'status' => 'active']);
        Venue::create(['name' => 'Other Turf', 'location' => 'Kondapur', 'price' => 800, 'is_active' => true, 'partner_id' => $other->id]);

        app(InboundMessages::class)->handle('whatsapp', '+919222222222', 'hello');

        $this->assertDatabaseCount('whatsapp_conversations', 0);
    }

    public function test_the_reader_reports_only_what_the_message_says(): void
    {
        $engine = app(WhatsAppIntentEngine::class);

        $hi = $engine->analyze($this->venue, 'hi');
        $this->assertNull($hi['detected_date']);
        $this->assertNull($hi['detected_start_time']);
        $this->assertNull($hi['calculated_rate']);

        $ask = $engine->analyze($this->venue, 'book court 1 day after tomorrow 6:30 to 8 pm');
        $this->assertSame(BusinessClock::todayDate()->addDays(2)->toDateString(), $ask['detected_date']);
        $this->assertSame('18:30:00', $ask['detected_start_time']);
        $this->assertSame('20:00:00', $ask['detected_end_time']);
        $this->assertSame(90, $ask['detected_duration_minutes']);
        $this->assertSame($this->court->id, $ask['resolved_court_id']);
        $this->assertEquals(1800.0, $ask['calculated_rate']); // ₹1,200/hr × 1.5h
    }

    // --- Holds are real bookings -------------------------------------------------

    public function test_a_hold_is_a_venue_booking_every_channel_respects(): void
    {
        $conv = $this->chat();

        $res = $this->hold($conv)->assertOk();
        $res->assertJsonPath('data.hold_total_seconds', WhatsAppReservationService::holdMinutes() * 60);
        $res->assertJsonPath('data.amount', 1200);

        $booking = Booking::findOrFail($res->json('data.booking_id'));
        $this->assertSame('venue', $booking->booking_type);
        $this->assertSame('PENDING', $booking->status);
        $this->assertSame('whatsapp', $booking->channel);
        $this->assertSame(self::CUSTOMER, $booking->guest_phone);
        $this->assertTrue($booking->reserved_until->isFuture());

        // The app/web checkout's own rule now sees the court as taken…
        $this->assertFalse(app(BookingService::class)->isCourtHourFree(
            $this->venue->id, $this->court->id, $this->tomorrow(), 18 * 60, 19 * 60,
        ));

        // …and so does a second desk chat.
        $other = WhatsAppConversation::create([
            'partner_id' => $this->partner->id, 'venue_id' => $this->venue->id,
            'phone_number' => '+919999988888', 'status' => 'active', 'window_expires_at' => now()->addHours(5),
        ]);
        $this->hold($other, '18:30', '19:30')->assertStatus(409);

        $this->assertSame('hold_active', $conv->fresh()->status);
    }

    public function test_an_app_booking_in_uppercase_blocks_the_desk(): void
    {
        Booking::create([
            'booking_type' => 'venue', 'venue_id' => $this->venue->id, 'venue_court_id' => $this->court->id,
            'user_id' => $this->partner->id, 'channel' => 'online',
            'slot_date' => $this->tomorrow(), 'start_time' => '18:00', 'end_time' => '19:00',
            'status' => 'CONFIRMED', 'total_amount' => 1200, 'quantity' => 1,
        ]);

        $this->hold($this->chat())->assertStatus(409);
    }

    public function test_a_time_that_has_passed_cannot_be_held(): void
    {
        $this->api('POST', "/conversations/{$this->chat()->id}/hold-slot", [
            'court_id' => $this->court->id,
            'slot_date' => BusinessClock::todayDate()->subDay()->toDateString(),
            'start_time' => '18:00',
            'end_time' => '19:00',
        ])->assertStatus(422);
    }

    // --- Payment -------------------------------------------------------------------

    public function test_the_payment_link_is_a_real_razorpay_link_sent_in_the_chat(): void
    {
        $conv = $this->chat();
        $this->hold($conv)->assertOk();

        $this->api('POST', "/conversations/{$conv->id}/send-payment-link")
            ->assertOk()
            ->assertJsonPath('data.short_url', 'https://rzp.io/i/desk123');

        $this->assertDatabaseHas('whatsapp_payment_links', [
            'conversation_id' => $conv->id,
            'razorpay_payment_link_id' => 'plink_Desk123',
            'status' => 'issued',
        ]);

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'api.razorpay.com/v1/payment_links')
            && $r['amount'] === 120000
            && $r['notes']['source'] === 'whatsapp_desk');
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'graph.facebook.com')
            && str_contains((string) ($r['text']['body'] ?? ''), 'https://rzp.io/i/desk123'));
    }

    public function test_the_webhook_confirms_a_paid_link_and_closes_the_chat(): void
    {
        $conv = $this->chat();
        $bookingId = $this->hold($conv)->json('data.booking_id');
        $this->api('POST', "/conversations/{$conv->id}/send-payment-link")->assertOk();

        $payload = [
            'event' => 'payment_link.paid',
            'payload' => [
                'payment_link' => ['entity' => ['id' => 'plink_Desk123', 'notes' => ['booking_id' => (string) $bookingId]]],
                'payment' => ['entity' => ['id' => 'pay_Desk999', 'amount' => 120000]],
            ],
        ];
        $raw = json_encode($payload);
        $this->call('POST', '/api/webhooks/razorpay', [], [], [], [
            'HTTP_X-Razorpay-Signature' => hash_hmac('sha256', $raw, self::WEBHOOK_SECRET),
            'CONTENT_TYPE' => 'application/json',
        ], $raw)->assertOk();

        $booking = Booking::findOrFail($bookingId);
        $this->assertSame('CONFIRMED', $booking->status);
        $this->assertSame('paid', $booking->payment_status);
        $this->assertEquals(1200.0, (float) $booking->amount_paid);
        $this->assertSame('pay_Desk999', $booking->razorpay_payment_id);

        $conv->refresh();
        $this->assertSame('converted', $conv->status);
        $this->assertNull($conv->active_booking_id);
        $this->assertSame('paid', WhatsAppPaymentLink::where('booking_id', $bookingId)->value('status'));
    }

    public function test_the_desk_can_see_a_link_paid_without_a_webhook(): void
    {
        $conv = $this->chat();
        $bookingId = $this->hold($conv)->json('data.booking_id');
        $this->api('POST', "/conversations/{$conv->id}/send-payment-link")->assertOk();

        $this->api('POST', "/conversations/{$conv->id}/payment-status")->assertJsonPath('paid', false);

        $this->linkStatus = 'paid';
        $this->api('POST', "/conversations/{$conv->id}/payment-status")->assertJsonPath('paid', true);

        $this->assertSame('CONFIRMED', Booking::find($bookingId)->status);
        $this->assertSame('converted', $conv->fresh()->status);
    }

    public function test_cash_at_the_counter_confirms_through_the_ledger(): void
    {
        $conv = $this->chat();
        $bookingId = $this->hold($conv)->json('data.booking_id');

        $this->api('POST', "/conversations/{$conv->id}/mark-paid", ['method' => 'cash'])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $booking = Booking::findOrFail($bookingId);
        $this->assertSame('CONFIRMED', $booking->status);
        $this->assertSame('paid', $booking->payment_status);
        $this->assertDatabaseHas('booking_payments', ['booking_id' => $bookingId, 'method' => 'cash', 'amount' => 1200]);
        $this->assertSame('converted', $conv->fresh()->status);

        // The booking code went to the CUSTOMER's WhatsApp, never the partner's.
        Http::assertNotSent(fn (HttpRequest $r) => str_contains(json_encode($r->data()), '919000000001'));
    }

    // --- Lapsed holds ------------------------------------------------------------

    public function test_the_sweep_expires_an_unpaid_hold_and_frees_the_chat(): void
    {
        $conv = $this->chat();
        $bookingId = $this->hold($conv)->json('data.booking_id');
        Booking::whereKey($bookingId)->update(['reserved_until' => now()->subMinute()]);

        $this->artisan('whatsapp:expire-holds')->assertSuccessful();

        $this->assertSame('EXPIRED', Booking::find($bookingId)->status);
        $conv->refresh();
        $this->assertSame('active', $conv->status);
        $this->assertNull($conv->active_booking_id);

        // The generic sweep leaves desk holds to the desk's own.
        $this->assertTrue(app(BookingService::class)->isCourtHourFree($this->venue->id, $this->court->id, $this->tomorrow(), 18 * 60, 19 * 60));
    }

    public function test_the_sweep_confirms_a_lapsed_hold_whose_link_was_paid(): void
    {
        $conv = $this->chat();
        $bookingId = $this->hold($conv)->json('data.booking_id');
        $this->api('POST', "/conversations/{$conv->id}/send-payment-link")->assertOk();
        Booking::whereKey($bookingId)->update(['reserved_until' => now()->subMinute()]);

        $this->linkStatus = 'paid';
        $this->artisan('whatsapp:expire-holds')->assertSuccessful();

        $this->assertSame('CONFIRMED', Booking::find($bookingId)->status);
        $this->assertSame('converted', $conv->fresh()->status);
    }

    public function test_releasing_a_hold_frees_the_court(): void
    {
        $conv = $this->chat();
        $bookingId = $this->hold($conv)->json('data.booking_id');

        $this->api('POST', "/conversations/{$conv->id}/release-hold")->assertJsonPath('outcome', 'released');

        $this->assertSame('CANCELLED', Booking::find($bookingId)->status);
        $this->assertTrue(app(BookingService::class)->isCourtHourFree($this->venue->id, $this->court->id, $this->tomorrow(), 18 * 60, 19 * 60));
    }

    // --- Replies & scoping ----------------------------------------------------------

    public function test_quick_replies_use_the_venues_real_details_and_hide_missing_ones(): void
    {
        WhatsAppQuickReply::query()->delete();
        WhatsAppQuickReply::create(['venue_id' => null, 'shortcut' => '/location', 'category' => 'Directions', 'title' => 'Find us', 'body' => "{{venue_name}}\n{{venue_address}}"]);
        WhatsAppQuickReply::create(['venue_id' => null, 'shortcut' => '/rules', 'category' => 'Rules', 'title' => 'Rules', 'body' => '{{venue_rules}}']);

        $replies = $this->api('GET', '/quick-replies')->assertOk()->json('data');

        $this->assertCount(1, $replies, 'the venue has no rules, so /rules is hidden');
        $this->assertSame("Apex Arena\n12 Hitech City Road, Madhapur", $replies[0]['body']);
    }

    public function test_the_desk_is_scoped_to_the_partners_own_venues(): void
    {
        $other = User::factory()->create(['role' => 'partner', 'partner_type' => 'venue', 'status' => 'active']);
        $theirs = Venue::create(['name' => 'Not Yours', 'location' => 'Gachibowli', 'price' => 900, 'is_active' => true, 'partner_id' => $other->id]);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/partner/venues/{$theirs->id}/whatsapp/conversations")
            ->assertNotFound();
    }
}
