<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Venues\Pages\EditVenue;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Models\VenueSlot;
use App\Services\BookingService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

/**
 * 30-minute venues choose how customers book (/control → Hours & slots): a single 30-minute
 * slot ("30 min from the start"), or "1 hour first, then 30-min steps". The rule holds on
 * the website checkout and the app/bot path; the desk still books what the counter asks for.
 */
final class VenueMinBookingTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private Venue $venue;

    private VenueCourt $court;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.razorpay.key' => 'rzp_test_key', 'services.razorpay.secret' => 'test_secret']);
        Http::fake(fn (HttpRequest $r) => Http::response(['id' => 'order_min_1', 'amount' => (int) $r->data()['amount'], 'currency' => 'INR']));

        $owner = User::create([
            'name' => 'Owner', 'email' => 'owner-min@haraan.test', 'password' => Hash::make('secret123'),
            'role' => 'PARTNER', 'partner_type' => 'venue', 'status' => 'active',
        ]);
        $this->buyer = User::create([
            'name' => 'Buyer', 'email' => 'buyer-min@haraan.test', 'password' => Hash::make('secret123'), 'status' => 'active',
        ]);
        $this->venue = Venue::create([
            'name' => 'Half Hour Nets', 'location' => 'Guntur', 'city' => 'Guntur', 'price' => 300,
            'images' => ['venues/nets.jpg'], 'status' => 'published', 'published_at' => now(),
            'is_active' => true, 'is_bookable' => true, 'partner_id' => $owner->id,
            'slot_minutes' => 30, 'min_booking_minutes' => 60,
        ]);
        $this->court = VenueCourt::create(['venue_id' => $this->venue->id, 'name' => 'Net 1', 'price' => 300, 'is_active' => true]);
        foreach (['6:00 AM', '6:30 AM', '7:00 AM', '7:30 AM'] as $time) {
            VenueSlot::create(['venue_id' => $this->venue->id, 'day' => 'Daily', 'time' => $time, 'price' => 300, 'is_available' => true]);
        }
    }

    /** @param  list<string>  $times */
    private function reserve(array $times): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->buyer)->postJson('/gamehub/'.$this->venue->id.'/book', [
            'date' => today()->addDay()->toDateString(),
            'court_id' => $this->court->id,
            'slots' => array_map(fn (string $t) => ['time' => $t, 'price' => 1], $times),
        ]);
    }

    private function slotId(string $time): int
    {
        return (int) VenueSlot::query()->where('venue_id', $this->venue->id)->where('time', $time)->value('id');
    }

    public function test_the_minimum_reads_in_slots_and_minutes(): void
    {
        $this->assertSame(2, $this->venue->minBookingSlots());
        $this->assertSame(60, $this->venue->minBookingMinutes());

        $this->venue->update(['min_booking_minutes' => null]);
        $this->assertSame(1, $this->venue->fresh()->minBookingSlots());
        $this->assertSame(30, $this->venue->fresh()->minBookingMinutes());

        // On hourly slots the 30-minute choice means nothing: one slot is the hour.
        $this->venue->update(['slot_minutes' => 60, 'min_booking_minutes' => 60]);
        $this->assertSame(1, $this->venue->fresh()->minBookingSlots());
    }

    public function test_website_refuses_a_single_half_hour_and_sells_an_hour_then_half_hour_steps(): void
    {
        $this->reserve(['6:00 AM - 6:30 AM'])->assertStatus(422)->assertJsonPath('error', fn (string $e) => str_contains($e, 'at least 1 hour'));
        // Two separate half hours are two runs shorter than the minimum.
        $this->reserve(['6:00 AM - 6:30 AM', '7:00 AM - 7:30 AM'])->assertStatus(422);

        $this->reserve(['6:00 AM - 6:30 AM', '6:30 AM - 7:00 AM', '7:00 AM - 7:30 AM'])->assertOk();
    }

    public function test_website_quote_follows_the_same_rule(): void
    {
        $quote = fn (array $slots) => $this->actingAs($this->buyer)->postJson('/gamehub/'.$this->venue->id.'/book/quote', [
            'date' => today()->addDay()->toDateString(), 'court_id' => $this->court->id, 'slots' => $slots,
        ]);

        $quote(['6:00 AM - 6:30 AM'])->assertStatus(422);
        $quote(['6:00 AM - 6:30 AM', '6:30 AM - 7:00 AM'])->assertOk();
    }

    public function test_a_single_half_hour_sells_when_the_venue_allows_it(): void
    {
        $this->venue->update(['min_booking_minutes' => null]);

        $this->reserve(['6:00 AM - 6:30 AM'])->assertOk();
    }

    public function test_app_path_needs_the_minimum_but_the_desk_does_not(): void
    {
        $date = today()->addDay()->toDateString();
        $bookings = app(BookingService::class);

        try {
            $bookings->createVenueBooking($this->buyer, $this->venue->id, $this->slotId('6:00 AM'), $date, $this->court->id, 1);
            $this->fail('A single half hour should be refused online.');
        } catch (ConflictHttpException $e) {
            $this->assertStringContainsString('at least 1 hour', $e->getMessage());
        }

        $ok = $bookings->createVenueBooking($this->buyer, $this->venue->id, $this->slotId('6:00 AM'), $date, $this->court->id, 2);
        $this->assertSame('06:00', substr((string) $ok->start_time, 0, 5));
        $this->assertSame('07:00', substr((string) $ok->end_time, 0, 5));

        $desk = $bookings->createOfflineVenueBooking($this->venue->partner, $this->venue->id, $this->slotId("7:30 AM"), $date, "Walk-in", "9999999999", $this->court->id, 1);
        $this->assertNotNull($desk->id);
    }

    public function test_control_form_offers_the_choice_on_half_hour_slots_only_and_saves_it(): void
    {
        $this->actingAs(User::create([
            'name' => 'Admin', 'email' => 'admin-min@haraan.test', 'password' => bcrypt('x'), 'role' => 'ADMIN', 'status' => 'active',
        ]));
        Filament::setCurrentPanel(Filament::getPanel('control'));

        $page = Livewire::test(EditVenue::class, ['record' => $this->venue->getRouteKey()])
            ->assertSee('How customers book 30-minute slots')
            ->assertFormSet(['slot_minutes' => 30, 'min_booking_minutes' => 60]);

        $page->fillForm(['min_booking_minutes' => 30])->call('save')->assertHasNoFormErrors();
        $this->assertNull($this->venue->fresh()->min_booking_minutes);

        $page->fillForm(['min_booking_minutes' => 60])->call('save')->assertHasNoFormErrors();
        $this->assertSame(60, $this->venue->fresh()->min_booking_minutes);

        // Hourly slots: the choice hides and the minimum clears.
        $page->fillForm(['slot_minutes' => 60])->assertDontSee('How customers book 30-minute slots')->call('save')->assertHasNoFormErrors();
        $this->assertSame(60, $this->venue->fresh()->slot_minutes);
        $this->assertNull($this->venue->fresh()->min_booking_minutes);
    }
}
