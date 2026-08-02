<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Event;
use App\Models\EventSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nothing used to filter events by date — only by `status`. So an event that had
 * already happened stayed in the app's Events tab, the website rails and the home
 * page forever, AND a logged-in user could still buy a ticket to it: a real booking
 * row plus a ticket email for an event that finished days ago.
 *
 * Event::notFinished() / hasFinished() close that. The cutoff is end-of-day so an
 * event stays live for its whole day (walk-ups still work), and slot-aware so a
 * multi-day event survives until its last session.
 */
class FinishedEventTest extends TestCase
{
    use RefreshDatabase;

    private function partner(): User
    {
        return User::firstOrCreate(
            ['email' => 'host@example.test'],
            ['name' => 'Host', 'password' => bcrypt('secret'), 'role' => 'PARTNER', 'status' => 'active'],
        );
    }

    private function event(string $title, $date): Event
    {
        return Event::create([
            'partner_id'      => $this->partner()->id,
            'title'           => $title,
            'category'        => 'MUSIC',
            'description'     => 'Test.',
            'location'        => 'Arena',
            'venue'           => 'Arena, Hyderabad',
            'city'            => 'Hyderabad',
            'date'            => $date,
            'time'            => '7:00 PM',
            'price'           => 499,
            'total_slots'     => 100,
            'available_slots' => 100,
            'images'          => ['https://example.test/poster.jpg'],
            'status'          => 'published',
        ]);
    }

    private function buyer(): User
    {
        return User::create([
            'name' => 'Buyer', 'email' => 'buyer@example.test',
            'password' => bcrypt('secret'), 'role' => 'user', 'status' => 'active',
        ]);
    }

    public function test_a_finished_event_leaves_the_public_feeds(): void
    {
        $past = $this->event('Finished Last Week', now()->subDays(7));
        $this->event('Coming Up', now()->addDays(7));

        $ids = collect($this->getJson('/api/events')->assertOk()->json('data'))->pluck('id');
        $this->assertNotContains($past->id, $ids, 'still in the app Events tab');

        $this->assertStringNotContainsString('Finished Last Week', $this->get('/events')->getContent());
        $this->assertStringNotContainsString('Finished Last Week', $this->get('/')->getContent());
        // The upcoming one is untouched.
        $this->assertStringContainsString('Coming Up', $this->get('/events')->getContent());
    }

    public function test_an_event_stays_live_for_the_whole_of_its_own_day(): void
    {
        // Started at 7pm today; someone browsing at 11pm must still find and book it.
        $today = $this->event('Tonight', now()->startOfDay());

        $ids = collect($this->getJson('/api/events')->assertOk()->json('data'))->pluck('id');

        $this->assertContains($today->id, $ids);
        $this->assertFalse($today->hasFinished());
    }

    public function test_a_multi_day_event_survives_until_its_last_session(): void
    {
        // `date` is day one, which a naive date filter would treat as over.
        $festival = $this->event('Three Day Festival', now()->subDays(1));
        EventSlot::create(['event_id' => $festival->id, 'starts_at' => now()->subDay(), 'sort' => 0]);
        EventSlot::create(['event_id' => $festival->id, 'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHours(4), 'sort' => 1]);

        $ids = collect($this->getJson('/api/events')->assertOk()->json('data'))->pluck('id');

        $this->assertContains($festival->id, $ids, 'a running festival was hidden on day 2');
        $this->assertFalse($festival->fresh()->hasFinished());
    }

    public function test_a_multi_day_event_does_finish_after_its_last_session(): void
    {
        $festival = $this->event('Finished Festival', now()->subDays(5));
        EventSlot::create(['event_id' => $festival->id, 'starts_at' => now()->subDays(5), 'sort' => 0]);
        EventSlot::create(['event_id' => $festival->id, 'starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(3)->addHours(4), 'sort' => 1]);

        $ids = collect($this->getJson('/api/events')->assertOk()->json('data'))->pluck('id');

        $this->assertNotContains($festival->id, $ids);
        $this->assertTrue($festival->fresh()->hasFinished());
    }

    public function test_a_finished_event_cannot_be_booked(): void
    {
        $past = $this->event('Finished Last Week', now()->subDays(7));

        $this->actingAs($this->buyer())
            ->post("/events/{$past->id}/book", [
                'qty' => [0 => 1],
                'contact' => ['name' => 'Buyer', 'email' => 'buyer@example.test', 'phone' => '9999999999'],
            ])
            ->assertNotFound();

        $this->assertSame(0, Booking::query()->count(), 'sold a ticket to a finished event');
    }

    public function test_the_checkout_page_of_a_finished_event_is_gone_too(): void
    {
        $past = $this->event('Finished Last Week', now()->subDays(7));

        $this->actingAs($this->buyer())
            ->get("/events/{$past->id}/book?qty[0]=1")
            ->assertNotFound();
    }

    public function test_an_upcoming_event_is_still_bookable(): void
    {
        $soon = $this->event('Coming Up', now()->addDays(7));

        $this->actingAs($this->buyer())
            ->post("/events/{$soon->id}/book", [
                'qty' => [0 => 1],
                'contact' => ['name' => 'Buyer', 'email' => 'buyer@example.test', 'phone' => '9999999999'],
            ])
            ->assertSuccessful();

        $this->assertSame(1, Booking::query()->count());
    }

    public function test_the_detail_page_survives_and_says_the_event_ended(): void
    {
        // Deliberately still reachable: shared links, SEO and the organiser's
        // "past events" list all point here. It just can't be booked.
        $past = $this->event('Finished Last Week', now()->subDays(7));

        $html = $this->get("/events/{$past->id}")->assertOk()->getContent();

        $this->assertStringContainsString('Event ended', $html);
        $this->assertStringNotContainsString('>Book Tickets<', $html);
    }
}
