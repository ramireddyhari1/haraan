<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Models\VenueSlot;
use App\Models\WhatsAppBotSession;
use App\Models\WhatsAppConversation;
use App\Services\InboundMessages;
use App\Services\WhatsAppDeskService;
use App\Support\BusinessClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A customer books a court entirely inside WhatsApp: location → venue → sport → day →
 * length → time → confirm → pay → confirmed, with the chat visible in the venue's desk.
 */
final class WhatsAppBookingBotTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'rzp_bot_webhook';

    private const CUSTOMER = '+919812345678';

    private Venue $venue;

    private VenueCourt $court;

    private User $partner;

    /** When true, the fake WhatsApp API refuses interactive messages (as MSG91 might). */
    private bool $rejectInteractive = false;

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
            if (str_contains($request->url(), 'api.razorpay.com/v1/payment_links')) {
                return Http::response(['id' => 'plink_Bot1', 'short_url' => 'https://rzp.io/i/bot1'], 200);
            }

            if ($this->rejectInteractive && ($request['type'] ?? null) === 'interactive') {
                return Http::response(['error' => ['message' => 'Unsupported']], 400);
            }

            return Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200);
        });

        $this->partner = User::factory()->create(['role' => 'partner', 'partner_type' => 'venue', 'status' => 'active']);

        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $this->venue = Venue::create([
            'name' => 'Apex Arena',
            'location' => 'Madhapur',
            'city' => 'Hyderabad',
            'price' => 1000,
            'latitude' => 17.4483,
            'longitude' => 78.3915,
            'images' => ['https://example.com/turf.jpg'],
            'status' => 'published',
            'published_at' => now(),
            'is_active' => true,
            'is_bookable' => true,
            'slot_minutes' => 60,
            'hours_json' => array_fill_keys($days, ['open' => '06:00', 'close' => '23:00']),
            'partner_id' => $this->partner->id,
        ]);

        $this->court = VenueCourt::create([
            'venue_id' => $this->venue->id, 'name' => 'Court 1', 'kind' => 'football',
            'price' => 1200, 'is_active' => true, 'sports' => ['football', 'cricket'],
        ]);

        VenueSlot::create(['venue_id' => $this->venue->id, 'day' => 'Daily', 'time' => '6:00 AM', 'is_available' => true, 'price' => 1200, 'capacity' => 1]);
    }

    /** @param array<string, mixed> $extra */
    private function say(string $text, array $extra = []): void
    {
        app(InboundMessages::class)->handle('whatsapp', self::CUSTOMER, $text, 'wamid.'.uniqid(), null, 'Rohit Sharma', $extra);
    }

    private function tap(string $id, string $title = ''): void
    {
        $this->say($title, ['reply_id' => $id]);
    }

    private function botSession(): WhatsAppBotSession
    {
        return WhatsAppBotSession::where('phone', self::CUSTOMER)->firstOrFail();
    }

    private function tomorrow(): string
    {
        return BusinessClock::todayDate()->addDay()->toDateString();
    }

    private function sentInteractive(string $type): bool
    {
        return Http::recorded()->contains(fn ($p) => ($p[0]['type'] ?? null) === 'interactive'
            && ($p[0]['interactive']['type'] ?? null) === $type);
    }

    public function test_a_customer_books_and_pays_entirely_in_the_chat(): void
    {
        $this->say('hi');
        $this->assertSame('await_location', $this->botSession()->state);
        $this->assertTrue($this->sentInteractive('location_request_message'));

        $this->say('', ['location' => ['lat' => 17.4500, 'lng' => 78.3900]]);
        $this->assertSame('await_venue', $this->botSession()->state);
        $this->assertTrue(Http::recorded()->contains(fn ($p) => str_contains(json_encode($p[0]->data()), '"v:'.$this->venue->id.'"')));

        $this->tap('v:'.$this->venue->id, 'Apex Arena');
        $this->assertSame('await_sport', $this->botSession()->state, 'the court runs two sports, so the bot asks which');
        $this->assertDatabaseHas('whatsapp_conversations', ['venue_id' => $this->venue->id, 'phone_number' => self::CUSTOMER]);

        $this->tap('s:football', 'Football');
        $this->assertSame('await_date', $this->botSession()->state);

        $this->tap('d:'.$this->tomorrow(), 'Tomorrow');
        $this->assertSame('await_length', $this->botSession()->state);

        $this->tap('l:60', '1 hour');
        $this->assertSame('await_time', $this->botSession()->state);

        // Nine times to a page: 6 PM is on the second.
        $this->tap('more', 'Later times');
        $this->assertSame('await_time', $this->botSession()->state);
        $this->tap('t:1080', '6 PM – 7 PM');
        $this->assertSame('await_confirm', $this->botSession()->state, 'one free court, so straight to the summary');

        $this->tap('pay', 'Confirm & pay');
        $session = $this->botSession();
        $this->assertSame('await_payment', $session->state);

        $booking = Booking::findOrFail($session->booking_id);
        $this->assertSame('online', $booking->channel);
        $this->assertSame('PENDING', $booking->status);
        $this->assertSame((int) $this->court->id, (int) $booking->venue_court_id);
        $this->assertSame('18:00', substr((string) $booking->start_time, 0, 5));
        $this->assertEquals(1200.0, (float) $booking->total_amount);

        // The booking is the customer's own account, made from their phone.
        $customer = User::findOrFail($booking->user_id);
        $this->assertSame(self::CUSTOMER, $customer->phone);
        $this->assertNotSame($this->partner->id, $customer->id);

        // The link went out, and the venue's desk sees the hold.
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'graph.facebook.com')
            && str_contains((string) ($r['text']['body'] ?? ''), 'https://rzp.io/i/bot1'));
        $conv = WhatsAppConversation::where('venue_id', $this->venue->id)->where('phone_number', self::CUSTOMER)->firstOrFail();
        $this->assertSame('hold_active', $conv->status);

        // Razorpay reports the payment.
        $payload = [
            'event' => 'payment_link.paid',
            'payload' => [
                'payment_link' => ['entity' => ['id' => 'plink_Bot1', 'notes' => ['booking_id' => (string) $booking->id]]],
                'payment' => ['entity' => ['id' => 'pay_Bot1', 'amount' => 120000]],
            ],
        ];
        $raw = json_encode($payload);
        $this->call('POST', '/api/webhooks/razorpay', [], [], [], [
            'HTTP_X-Razorpay-Signature' => hash_hmac('sha256', $raw, self::WEBHOOK_SECRET),
            'CONTENT_TYPE' => 'application/json',
        ], $raw)->assertOk();

        $this->assertSame('CONFIRMED', $booking->fresh()->status);
        $this->assertSame('paid', $booking->fresh()->payment_status);
        $this->assertSame('done', $this->botSession()->state);
        $this->assertSame('converted', $conv->fresh()->status);
    }

    public function test_typed_numbers_work_when_the_provider_refuses_lists(): void
    {
        $this->rejectInteractive = true;

        $this->say('hi');
        $this->say('', ['location' => ['lat' => 17.4500, 'lng' => 78.3900]]);

        // The venue list went out as numbered text instead.
        Http::assertSent(fn (HttpRequest $r) => ($r['type'] ?? null) === 'text'
            && str_contains((string) ($r['text']['body'] ?? ''), '1. Apex Arena'));

        $this->say('1');
        $this->assertSame('await_sport', $this->botSession()->state);
        $this->assertSame((int) $this->venue->id, (int) $this->botSession()->venue_id);
    }

    public function test_no_venue_nearby_says_so_and_asks_again(): void
    {
        $this->say('hi');
        $this->say('', ['location' => ['lat' => 28.6139, 'lng' => 77.2090]]); // Delhi, ~1,250 km away

        $this->assertSame('await_location', $this->botSession()->state);
        Http::assertSent(fn (HttpRequest $r) => str_contains((string) ($r['text']['body'] ?? ''), 'near that location'));
    }

    public function test_a_staff_reply_in_the_desk_pauses_the_bot_until_menu(): void
    {
        $this->say('hi');
        $this->say('', ['location' => ['lat' => 17.4500, 'lng' => 78.3900]]);
        $this->tap('v:'.$this->venue->id);

        $conv = WhatsAppConversation::where('phone_number', self::CUSTOMER)->firstOrFail();
        app(WhatsAppDeskService::class)->sendOutboundMessage($conv, 'Hi Rohit, this is Apex Arena!', $this->partner);
        $this->assertTrue($this->botSession()->isPaused());

        $sentBefore = Http::recorded()->count();
        $this->say('ok thanks');
        $this->assertSame($sentBefore, Http::recorded()->count(), 'the bot stays quiet while staff are talking');

        $this->say('menu');
        $this->assertFalse($this->botSession()->isPaused());
        $this->assertSame('await_location', $this->botSession()->state);
    }

    public function test_someone_who_sent_stop_gets_silence_not_the_bot(): void
    {
        \App\Models\MessagingOptOut::record('whatsapp', self::CUSTOMER, null, 'stop_keyword');

        $this->say('hi');

        $this->assertDatabaseMissing('whatsapp_bot_sessions', ['phone' => self::CUSTOMER, 'state' => 'await_location']);
        Http::assertNothingSent();
    }

    public function test_a_partners_keyword_auto_reply_beats_starting_the_bot(): void
    {
        $plan = \App\Models\PartnerPlan::create([
            'code' => 'growth', 'name' => 'Growth', 'price_inr' => 499,
            'included_conversations' => 500, 'features' => [\App\Models\PartnerPlan::FEATURE_INBOUND],
        ]);
        \App\Models\PartnerSubscription::create([
            'partner_id' => $this->partner->id, 'plan_id' => $plan->id,
            'status' => \App\Models\PartnerSubscription::STATUS_ACTIVE, 'current_period_end' => now()->addMonth(),
        ]);
        \App\Models\AutomationRule::create([
            'partner_id' => $this->partner->id, 'channel' => 'whatsapp', 'name' => 'Parking',
            'trigger_type' => 'keyword', 'match_type' => 'contains', 'keywords' => ['parking'],
            'reply_body' => 'Free parking behind the turf.', 'is_active' => true,
        ]);
        // Attributes this number to the partner, as a past conversation would.
        \App\Models\MessageConversation::create([
            'channel' => 'whatsapp', 'recipient' => self::CUSTOMER, 'partner_id' => $this->partner->id, 'category' => 'service',
            'opened_at' => now()->subHour(), 'expires_at' => now()->addHours(20),
        ]);

        $this->say('is there parking');
        Http::assertSent(fn (HttpRequest $r) => str_contains((string) ($r['text']['body'] ?? ''), 'Free parking'));
        $this->assertDatabaseMissing('whatsapp_bot_sessions', ['phone' => self::CUSTOMER, 'state' => 'await_location']);
    }

    public function test_an_unmatched_answer_repeats_the_question(): void
    {
        $this->say('hi');
        $this->say('', ['location' => ['lat' => 17.4500, 'lng' => 78.3900]]);
        $this->say('banana');

        $this->assertSame('await_venue', $this->botSession()->state);
        Http::assertSent(fn (HttpRequest $r) => str_contains((string) ($r['text']['body'] ?? ''), 'Please pick one'));
    }
}
