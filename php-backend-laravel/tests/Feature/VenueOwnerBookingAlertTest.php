<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Services\BookingNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * When a court is booked, the venue's owner gets a WhatsApp — alongside, and independent
 * of, the customer's ticket.
 */
class VenueOwnerBookingAlertTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Venue $venue;

    private VenueCourt $court;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'name' => 'Turf Owner', 'email' => 'owner@example.test', 'phone' => '+91 91234 56789',
            'password' => bcrypt('secret'), 'role' => 'PARTNER', 'status' => 'active',
            'partner_type' => 'venue',
        ]);

        $this->venue = Venue::create([
            'name' => 'Kick Off Turf', 'location' => 'Gachibowli', 'price' => 1000,
            'is_active' => true, 'is_bookable' => true, 'partner_id' => $this->owner->id,
            'city' => 'Hyderabad', 'images' => ['venues/test.jpg'], 'status' => 'published',
        ]);

        $this->court = VenueCourt::create([
            'venue_id' => $this->venue->id, 'name' => 'Turf A', 'price' => 1200, 'is_active' => true,
        ]);

        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.driver' => 'meta',
            'services.whatsapp.phone_number_id' => '123456789',
            'services.whatsapp.access_token' => 'meta-token',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200),
            '*' => Http::response('', 200),
        ]);
    }

    private function booking(array $overrides = []): Booking
    {
        $buyer = User::firstOrCreate(
            ['email' => 'player@example.test'],
            ['name' => 'Ravi Kumar', 'password' => bcrypt('secret'), 'role' => 'user', 'status' => 'active'],
        );

        $booking = Booking::create(array_merge([
            'user_id' => $buyer->id, 'venue_id' => $this->venue->id, 'venue_court_id' => $this->court->id,
            'booking_type' => 'venue', 'channel' => 'online', 'quantity' => 1,
            'slot_date' => Carbon::parse('2026-10-03'), 'start_time' => '18:00', 'end_time' => '19:00',
            'total_amount' => 1200, 'status' => 'CONFIRMED',
            'attendee_phone' => '9876543210', 'attendee_name' => 'Ravi Kumar',
            'razorpay_order_id' => 'order_TEST1',
        ], $overrides));

        // What BookingLedger::settleOnline() leaves behind by the time the deferred
        // notifier runs — amount_paid is derived, never mass-assigned.
        $booking->forceFill(['amount_paid' => 1200, 'payment_status' => 'paid'])->save();

        return $booking;
    }

    /** @return list<array<string, mixed>> WhatsApp payloads that went to the owner */
    private function sentToOwner(): array
    {
        return $this->sentTo('919123456789');
    }

    /** @return list<array<string, mixed>> */
    private function sentTo(string $to): array
    {
        return Http::recorded()
            ->map(fn ($pair) => $pair[0])
            ->filter(fn ($r) => str_contains($r->url(), 'graph.facebook.com') && ($r->data()['to'] ?? null) === $to)
            ->map(fn ($r) => $r->data())
            ->values()
            ->all();
    }

    private function approveTemplate(): void
    {
        MessageTemplate::create([
            'key' => 'booking.partner_alert', 'name' => 'New venue booking (owner)', 'channel' => 'whatsapp',
            'category' => 'utility', 'locale' => 'en', 'body' => 'body', 'variables' => [],
            'provider_template_id' => 'venue_booking_alert', 'status' => 'approved', 'is_active' => true,
        ]);
    }

    public function test_the_owner_gets_the_approved_template_with_the_booking_details(): void
    {
        $this->approveTemplate();
        $booking = $this->booking();

        app(BookingNotifier::class)->notify($booking);

        $sent = $this->sentToOwner();
        $this->assertCount(1, $sent);
        $this->assertSame('venue_booking_alert', $sent[0]['template']['name']);

        // Pinned order: 1 venue 2 date 3 slots 4 customer 5 phone 6 amount 7 code.
        $params = array_column($sent[0]['template']['components'][0]['parameters'], 'text');
        $this->assertSame([
            'Kick Off Turf', 'Sat, 03 Oct 2026', 'Turf A · 18:00 – 19:00', 'Ravi Kumar', '9876543210',
            'Rs.1,200 paid online', $booking->ticket_code,
        ], $params);

        $log = MessageLog::where('template_key', 'booking.partner_alert')->first();
        $this->assertSame(MessageLog::STATUS_SENT, $log->status);
        $this->assertSame('+919123456789', $log->recipient);
    }

    public function test_a_multi_slot_checkout_is_one_message_listing_every_slot(): void
    {
        $this->approveTemplate();
        $first = $this->booking();
        $this->booking(['start_time' => '19:00', 'end_time' => '20:00']);

        app(BookingNotifier::class)->notify($first);

        $sent = $this->sentToOwner();
        $this->assertCount(1, $sent);
        $params = array_column($sent[0]['template']['components'][0]['parameters'], 'text');
        $this->assertSame('Turf A · 18:00 – 19:00, Turf A · 19:00 – 20:00', $params[2]);
        $this->assertSame('Rs.2,400 paid online', $params[5]);
    }

    public function test_the_owner_is_alerted_even_when_the_customer_left_no_contact(): void
    {
        $booking = $this->booking(['attendee_phone' => null, 'attendee_email' => null]);
        $booking->user->forceFill(['phone' => null, 'email' => 'noreply@example.invalid'])->save();

        app(BookingNotifier::class)->notify($booking->fresh(['user']));

        $this->assertCount(1, $this->sentToOwner());
    }

    public function test_a_desk_walk_in_does_not_alert_the_owner_who_made_it(): void
    {
        app(BookingNotifier::class)->notify($this->booking([
            'user_id' => $this->owner->id, 'channel' => 'offline', 'razorpay_order_id' => null,
        ]));

        $this->assertSame([], $this->sentToOwner());
    }

    public function test_deactivating_the_template_in_control_switches_the_alert_off(): void
    {
        MessageTemplate::create([
            'key' => 'booking.partner_alert', 'name' => 'New venue booking (owner)', 'channel' => 'whatsapp',
            'category' => 'utility', 'locale' => 'en', 'body' => 'body', 'variables' => [],
            'provider_template_id' => 'venue_booking_alert', 'status' => 'approved', 'is_active' => false,
        ]);

        app(BookingNotifier::class)->notify($this->booking());

        $this->assertSame([], $this->sentToOwner());
    }

    public function test_a_partner_cancelling_their_own_booking_is_not_told_about_it(): void
    {
        $booking = $this->booking(['status' => 'CANCELLED']);

        app(BookingNotifier::class)->notifyCancellation($booking, 'Rain', byPartner: true);
        $this->assertSame([], $this->sentToOwner());

        app(BookingNotifier::class)->notifyCancellation($booking, 'Rain');
        $this->assertCount(1, $this->sentToOwner());
    }

    private function staff(string $name, string $phone, array $perms, array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => $name, 'email' => strtolower(str_replace(' ', '.', $name)) . '@example.test', 'phone' => $phone,
            'password' => bcrypt('secret'), 'role' => 'PARTNER', 'status' => 'active', 'partner_type' => 'venue',
            'parent_partner_id' => $this->owner->id, 'staff_permissions' => $perms,
        ], $overrides));
    }

    public function test_desk_staff_who_work_bookings_or_checkin_are_alerted_too(): void
    {
        $this->staff('Box Office', '9000000001', ['bookings', 'checkin']);
        $this->staff('Gate Person', '9000000002', ['checkin']);
        $this->staff('Accounts', '9000000003', ['reports']);
        $this->staff('Suspended Desk', '9000000004', ['bookings'], ['status' => 'SUSPENDED']);

        app(BookingNotifier::class)->notify($this->booking());

        $this->assertCount(1, $this->sentToOwner());
        $this->assertCount(1, $this->sentTo('919000000001'));
        $this->assertCount(1, $this->sentTo('919000000002'));
        $this->assertSame([], $this->sentTo('919000000003'), 'finance-only staff never run the desk');
        $this->assertSame([], $this->sentTo('919000000004'), 'a suspended account hears nothing');
    }

    public function test_staff_assigned_to_another_branch_are_not_alerted(): void
    {
        $other = Venue::create([
            'name' => 'Branch Two', 'location' => 'Kondapur', 'price' => 900, 'is_active' => true,
            'is_bookable' => true, 'partner_id' => $this->owner->id, 'city' => 'Hyderabad',
            'images' => ['venues/test.jpg'], 'status' => 'published',
        ]);

        $here = $this->staff('Here Desk', '9000000011', ['bookings']);
        $here->assignedVenues()->attach($this->venue->id);
        $there = $this->staff('There Desk', '9000000012', ['bookings']);
        $there->assignedVenues()->attach($other->id);

        app(BookingNotifier::class)->notify($this->booking());

        $this->assertCount(1, $this->sentTo('919000000011'));
        $this->assertSame([], $this->sentTo('919000000012'));
    }

    public function test_a_phone_shared_by_owner_and_staff_gets_one_message(): void
    {
        $this->staff('Owner Desk Login', '9123456789', ['bookings']);

        app(BookingNotifier::class)->notify($this->booking());

        $this->assertCount(1, $this->sentToOwner());
    }

    public function test_staff_hear_about_a_customer_cancellation(): void
    {
        $this->staff('Box Office', '9000000001', ['bookings', 'checkin']);

        app(BookingNotifier::class)->notifyCancellation($this->booking(['status' => 'CANCELLED']), 'Rain');

        $this->assertCount(1, $this->sentTo('919000000001'));
    }

    public function test_the_customer_gets_the_approved_cancellation_template(): void
    {
        MessageTemplate::create([
            'key' => 'booking.cancelled', 'name' => 'Booking cancelled (customer)', 'channel' => 'whatsapp',
            'category' => 'utility', 'locale' => 'en', 'body' => 'body', 'variables' => [],
            'provider_template_id' => 'booking_cancelled', 'status' => 'approved', 'is_active' => true,
        ]);
        $booking = $this->booking(['status' => 'CANCELLED']);

        app(BookingNotifier::class)->notifyCancellation($booking, '', byPartner: true);

        $sent = $this->sentTo('919876543210');
        $this->assertCount(1, $sent);
        $this->assertSame('booking_cancelled', $sent[0]['template']['name']);
        $params = array_column($sent[0]['template']['components'][0]['parameters'], 'text');
        // Pinned order: 1 venue 2 when 3 code 4 reason — and never an empty parameter.
        $this->assertSame(['Kick Off Turf', 'Sat, 03 Oct 2026 · 18:00 – 19:00', $booking->ticket_code, 'Not given'], $params);
    }
}
