<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Event;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\BookingNotifier;
use App\Support\MessageContext;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Ticket delivery after a confirmed booking: the QR, the venue details and the
 * pass link have to reach the customer over WhatsApp, email and push, and no one
 * of them may depend on another having worked.
 */
class BookingTicketDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $partner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->partner = User::create([
            'name' => 'Ticket Partner', 'email' => 'ticket-partner@example.test',
            'password' => bcrypt('secret'), 'role' => 'PARTNER', 'status' => 'active',
            'partner_type' => 'event',
        ]);

        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.driver' => 'meta',
            'services.whatsapp.phone_number_id' => '123456789',
            'services.whatsapp.access_token' => 'meta-token',
        ]);
    }

    private function booking(array $overrides = []): Booking
    {
        $event = Event::create([
            'partner_id' => $this->partner->id, 'title' => 'Sunburn Arena', 'category' => 'Music',
            'location' => '12 Marine Drive, Fort', 'venue' => 'Phoenix Arena', 'city' => 'Mumbai',
            'date' => Carbon::parse('2026-09-12'), 'time' => '7:00 PM',
            'price' => 500, 'total_slots' => 50, 'available_slots' => 50, 'images' => [],
            'status' => 'published',
        ]);

        $buyer = User::firstOrCreate(
            ['email' => 'ticket-buyer@example.test'],
            ['name' => 'Buyer', 'password' => bcrypt('secret'), 'role' => 'user', 'status' => 'active'],
        );

        return Booking::create(array_merge([
            'user_id' => $buyer->id, 'event_id' => $event->id, 'quantity' => 2,
            'total_amount' => 1000, 'status' => 'CONFIRMED',
            'attendee_phone' => '9876543210', 'attendee_email' => 'attendee@example.test',
        ], $overrides));
    }

    /** All outbound HTTP faked: MSG91 accepted, Meta accepted. */
    private function fakeProvidersOk(): void
    {
        Http::fake([
            'control.msg91.com/*' => Http::response(['type' => 'success', 'request_id' => 'req-1'], 200),
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200),
            '*' => Http::response('', 200),
        ]);
    }

    public function test_a_confirmed_booking_delivers_the_ticket_over_whatsapp(): void
    {
        $this->fakeProvidersOk();
        $booking = $this->booking();

        app(BookingNotifier::class)->notify($booking);

        // No approved template registered in this database, so the send is the QR
        // image — which is the better message anyway, wherever it's allowed.
        Http::assertSent(function ($request) use ($booking): bool {
            $data = $request->data();

            return str_contains($request->url(), 'graph.facebook.com')
                && ($data['type'] ?? null) === 'image'
                && str_ends_with($data['image']['link'], '/t/' . $booking->ticket_code . '/qr.png')
                && str_contains($data['image']['caption'], $booking->ticket_code);
        });
    }

    public function test_an_approved_template_is_used_instead_of_free_text(): void
    {
        // Outside a customer-opened window the image and the caption are both
        // illegal, and the template is the only send that gets delivered.
        MessageTemplate::create([
            'key' => 'booking.ticket', 'name' => 'Booking confirmation', 'channel' => 'whatsapp',
            'category' => 'utility', 'locale' => 'en', 'body' => 'body', 'variables' => [],
            'provider_template_id' => 'booking_confirmation', 'status' => 'approved', 'is_active' => true,
        ]);

        $this->fakeProvidersOk();
        $booking = $this->booking();

        app(BookingNotifier::class)->notify($booking);

        Http::assertSent(function ($request) use ($booking): bool {
            $data = $request->data();

            if (($data['type'] ?? null) !== 'template') {
                return false;
            }

            $parameters = $data['template']['components'][0]['parameters'] ?? [];

            // The approved order: 1 event, 2 when, 3 venue, 4 code, 5 QR link.
            // Reordering these without re-approving the template would reorder the
            // customer's ticket, so it is pinned here.
            return $data['template']['name'] === 'booking_confirmation'
                && count($parameters) === 5
                && $parameters[0]['text'] === 'Sunburn Arena'
                // The compact date: a template parameter reads better short, and
                // must not contain a newline or WhatsApp rejects the whole message.
                && $parameters[1]['text'] === '12 Sep, 7:00 PM'
                && $parameters[2]['text'] === 'Phoenix Arena, Mumbai'
                && $parameters[3]['text'] === $booking->ticket_code
                && str_ends_with($parameters[4]['text'], '/t/' . $booking->ticket_code);
        });
    }

    public function test_the_ticket_lands_in_the_messaging_ledger_against_the_partner(): void
    {
        $this->fakeProvidersOk();

        app(BookingNotifier::class)->notify($this->booking());

        $log = MessageLog::where('channel', 'whatsapp')->first();

        $this->assertNotNull($log, 'a send attempt must always leave a ledger row');
        $this->assertSame(MessageLog::STATUS_SENT, $log->status);
        $this->assertSame($this->partner->id, $log->partner_id);
        $this->assertSame('booking.ticket', $log->template_key);
        // Metered on the normalised number so one customer isn't two recipients.
        $this->assertSame('+919876543210', $log->recipient);
    }

    public function test_a_dead_provider_is_recorded_and_never_breaks_the_booking(): void
    {
        // The whole point of the best-effort design: a delivery failure is a
        // reporting event, not an exception that reaches a paying customer.
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'token expired']], 401),
            '*' => Http::response('', 200),
        ]);

        app(BookingNotifier::class)->notify($this->booking());

        $log = MessageLog::where('channel', 'whatsapp')->first();
        $this->assertSame(MessageLog::STATUS_FAILED, $log->status);
        $this->assertStringContainsString('token expired', (string) $log->error);
    }

    public function test_the_channel_switched_off_is_visible_in_the_ledger(): void
    {
        config(['services.whatsapp.enabled' => false]);
        $this->fakeProvidersOk();

        app(BookingNotifier::class)->notify($this->booking());

        $this->assertSame(
            MessageLog::STATUS_DISABLED,
            MessageLog::where('channel', 'whatsapp')->value('status'),
            'a channel switched off must be visible in the ledger, not invisible in a log line',
        );
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com'));
    }

    public function test_the_ticket_link_opens_without_a_session(): void
    {
        // The link the ticket carries has to work for a gift recipient and for a desk
        // walk-in, neither of whom owns the booking row.
        $booking = $this->booking();

        $this->get('/t/' . $booking->ticket_code)
            ->assertOk()
            ->assertSee($booking->ticket_code);
    }

    public function test_an_unknown_ticket_code_is_a_404(): void
    {
        $this->get('/t/NOSUCHCODE123')->assertNotFound();
    }

    public function test_phone_numbers_normalise_to_one_identity(): void
    {
        // Inbound and outbound must agree, or the ledger double-counts the customer.
        foreach (['9876543210', '+91 98765 43210', '09876543210', '919876543210'] as $input) {
            $this->assertSame('+919876543210', PhoneNumber::e164($input));
        }

        $this->assertFalse(PhoneNumber::isRoutable(PhoneNumber::e164('12345')));
    }

    // --- push ---------------------------------------------------------------

    /**
     * Stand the real FcmClient up against faked HTTP.
     *
     * The service-account file only has to EXIST for isConfigured() to pass. The
     * OAuth access token is seeded straight into the cache the client reads, which
     * skips the RS256 assertion it would otherwise sign — so these tests exercise
     * the real client without needing a private key (and without depending on the
     * machine having a usable openssl.cnf).
     */
    /** @param mixed $fcmResponse whatever Http::response() returns (a promise, not a Response) */
    private function fakeFcm(mixed $fcmResponse = null): void
    {
        $path = storage_path('framework/testing/fcm-sa.json');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, json_encode([
            'type' => 'service_account',
            'project_id' => 'haraan-test',
            'client_email' => 'test@haraan-test.iam.gserviceaccount.com',
            'private_key' => 'unused-the-access-token-is-pre-cached',
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));

        config(['services.fcm.credentials' => $path]);
        \Illuminate\Support\Facades\Cache::put('fcm.access_token', 'ya29.test', 3300);

        // Note: Http::fake() MERGES stubs and the first match wins, so the caller's
        // FCM response has to be passed in here rather than faked over the top.
        Http::fake([
            'fcm.googleapis.com/*' => $fcmResponse ?? Http::response(['name' => 'projects/haraan-test/messages/1'], 200),
            'control.msg91.com/*' => Http::response(['type' => 'success', 'request_id' => 'req-1'], 200),
            '*' => Http::response('', 200),
        ]);
    }

    public function test_the_ticket_is_pushed_to_the_buyers_devices(): void
    {
        $this->fakeFcm();
        $booking = $this->booking();

        \App\Models\DeviceToken::create([
            'user_id' => $booking->user_id, 'token' => 'device-token-1', 'platform' => 'android',
        ]);

        app(BookingNotifier::class)->notify($booking);

        Http::assertSent(function ($request) use ($booking): bool {
            if (! str_contains($request->url(), 'fcm.googleapis.com')) {
                return false;
            }

            $data = $request->data()['message']['data'];

            return str_contains($data['title'], 'Sunburn Arena')
                && str_contains($data['body'], $booking->ticket_code)
                && $data['deep_link'] === url('/t/' . $booking->ticket_code)
                && $data['ticket_code'] === $booking->ticket_code;
        });
    }

    public function test_a_desk_walk_in_is_never_pushed_to_the_partners_phone(): void
    {
        // An offline booking carries the PARTNER's user_id, because the desk created
        // it. Pushing it would fire the customer's ticket at the partner's phone.
        $this->fakeFcm();

        \App\Models\DeviceToken::create([
            'user_id' => $this->partner->id, 'token' => 'partner-device', 'platform' => 'android',
        ]);

        $booking = $this->booking(['user_id' => $this->partner->id, 'channel' => 'offline']);

        app(BookingNotifier::class)->notify($booking);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'fcm.googleapis.com'));
    }

    public function test_a_dead_device_token_is_pruned(): void
    {
        // What FCM returns once the app has been uninstalled.
        $this->fakeFcm(Http::response([
            'error' => ['status' => 'NOT_FOUND', 'message' => 'Requested entity was not found.'],
        ], 404));

        $booking = $this->booking();
        \App\Models\DeviceToken::create([
            'user_id' => $booking->user_id, 'token' => 'stale-token', 'platform' => 'android',
        ]);

        app(BookingNotifier::class)->notify($booking);

        $this->assertSame(0, \App\Models\DeviceToken::where('token', 'stale-token')->count());
    }

    public function test_push_still_happens_when_there_is_no_email_and_no_phone(): void
    {
        // A Google sign-in with no phone on file used to fall out of notify()
        // before anything ran. They still have the app in their pocket.
        $this->fakeFcm();

        $booking = $this->booking(['attendee_phone' => null, 'attendee_email' => null]);
        $booking->user->forceFill(['phone' => null, 'email' => 'noreply@example.invalid'])->save();

        \App\Models\DeviceToken::create([
            'user_id' => $booking->user_id, 'token' => 'lonely-device', 'platform' => 'android',
        ]);

        app(BookingNotifier::class)->notify($booking->fresh(['user']));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'fcm.googleapis.com'));
    }

    public function test_an_unroutable_number_is_recorded_rather_than_attempted(): void
    {
        $this->fakeProvidersOk();

        app(\App\Services\WhatsAppService::class)->sendMessage('123', 'hello', MessageContext::platform());

        $this->assertSame(
            MessageLog::STATUS_UNROUTABLE,
            MessageLog::where('channel', 'whatsapp')->value('status'),
        );
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com'));
    }
}
