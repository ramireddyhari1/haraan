<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Venues\Pages\EditVenue;
use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueReview;
use App\Models\VenueSlot;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * /control → Game Hub → Venues → edit. Four things it used to get wrong:
 * invented ratings, slot edits wiped on every save, delete orphaning bookings,
 * and no way to set the convenience fee checkout charges.
 */
class VenueEditSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('control'));
        $admin = User::create([
            'name' => 'Ops Operator', 'email' => 'ops@haraan.test', 'password' => bcrypt('Password123!'),
            'role' => 'ADMIN', 'status' => 'ACTIVE',
        ]);
        // The delete button is gated by Shield's Delete:Venue permission (VenuePolicy).
        $admin->givePermissionTo(Permission::findOrCreate('Delete:Venue', 'web'));
        $this->actingAs($admin);
    }

    private function venue(array $attributes = []): Venue
    {
        return Venue::create(array_merge([
            'name' => 'Sportz Arena', 'category' => 'Cricket', 'location' => 'Gachibowli', 'city' => 'Hyderabad',
            'price' => 1000, 'is_active' => true, 'is_bookable' => true, 'slot_minutes' => 60,
            'hours_json' => ['Mon' => ['open' => '06:00', 'close' => '08:00']],
        ], $attributes));
    }

    private function slot(Venue $venue, string $time): ?VenueSlot
    {
        return $venue->slots()->where('day', 'Monday')->where('time', $time)->first();
    }

    // ── 1. Ratings come from real reviews only ───────────────────────────────

    public function test_the_rating_is_derived_from_real_reviews_not_typed_in(): void
    {
        $venue = $this->venue(['rating' => '4.2', 'ratings_count' => 120, 'reviews_count' => 0]);

        $a = VenueReview::create(['venue_id' => $venue->id, 'name' => 'Asha', 'rating' => 4, 'text' => 'Good', 'is_active' => true]);
        VenueReview::create(['venue_id' => $venue->id, 'name' => 'Ravi', 'rating' => 5, 'text' => 'Great', 'is_active' => true]);

        $venue->refresh();
        $this->assertSame('4.5', (string) $venue->rating);
        $this->assertSame(2, (int) $venue->ratings_count);
        $this->assertSame(2, (int) $venue->reviews_count);

        // A hidden review stops counting.
        $a->update(['is_active' => false]);
        $this->assertSame('5.0', (string) $venue->fresh()->rating);

        VenueReview::query()->where('venue_id', $venue->id)->get()->each->delete();
        $venue->refresh();
        $this->assertSame('0', (string) $venue->rating); // "not rated" — the column is NOT NULL on prod
        $this->assertSame(0, (int) $venue->ratings_count);
    }

    public function test_the_migration_clears_invented_ratings_and_new_venues_start_unrated(): void
    {
        $fake = $this->venue(['rating' => '4.2', 'ratings_count' => 120, 'reviews_count' => 0]);
        $real = $this->venue(['name' => 'Real Reviews Turf', 'rating' => '4.9', 'ratings_count' => 300]);
        VenueReview::create(['venue_id' => $real->id, 'name' => 'Asha', 'rating' => 3, 'text' => 'Ok', 'is_active' => true]);
        $real->forceFill(['rating' => '4.9', 'ratings_count' => 300])->saveQuietly();

        (require database_path('migrations/2026_09_25_000002_derive_venue_ratings_from_reviews.php'))->up();

        $this->assertSame('0', (string) $fake->fresh()->rating);
        $this->assertSame(0, (int) $fake->fresh()->ratings_count);
        $this->assertSame('3.0', (string) $real->fresh()->rating);
        $this->assertSame(1, (int) $real->fresh()->ratings_count);

        // New venues no longer start with a rating nobody gave.
        $new = Venue::create(['name' => 'Fresh', 'location' => 'Kondapur', 'city' => 'Hyderabad', 'price' => 800]);
        $this->assertSame('0', (string) $new->fresh()->rating);
    }

    /**
     * The first draft of this migration changed the `rating` column. On SQLite that rebuilds
     * `venues`, and the foreign-key cascade on the drop deleted every court, slot and review.
     */
    public function test_the_migration_never_touches_courts_slots_or_reviews(): void
    {
        $venue = $this->venue();
        $venue->courts()->create(['name' => 'Court 1', 'price' => 1000, 'is_active' => true]);
        $venue->regenerateSlotsFromHours();
        VenueReview::create(['venue_id' => $venue->id, 'name' => 'Asha', 'rating' => 4, 'text' => 'Ok', 'is_active' => true]);
        $before = [DB::table('venue_courts')->count(), DB::table('venue_slots')->count(), DB::table('venue_reviews')->count()];

        (require database_path('migrations/2026_09_25_000002_derive_venue_ratings_from_reviews.php'))->up();

        $this->assertSame($before, [DB::table('venue_courts')->count(), DB::table('venue_slots')->count(), DB::table('venue_reviews')->count()]);
        $this->assertSame([1, 2, 1], $before);
    }

    public function test_the_edit_form_no_longer_lets_anyone_type_a_rating(): void
    {
        $venue = $this->venue();

        Livewire::test(EditVenue::class, ['record' => $venue->getRouteKey()])
            ->assertFormFieldDoesNotExist('rating')
            ->assertFormFieldDoesNotExist('ratings_count')
            ->assertSee('No reviews yet');
    }

    // ── 2. Saving the venue keeps the Slots tab's edits ──────────────────────

    public function test_slot_regeneration_keeps_existing_slot_prices_and_switches(): void
    {
        $venue = $this->venue();
        $venue->regenerateSlotsFromHours();
        $this->assertSame(2, $venue->slots()->count()); // 6:00, 7:00

        $this->slot($venue, '6:00 AM')->update(['price' => 700, 'is_available' => false]);

        // Hours widen to 06:00–09:00: 6 AM keeps its price + switch, 8 AM is added.
        $venue->update(['hours_json' => ['Mon' => ['open' => '06:00', 'close' => '09:00']]]);
        $venue->regenerateSlotsFromHours();

        $six = $this->slot($venue, '6:00 AM');
        $this->assertSame(700, (int) $six->price);
        $this->assertFalse($six->is_available);
        $this->assertNotNull($this->slot($venue, '8:00 AM'));
        $this->assertSame(3, $venue->slots()->count());

        // Hours narrow to 07:00–09:00: only the 6 AM row goes.
        $venue->update(['hours_json' => ['Mon' => ['open' => '07:00', 'close' => '09:00']]]);
        $venue->regenerateSlotsFromHours();
        $this->assertNull($this->slot($venue, '6:00 AM'));
        $this->assertSame(2, $venue->slots()->count());
    }

    public function test_saving_unrelated_fields_does_not_touch_the_slots(): void
    {
        $venue = $this->venue();
        $venue->regenerateSlotsFromHours();
        $this->slot($venue, '7:00 AM')->update(['price' => 900, 'is_available' => false]);
        $ids = $venue->slots()->pluck('id')->sort()->values()->all();

        Livewire::test(EditVenue::class, ['record' => $venue->getRouteKey()])
            ->fillForm(['name' => 'Sportz Arena — Gachibowli'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Sportz Arena — Gachibowli', $venue->fresh()->name);
        $this->assertSame($ids, $venue->slots()->pluck('id')->sort()->values()->all());
        $seven = $this->slot($venue, '7:00 AM');
        $this->assertSame(900, (int) $seven->price);
        $this->assertFalse($seven->is_available);
    }

    // ── 3. A venue with bookings can't be deleted ────────────────────────────

    public function test_a_venue_with_bookings_cannot_be_deleted_from_the_edit_page(): void
    {
        $venue = $this->venue();
        Booking::create([
            'quantity' => 1, 'total_amount' => 1000, 'status' => 'CONFIRMED', 'booking_type' => 'venue',
            'user_id' => auth()->id(), 'venue_id' => $venue->id, 'slot_date' => today()->toDateString(),
            'start_time' => '06:00', 'end_time' => '07:00', 'channel' => 'offline',
        ]);

        Livewire::test(EditVenue::class, ['record' => $venue->getRouteKey()])
            ->callAction(DeleteAction::class)
            ->assertNotified('Can’t delete a venue with bookings');

        $this->assertNotNull(Venue::query()->find($venue->id));
    }

    public function test_the_model_refuses_the_delete_on_every_other_path(): void
    {
        $venue = $this->venue();
        Booking::create([
            'quantity' => 1, 'total_amount' => 1000, 'status' => 'CANCELLED', 'booking_type' => 'venue',
            'user_id' => auth()->id(), 'venue_id' => $venue->id, 'slot_date' => today()->toDateString(),
            'channel' => 'online',
        ]);

        $this->expectException(\DomainException::class);
        $venue->delete();
    }

    public function test_a_venue_without_bookings_can_still_be_deleted(): void
    {
        $venue = $this->venue();

        Livewire::test(EditVenue::class, ['record' => $venue->getRouteKey()])
            ->callAction(DeleteAction::class);

        $this->assertNull(Venue::query()->find($venue->id));
    }

    // ── 4. The convenience fee is editable ───────────────────────────────────

    public function test_the_convenience_fee_can_be_set_from_the_edit_page(): void
    {
        $venue = $this->venue();

        Livewire::test(EditVenue::class, ['record' => $venue->getRouteKey()])
            ->fillForm(['convenience_fee_type' => 'percent', 'convenience_fee_value' => 5])
            ->call('save')
            ->assertHasNoFormErrors();

        $venue->refresh();
        $this->assertSame('percent', $venue->convenience_fee_type);
        $this->assertEqualsWithDelta(5.0, (float) $venue->convenience_fee_value, 0.001);
        $this->assertEqualsWithDelta(50.0, $venue->convenienceFeeFor(1000), 0.001);
    }

    public function test_a_percentage_fee_over_50_is_refused(): void
    {
        $venue = $this->venue();

        Livewire::test(EditVenue::class, ['record' => $venue->getRouteKey()])
            ->fillForm(['convenience_fee_type' => 'percent', 'convenience_fee_value' => 80])
            ->call('save')
            ->assertHasFormErrors(['convenience_fee_value']);
    }
}
