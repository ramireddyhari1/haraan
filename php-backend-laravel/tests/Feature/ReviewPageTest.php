<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use App\Models\VenueReview;
use App\Services\JourneyTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The post-event review page.
 *
 * It is public and addressed only by booking code, because the person who attended
 * often isn't the person who paid — so the code is the entire authorisation, and
 * these cover what that has to buy: one review per booking, nothing before the
 * event, nothing for a cancelled order, and a 404 for a code that isn't real.
 */
class ReviewPageTest extends TestCase
{
    use RefreshDatabase;

    private User $partner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->partner = User::create([
            'name' => 'Review Partner', 'email' => 'review-partner@example.test',
            'password' => bcrypt('secret'), 'role' => 'PARTNER', 'status' => 'active',
            'partner_type' => 'event',
        ]);
    }

    private function booking(array $eventOverrides = [], array $overrides = []): Booking
    {
        $event = Event::create(array_merge([
            'partner_id' => $this->partner->id, 'title' => 'Sunburn Arena', 'category' => 'Music',
            'location' => '12 Marine Drive', 'venue' => 'Phoenix Arena', 'city' => 'Mumbai',
            // Yesterday, so it has happened.
            'date' => Carbon::now()->subDay(), 'time' => '7:00 PM',
            'price' => 500, 'total_slots' => 50, 'available_slots' => 50, 'images' => [],
            'status' => 'published',
        ], $eventOverrides));

        $buyer = User::create([
            'name' => 'Asha', 'email' => 'reviewer' . uniqid() . '@example.test',
            'password' => bcrypt('secret'), 'role' => 'user', 'status' => 'active',
        ]);

        return Booking::create(array_merge([
            'user_id' => $buyer->id, 'event_id' => $event->id, 'quantity' => 1,
            'total_amount' => 500, 'status' => 'CONFIRMED',
            'ticket_code' => 'RV' . strtoupper(substr(uniqid(), -8)),
        ], $overrides));
    }

    public function test_the_page_opens_with_no_session_at_all(): void
    {
        // A gift recipient and a desk walk-in both have the code and neither has an
        // account. A login wall here is how you get no ratings.
        $booking = $this->booking();

        $this->get('/r/' . $booking->ticket_code)
            ->assertOk()
            ->assertSee('How was it?')
            ->assertSee('Sunburn Arena');
    }

    public function test_an_unknown_code_is_a_404(): void
    {
        $this->get('/r/NOSUCHCODE1')->assertNotFound();
    }

    public function test_a_cancelled_booking_cannot_leave_a_review(): void
    {
        $booking = $this->booking([], ['status' => 'CANCELLED']);

        $this->get('/r/' . $booking->ticket_code)->assertNotFound();
        $this->post('/r/' . $booking->ticket_code, ['rating' => 5])->assertNotFound();

        $this->assertSame(0, VenueReview::query()->count());
    }

    public function test_a_rating_is_saved_against_the_event_and_the_booking(): void
    {
        $booking = $this->booking();

        $this->post('/r/' . $booking->ticket_code, ['rating' => 4, 'text' => 'Great sound, slow queue.'])
            ->assertRedirect('/r/' . $booking->ticket_code);

        $review = VenueReview::query()->first();

        $this->assertNotNull($review);
        $this->assertSame(4, $review->rating);
        $this->assertSame('Great sound, slow queue.', $review->text);
        $this->assertSame((int) $booking->event_id, (int) $review->event_id);
        $this->assertSame((int) $booking->id, (int) $review->booking_id);
        // An event booking has no venue row to point at — events keep the venue as
        // free text, so there is no foreign key to fill.
        $this->assertNull($review->venue_id);
        $this->assertSame('Asha', $review->name);
        $this->assertTrue($review->is_active);
    }

    public function test_a_rating_with_no_comment_is_a_complete_review(): void
    {
        // Demanding prose is how you get "good" typed a thousand times.
        $booking = $this->booking();

        $this->post('/r/' . $booking->ticket_code, ['rating' => 5])->assertRedirect();

        $this->assertNull(VenueReview::query()->first()->text);
    }

    public function test_one_booking_leaves_exactly_one_review(): void
    {
        // The link is public and re-openable, so a double submit must not create two.
        $booking = $this->booking();

        $this->post('/r/' . $booking->ticket_code, ['rating' => 5]);
        $this->post('/r/' . $booking->ticket_code, ['rating' => 1, 'text' => 'changed my mind']);

        $this->assertSame(1, VenueReview::query()->count());
        $this->assertSame(5, VenueReview::query()->first()->rating, 'the first review stands');
    }

    public function test_revisiting_after_reviewing_shows_the_rating_back(): void
    {
        $booking = $this->booking();
        $this->post('/r/' . $booking->ticket_code, ['rating' => 5, 'text' => 'Loved it']);

        $this->get('/r/' . $booking->ticket_code)
            ->assertOk()
            ->assertSee('Thanks for the feedback')
            ->assertSee('Loved it')
            ->assertDontSee('Send rating');
    }

    public function test_an_event_that_has_not_happened_yet_cannot_be_reviewed(): void
    {
        $booking = $this->booking(['date' => Carbon::now()->addWeek()]);

        $this->get('/r/' . $booking->ticket_code)
            ->assertOk()
            ->assertSee('Not just yet')
            ->assertDontSee('Send rating');

        $this->post('/r/' . $booking->ticket_code, ['rating' => 5]);

        $this->assertSame(0, VenueReview::query()->count(), 'a review written before the event is not a review');
    }

    public function test_a_rating_outside_one_to_five_is_rejected(): void
    {
        $booking = $this->booking();

        $this->post('/r/' . $booking->ticket_code, ['rating' => 9])->assertSessionHasErrors('rating');
        $this->post('/r/' . $booking->ticket_code, ['rating' => 0])->assertSessionHasErrors('rating');

        $this->assertSame(0, VenueReview::query()->count());
    }

    public function test_the_journey_message_carries_the_review_link(): void
    {
        // The whole reason the page exists: the WhatsApp template's second variable
        // has to be a URL that actually resolves.
        $booking = $this->booking();

        $variables = app(JourneyTemplates::class)->variables('review.request', $booking);

        $this->assertSame('Sunburn Arena', $variables[0]);
        $this->assertSame(url('/r/' . $booking->ticket_code), $variables[1]);

        $this->assertStringContainsString(
            url('/r/' . $booking->ticket_code),
            (string) app(JourneyTemplates::class)->render('review.request', $booking),
        );
    }
}
