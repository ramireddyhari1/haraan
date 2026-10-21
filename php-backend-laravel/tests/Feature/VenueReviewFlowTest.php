<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Models\VenueReview;
use App\Models\VenueSlot;
use App\Support\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * RATE VENUE end to end. The app posted to /api/venues/{id}/reviews, a route that never
 * existed; the website's form drew a card in the page and saved nothing. So no review
 * ever reached the venue page, the partner, or /control.
 */
final class VenueReviewFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $player;
    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'name' => 'Ravi Teja', 'email' => 'owner@haraan.test', 'password' => Hash::make('secret123'),
            'role' => 'PARTNER', 'partner_type' => 'venue', 'status' => 'active',
        ]);
        $this->player = User::create([
            'name' => 'Sai Kiran Reddy', 'email' => 'sai@haraan.test', 'password' => Hash::make('secret123'), 'status' => 'active',
        ]);
        $this->venue = Venue::create([
            'name' => 'Shuttle House', 'location' => 'Labbipet', 'city' => 'Vijayawada', 'price' => 400,
            'images' => ['venues/a.jpg'], 'status' => 'published', 'published_at' => now(),
            'is_active' => true, 'is_bookable' => true, 'partner_id' => $this->owner->id,
        ]);
        VenueCourt::create(['venue_id' => $this->venue->id, 'name' => 'Court 1', 'price' => 400, 'is_active' => true]);
        VenueSlot::create(['venue_id' => $this->venue->id, 'day' => 'Daily', 'time' => '6:00 AM', 'price' => 400, 'is_available' => true]);
    }

    private function bearer(User $u): array
    {
        return ['Authorization' => 'Bearer ' . JwtService::issueForUser($u, (string) (config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me')))];
    }

    public function test_a_rating_from_the_app_reaches_the_venue_page_the_partner_and_the_average(): void
    {
        $this->withHeaders($this->bearer($this->player))
            ->postJson("/api/venues/{$this->venue->id}/reviews", ['rating' => 4, 'text' => 'Good wooden courts'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Sai R.');

        $this->assertSame(1, (int) $this->venue->fresh()->reviews_count);
        $this->assertSame('4.0', (string) $this->venue->fresh()->rating);

        $detail = $this->getJson("/api/venues/{$this->venue->id}")->assertOk()->json('data.reviews');
        $this->assertSame('Good wooden courts', $detail[0]['text']);

        $partner = $this->withHeaders($this->bearer($this->owner))->getJson('/api/partner/reviews')->assertOk()->json('data');
        $this->assertSame(1, $partner['count']);
        $this->assertSame(4.0, (float) $partner['average']);
        $this->assertSame('Shuttle House', $partner['reviews'][0]['venue']);

        // Rating again edits the same review — one account can't stack a venue.
        $this->withHeaders($this->bearer($this->player))
            ->postJson("/api/venues/{$this->venue->id}/reviews", ['rating' => 2])
            ->assertOk();
        $this->assertSame(1, VenueReview::query()->count());
        $this->assertSame('2.0', (string) $this->venue->fresh()->rating);
    }

    public function test_the_owner_cannot_rate_their_own_venue(): void
    {
        $this->withHeaders($this->bearer($this->owner))
            ->postJson("/api/venues/{$this->venue->id}/reviews", ['rating' => 5])
            ->assertStatus(422);
        $this->assertSame(0, VenueReview::query()->count());
    }

    public function test_a_past_booking_marks_the_review_played_here(): void
    {
        Booking::query()->forceCreate([
            'user_id' => $this->player->id, 'venue_id' => $this->venue->id, 'status' => 'CONFIRMED',
            'quantity' => 1, 'total_amount' => 400, 'booking_type' => 'venue',
            'slot_date' => now()->subDays(3)->toDateString(), 'slot_label' => '6:00 AM',
        ]);

        $this->withHeaders($this->bearer($this->player))
            ->postJson("/api/venues/{$this->venue->id}/reviews", ['rating' => 5])
            ->assertCreated()
            ->assertJsonPath('data.played', true);
    }

    public function test_hidden_and_deleted_reviews_leave_the_page_the_average_and_the_partner_view(): void
    {
        $this->withHeaders($this->bearer($this->player))
            ->postJson("/api/venues/{$this->venue->id}/reviews", ['rating' => 1, 'text' => 'spam spam'])
            ->assertCreated();
        $review = VenueReview::query()->firstOrFail();

        $review->forceFill(['is_active' => false, 'hidden_reason' => 'Spam'])->save();
        $this->assertSame(0, (int) $this->venue->fresh()->reviews_count);
        $this->assertSame([], $this->getJson("/api/venues/{$this->venue->id}")->json('data.reviews'));
        $this->assertSame(0, $this->withHeaders($this->bearer($this->owner))->getJson('/api/partner/reviews')->json('data.count'));

        // Editing doesn't reopen a hidden review.
        $this->withHeaders($this->bearer($this->player))
            ->postJson("/api/venues/{$this->venue->id}/reviews", ['rating' => 1])->assertOk();
        $this->assertFalse((bool) $review->fresh()->is_active);

        // Delete is soft: recoverable, and the author can't write over it meanwhile.
        $review->fresh()->forceFill(['is_active' => true])->save();
        $review->fresh()->delete();
        $this->assertSoftDeleted($review);
        $this->assertSame(0, (int) $this->venue->fresh()->reviews_count);
        $this->withHeaders($this->bearer($this->player))
            ->postJson("/api/venues/{$this->venue->id}/reviews", ['rating' => 5])->assertStatus(422);

        VenueReview::withTrashed()->findOrFail($review->id)->restore();
        $this->assertSame(1, (int) $this->venue->fresh()->reviews_count);
    }

    public function test_control_lists_reviews_and_shows_the_deleted_ones_on_their_own_tab(): void
    {
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@haraan.test', 'password' => Hash::make('secret123'), 'role' => 'ADMIN', 'status' => 'ACTIVE',
        ]);
        $live = VenueReview::create(['venue_id' => $this->venue->id, 'name' => 'Teja V.', 'rating' => 5, 'text' => 'Lovely courts', 'is_active' => true]);
        $gone = VenueReview::create(['venue_id' => $this->venue->id, 'name' => 'Spam Bot', 'rating' => 1, 'text' => 'buy cheap', 'is_active' => true]);
        $gone->delete();

        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('control'));
        $url = \App\Filament\Resources\VenueReviews\VenueReviewResource::getUrl('index', panel: 'control');
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('Lovely courts')->assertDontSee('buy cheap');

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\VenueReviews\Pages\ListVenueReviews::class)
            ->set('activeTab', 'deleted')
            ->assertCanSeeTableRecords([$gone])
            ->assertCanNotSeeTableRecords([$live]);

        // A partner never reaches moderation.
        $this->actingAs($this->owner)->get($url)->assertForbidden();
    }

    public function test_the_website_form_saves_for_real(): void
    {
        $this->actingAs($this->player)
            ->postJson("/gamehub/{$this->venue->id}/review", ['rating' => 5, 'text' => 'Lovely lights'])
            ->assertCreated()
            ->assertJsonPath('review.text', 'Lovely lights')
            ->assertJsonPath('venue.reviews', 1);

        $this->assertSame(VenueReview::SOURCE_WEB, VenueReview::query()->firstOrFail()->source);
    }
}
