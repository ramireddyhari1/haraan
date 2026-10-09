<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Venues\Pages\EditVenue;
use App\Filament\Resources\Venues\RelationManagers\CourtsRelationManager;
use App\Filament\Resources\Venues\RelationManagers\SlotsRelationManager;
use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Models\VenueSlot;
use App\Support\BusinessClock;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * /control venue → Courts & slots: each court with the slots it is sold in and the price it
 * charges in each, plus the per-court "Slot prices" editor and the guards around courts.
 */
final class CourtsAndSlotsBoardTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    private VenueCourt $pitch;

    private VenueCourt $turf;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::create([
            'name' => 'Admin', 'email' => 'admin@haraan.test', 'password' => bcrypt('x'),
            'role' => 'ADMIN', 'status' => 'active',
        ]));
        Filament::setCurrentPanel(Filament::getPanel('control'));

        $this->venue = Venue::create([
            'name' => 'Vaddeswaram', 'location' => 'Vaddeswaram', 'price' => 300,
            'sports' => ['Cricket'], 'is_active' => true, 'is_bookable' => true,
        ]);
        $this->pitch = VenueCourt::create(['venue_id' => $this->venue->id, 'name' => 'VADI', 'sports' => ['Cricket'], 'price' => 500, 'is_active' => true]);
        $this->turf = VenueCourt::create(['venue_id' => $this->venue->id, 'name' => 'padi', 'sports' => ['Football'], 'price' => 700, 'is_active' => true]);
    }

    private function slot(string $time, array $extra = []): VenueSlot
    {
        return VenueSlot::create($extra + ['venue_id' => $this->venue->id, 'day' => VenueSlot::EVERY_DAY, 'time' => $time, 'is_available' => true]);
    }

    private function courts()
    {
        return Livewire::test(CourtsRelationManager::class, ['ownerRecord' => $this->venue, 'pageClass' => EditVenue::class]);
    }

    public function test_board_shows_each_court_with_its_own_rate_per_slot(): void
    {
        $this->slot('6:00 AM');
        $this->slot('7:00 AM', ['price' => 900]);                                   // all courts
        $this->slot('8:00 AM', ['court_prices' => [$this->turf->id => 1200]]);       // padi only
        $this->slot('9:00 AM', ['sports' => ['Football']]);                          // VADI can't be sold
        $this->slot('10:00 AM', ['is_available' => false]);                          // closed

        $board = $this->courts()->assertOk()
            ->assertSee('What each court charges, slot by slot')
            ->assertSee('VADI')->assertSee('padi')
            ->instance()->courtBoard();

        $byName = collect($board['courts'])->keyBy('name');
        $vadi = collect($byName['VADI']['chips'])->keyBy('time');
        $padi = collect($byName['padi']['chips'])->keyBy('time');

        $this->assertSame([500, 'base'], [$vadi['6:00 AM']['rate'], $vadi['6:00 AM']['source']]);
        $this->assertSame([900, 'slot'], [$vadi['7:00 AM']['rate'], $vadi['7:00 AM']['source']]);
        $this->assertSame([500, 'base'], [$vadi['8:00 AM']['rate'], $vadi['8:00 AM']['source']]);
        $this->assertSame([1200, 'own'], [$padi['8:00 AM']['rate'], $padi['8:00 AM']['source']]);
        $this->assertFalse($vadi['9:00 AM']['open'], 'A football-only slot is not sold on the cricket pitch.');
        $this->assertTrue($padi['9:00 AM']['open']);
        $this->assertFalse($padi['10:00 AM']['open'], 'A closed slot is sold on no court.');
        $this->assertSame(3, $byName['VADI']['openCount']);
    }

    public function test_slot_prices_action_writes_one_courts_prices_and_leaves_others(): void
    {
        $a = $this->slot('6:00 AM', ['court_prices' => [$this->pitch->id => 650]]);
        $b = $this->slot('7:00 AM');

        $this->courts()
            ->callTableAction('slotPrices', $this->turf, data: ['p' => [$a->id => '800', $b->id => '']])
            ->assertHasNoTableActionErrors();

        $this->assertSame(800.0, $a->fresh()->courtPriceList()[$this->turf->id]);
        $this->assertSame(650.0, $a->fresh()->courtPriceList()[$this->pitch->id], 'Another court\'s price is untouched.');
        $this->assertSame([], $b->fresh()->courtPriceList());

        // Clearing the box removes this court's price again.
        $this->courts()->callTableAction('slotPrices', $this->turf, data: ['p' => [$a->id => '', $b->id => '']]);
        $this->assertArrayNotHasKey($this->turf->id, $a->fresh()->courtPriceList());
    }

    public function test_a_court_with_upcoming_bookings_cannot_be_deleted(): void
    {
        Booking::forceCreate([
            'user_id' => User::first()->id, 'venue_id' => $this->venue->id, 'venue_court_id' => $this->pitch->id,
            'booking_type' => 'venue', 'status' => 'CONFIRMED', 'quantity' => 1, 'total_amount' => 500,
            'slot_date' => BusinessClock::today(), 'start_time' => '18:00', 'end_time' => '19:00',
        ]);

        $this->courts()->callTableAction('delete', $this->pitch);
        $this->assertNotNull($this->pitch->fresh(), 'Deleting would orphan an upcoming booking.');

        $this->courts()->callTableAction('delete', $this->turf);
        $this->assertNull($this->turf->fresh(), 'A court with nothing booked deletes normally.');
    }

    public function test_a_courts_sport_is_added_to_the_venue_once(): void
    {
        $this->venue->update(['sports' => ['cricket']]);   // old lowercase data

        $this->courts()->callTableAction('edit', $this->turf, data: [
            'name' => 'padi', 'kind' => 'court', 'sports' => ['Football', 'Cricket'], 'price' => 700, 'sort_order' => 0, 'is_active' => true,
        ])->assertHasNoTableActionErrors();

        $this->assertSame(['cricket', 'Football'], $this->venue->fresh()->sports);
    }

    public function test_slots_can_be_limited_to_a_sport_and_are_no_longer_dissociable(): void
    {
        $slot = $this->slot('6:00 AM');

        Livewire::test(SlotsRelationManager::class, ['ownerRecord' => $this->venue, 'pageClass' => EditVenue::class])
            ->assertOk()
            ->assertTableActionDoesNotExist('dissociate')
            ->assertTableActionDoesNotExist('associate')
            ->callTableAction('edit', $slot, data: ['day' => VenueSlot::EVERY_DAY, 'time' => '6:00 AM', 'sports' => ['Football'], 'is_available' => true])
            ->assertHasNoTableActionErrors();

        $this->assertSame(['Football'], $slot->fresh()->sportsList());
    }
}
