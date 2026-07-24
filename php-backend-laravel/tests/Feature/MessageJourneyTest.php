<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Event;
use App\Models\MessageLog;
use App\Models\MessagingOptOut;
use App\Models\ScheduledMessage;
use App\Models\User;
use App\Services\MessageJourneys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Journeys message real customers on a timer, so the rules that matter are the
 * ones that stop a message: already queued, opted out, cancelled, too late, or
 * the master switch being off.
 */
class MessageJourneyTest extends TestCase
{
    use RefreshDatabase;

    private User $partner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->partner = User::create([
            'name' => 'Journey Partner',
            'email' => 'journey-partner@example.test',
            'password' => bcrypt('secret'),
            'role' => 'PARTNER',
            'status' => 'active',
            'partner_type' => 'event',
        ]);

        config([
            'messaging.local_timezone' => 'Asia/Kolkata',
            'messaging.journeys.enabled' => true,
            // Neutralise quiet hours for most tests; one test asserts them directly.
            'messaging.journeys.quiet_hours.start' => 23,
            'messaging.journeys.quiet_hours.end' => 23,
            'services.whatsapp.enabled' => true,
            'services.whatsapp.account_sid' => 'ACtest',
            'services.whatsapp.auth_token' => 'token',
            'services.whatsapp.from' => '+14155238886',
        ]);
    }

    private function booking(array $eventOverrides = [], array $bookingOverrides = []): Booking
    {
        $event = Event::create(array_merge([
            'partner_id' => $this->partner->id,
            'title' => 'Test Concert',
            'category' => 'Music',
            'location' => 'Test Arena',
            'date' => Carbon::now()->addDays(3),
            'time' => '19:00',
            'price' => 249,
            'total_slots' => 100,
            'available_slots' => 100,
            'images' => [],
            'status' => 'published',
        ], $eventOverrides));

        $buyer = User::firstOrCreate(
            ['email' => 'journey-buyer@example.test'],
            ['name' => 'Journey Buyer', 'password' => bcrypt('secret'), 'role' => 'user', 'status' => 'active'],
        );

        return Booking::create(array_merge([
            'user_id' => $buyer->id,
            'event_id' => $event->id,
            'quantity' => 1,
            'total_amount' => 249,
            'status' => 'CONFIRMED',
            'ticket_code' => 'TC' . uniqid(),
            'attendee_phone' => '9876543210',
        ], $bookingOverrides));
    }

    private function journeys(): MessageJourneys
    {
        return app(MessageJourneys::class);
    }

    public function test_it_queues_both_reminders_and_a_review_request(): void
    {
        $this->booking();

        $this->journeys()->enqueue();

        $this->assertSame(
            ['event.reminder_24h', 'event.reminder_2h', 'review.request'],
            ScheduledMessage::orderBy('send_after')->pluck('template_key')->all(),
        );
    }

    public function test_stored_times_are_read_as_local_wall_clock(): void
    {
        // 19:00 typed by an admin means 7pm in India, i.e. 13:30 UTC — so the
        // "2 hours before" reminder is 11:30 UTC. Reading the stored value as UTC
        // would fire it 5.5 hours early. Kept inside the enqueue horizon.
        $date = Carbon::now()->addDays(2)->format('Y-m-d');
        $this->booking(['date' => Carbon::parse($date), 'time' => '19:00']);

        $this->journeys()->enqueue();

        $reminder = ScheduledMessage::where('template_key', 'event.reminder_2h')->first();
        $this->assertSame($date . ' 11:30:00', $reminder->send_after->utc()->toDateTimeString());
    }

    public function test_an_event_without_a_time_gets_no_two_hour_reminder(): void
    {
        // "2 hours before midnight" is a guess dressed up as a service.
        $this->booking(['time' => '']);

        $this->journeys()->enqueue();

        $this->assertFalse(ScheduledMessage::where('template_key', 'event.reminder_2h')->exists());
        $this->assertTrue(ScheduledMessage::where('template_key', 'event.reminder_24h')->exists());
    }

    public function test_enqueueing_twice_does_not_duplicate(): void
    {
        $this->booking();

        $this->journeys()->enqueue();
        $first = ScheduledMessage::count();
        $this->journeys()->enqueue();

        $this->assertSame($first, ScheduledMessage::count(), 'the cron re-runs constantly; dedupe_key is the guard');
    }

    public function test_steps_whose_moment_has_passed_are_not_queued(): void
    {
        // Event is in 1 hour: the 24h and 2h reminders are both already moot.
        $this->booking(['date' => Carbon::now()->addHour(), 'time' => Carbon::now()->addHour()->format('H:i')]);

        $this->journeys()->enqueue();

        $this->assertFalse(ScheduledMessage::where('template_key', 'like', 'event.reminder%')->exists());
        $this->assertTrue(ScheduledMessage::where('template_key', 'review.request')->exists());
    }

    public function test_a_bookings_without_a_phone_queues_nothing(): void
    {
        $this->booking([], ['attendee_phone' => null]);

        $this->journeys()->enqueue();

        $this->assertSame(0, ScheduledMessage::count());
    }

    public function test_due_messages_are_sent_and_recorded(): void
    {
        Http::fake(fn () => Http::response(['sid' => 'SM123'], 201));
        $this->booking();
        $this->journeys()->enqueue();
        ScheduledMessage::query()->update(['send_after' => Carbon::now()->subMinute()]);

        $result = $this->journeys()->dispatch();

        $this->assertSame(3, $result['sent']);
        $this->assertSame(3, ScheduledMessage::where('status', ScheduledMessage::STATUS_SENT)->count());
        // Each one lands in the ledger, attributed to the partner.
        $this->assertSame(3, MessageLog::where('partner_id', $this->partner->id)->count());
    }

    public function test_nothing_is_delivered_while_the_master_switch_is_off(): void
    {
        Http::fake(fn () => Http::response(['sid' => 'SM123'], 201));
        config(['messaging.journeys.enabled' => false]);
        $this->booking();
        $this->journeys()->enqueue();
        ScheduledMessage::query()->update(['send_after' => Carbon::now()->subMinute()]);

        $result = $this->journeys()->dispatch();

        $this->assertSame(0, $result['sent']);
        $this->assertSame(3, $result['held']);
        Http::assertNothingSent();
        // Held, not consumed — they go out when the switch is flipped.
        $this->assertSame(3, ScheduledMessage::where('status', ScheduledMessage::STATUS_PENDING)->count());
    }

    public function test_an_opted_out_recipient_is_skipped(): void
    {
        Http::fake(fn () => Http::response(['sid' => 'SM123'], 201));
        $this->booking();
        $this->journeys()->enqueue();
        ScheduledMessage::query()->update(['send_after' => Carbon::now()->subMinute()]);

        MessagingOptOut::record('whatsapp', '9876543210', null, 'stop_keyword');

        $result = $this->journeys()->dispatch();

        $this->assertSame(0, $result['sent']);
        $this->assertSame(3, $result['skipped']);
        $this->assertSame('opted_out', ScheduledMessage::first()->skip_reason);
        Http::assertNothingSent();
    }

    public function test_a_cancelled_booking_is_skipped_at_send_time(): void
    {
        Http::fake(fn () => Http::response(['sid' => 'SM123'], 201));
        $booking = $this->booking();
        $this->journeys()->enqueue();
        ScheduledMessage::query()->update(['send_after' => Carbon::now()->subMinute()]);

        // Queued while confirmed, cancelled before the reminder fires.
        $booking->update(['status' => 'CANCELLED']);

        $result = $this->journeys()->dispatch();

        $this->assertSame(3, $result['skipped']);
        $this->assertSame('cancelled', ScheduledMessage::first()->skip_reason);
        Http::assertNothingSent();
    }

    public function test_quiet_hours_hold_a_message_rather_than_dropping_it(): void
    {
        Http::fake(fn () => Http::response(['sid' => 'SM123'], 201));
        $this->booking();
        $this->journeys()->enqueue();
        ScheduledMessage::query()->update(['send_after' => Carbon::now()->subMinute()]);

        // Freeze at 3am local, inside any sane quiet window.
        config(['messaging.journeys.quiet_hours.start' => 21, 'messaging.journeys.quiet_hours.end' => 8]);
        Carbon::setTestNow(Carbon::parse('2026-08-01 03:00', 'Asia/Kolkata')->utc());

        $result = $this->journeys()->dispatch();
        Carbon::setTestNow();

        $this->assertSame(3, $result['held']);
        $this->assertSame(3, ScheduledMessage::where('status', ScheduledMessage::STATUS_PENDING)->count());
        Http::assertNothingSent();
    }

    public function test_a_failed_send_retries_before_giving_up(): void
    {
        Http::fake(fn () => Http::response(['message' => 'nope'], 400));
        $this->booking(['time' => '']);   // one reminder + one review request
        $this->journeys()->enqueue();
        ScheduledMessage::query()->update(['send_after' => Carbon::now()->subMinute()]);

        $this->journeys()->dispatch();

        $message = ScheduledMessage::first();
        $this->assertSame(1, $message->attempts);
        $this->assertSame(ScheduledMessage::STATUS_PENDING, $message->status, 'a Twilio blip should not cost the reminder');
        $this->assertTrue($message->send_after->isFuture());
    }

    public function test_the_review_request_names_the_event_and_offers_an_exit(): void
    {
        $booking = $this->booking();

        $body = app(\App\Services\JourneyTemplates::class)->render('review.request', $booking->fresh(['event']));

        $this->assertStringContainsString('Test Concert', $body);
        $this->assertStringContainsString('STOP', $body);
    }
}
