<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\Partner\PartnerDesk;
use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Support\PartnerBranchContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Today's Desk — the surface someone stands at.
 *
 * Its two real risks are seating a walk-in at the wrong branch, and seating one
 * on a unit that is already taken. Everything else is presentation.
 */
class PartnerDeskTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Venue $kora;
    private Venue $hsr;
    private VenueCourt $table4;

    protected function setUp(): void
    {
        parent::setUp();
        PartnerBranchContext::flush();

        // 7pm on a fixed day, so "busy now" is deterministic.
        Carbon::setTestNow(Carbon::parse('2026-08-20 19:00:00'));

        $this->owner = User::create([
            'name' => 'Big Bean Coffee',
            'email' => 'owner@bigbean.test',
            'password' => Hash::make('secret123'),
            'role' => 'PARTNER',
            'partner_type' => 'cafe',
            'status' => 'active',
        ]);

        $this->kora = $this->branch('Koramangala');
        $this->hsr = $this->branch('HSR');

        $this->table4 = VenueCourt::create([
            'venue_id' => $this->kora->id, 'name' => 'Table 04', 'kind' => 'table',
            'seats' => 4, 'price' => 200, 'is_active' => true,
        ]);
        VenueCourt::create([
            'venue_id' => $this->kora->id, 'name' => 'Pool', 'kind' => 'table',
            'seats' => null, 'price' => 300, 'is_active' => true,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('partner'));
        $this->actingAs($this->owner);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        PartnerBranchContext::flush();
        parent::tearDown();
    }

    private function branch(string $label): Venue
    {
        return Venue::create([
            'name' => 'Big Bean Coffee', 'branch_label' => $label, 'location' => $label,
            'price' => 200, 'is_active' => true, 'is_bookable' => true,
            'partner_id' => $this->owner->id,
        ]);
    }

    private function desk(): PartnerDesk
    {
        return new PartnerDesk;
    }

    // ---------------------------------------------------------------
    //  Which floor am I on
    // ---------------------------------------------------------------

    public function test_it_refuses_to_guess_the_branch(): void
    {
        // Two branches, nothing selected — the desk must ask, not pick.
        $this->assertNull($this->desk()->branch());
        $this->assertSame([], $this->desk()->floor());
    }

    public function test_it_follows_the_switcher(): void
    {
        PartnerBranchContext::select($this->kora->id);

        $this->assertSame($this->kora->id, $this->desk()->branch()?->id);
        $this->assertCount(2, $this->desk()->floor());
    }

    public function test_a_single_branch_partner_never_has_to_choose(): void
    {
        $solo = User::create([
            'name' => 'Solo Cafe', 'email' => 'solo@bigbean.test',
            'password' => Hash::make('secret123'), 'role' => 'PARTNER',
            'partner_type' => 'cafe', 'status' => 'active',
        ]);
        Venue::create([
            'name' => 'Solo Cafe', 'branch_label' => 'Indiranagar', 'location' => 'Indiranagar',
            'price' => 200, 'is_active' => true, 'is_bookable' => true, 'partner_id' => $solo->id,
        ]);

        $this->actingAs($solo);
        PartnerBranchContext::flush();

        $this->assertSame('Indiranagar', $this->desk()->branch()?->branchName());
    }

    // ---------------------------------------------------------------
    //  Access
    // ---------------------------------------------------------------

    public function test_an_event_host_has_no_floor(): void
    {
        $host = User::create([
            'name' => 'Gig Co', 'email' => 'host@haraan.test',
            'password' => Hash::make('secret123'), 'role' => 'PARTNER',
            'partner_type' => 'event', 'status' => 'active',
        ]);

        $this->actingAs($host);

        $this->assertFalse(PartnerDesk::canAccess());
    }

    public function test_staff_without_the_bookings_capability_cannot_open_it(): void
    {
        $gate = User::create([
            'name' => 'Gate', 'email' => 'gate@bigbean.test',
            'password' => Hash::make('secret123'), 'role' => 'PARTNER',
            'partner_type' => 'cafe', 'status' => 'active',
            'parent_partner_id' => $this->owner->id,
            'staff_permissions' => ['checkin'],
        ]);

        $this->actingAs($gate);

        $this->assertFalse(PartnerDesk::canAccess(), 'Seating someone is a bookings action.');
    }

    public function test_the_owner_can_open_it(): void
    {
        $this->assertTrue(PartnerDesk::canAccess());
    }

    // ---------------------------------------------------------------
    //  The floor's state
    // ---------------------------------------------------------------

    public function test_a_unit_is_busy_only_while_its_booking_is_running(): void
    {
        PartnerBranchContext::select($this->kora->id);

        // 6pm–8pm covers "now" (19:00).
        Booking::create([
            'quantity' => 1, 'total_amount' => 400, 'status' => 'CONFIRMED',
            'booking_type' => 'venue', 'user_id' => $this->owner->id,
            'venue_id' => $this->kora->id, 'venue_court_id' => $this->table4->id,
            'slot_date' => today()->toDateString(),
            'start_time' => '6:00 PM', 'end_time' => '8:00 PM',
            'channel' => 'offline', 'guest_name' => 'Anika',
        ]);

        $floor = collect($this->desk()->floor())->keyBy('name');

        $this->assertTrue($floor['Table 04']['busy']);
        $this->assertSame('Anika', $floor['Table 04']['guest']);
        $this->assertFalse($floor['Pool']['busy'], 'A different unit is unaffected.');

        $summary = $this->desk()->summary();
        $this->assertSame(1, $summary['busy']);
        $this->assertSame(1, $summary['free']);
    }

    public function test_a_later_booking_leaves_the_unit_free_now(): void
    {
        PartnerBranchContext::select($this->kora->id);

        // 9pm — two hours away. The table is free at 7pm and the desk must say so.
        Booking::create([
            'quantity' => 1, 'total_amount' => 400, 'status' => 'CONFIRMED',
            'booking_type' => 'venue', 'user_id' => $this->owner->id,
            'venue_id' => $this->kora->id, 'venue_court_id' => $this->table4->id,
            'slot_date' => today()->toDateString(),
            'start_time' => '9:00 PM', 'end_time' => '11:00 PM',
            'channel' => 'offline', 'guest_name' => 'Later',
        ]);

        $floor = collect($this->desk()->floor())->keyBy('name');

        $this->assertFalse($floor['Table 04']['busy']);
        $this->assertSame('9:00 PM', $floor['Table 04']['next_at']);
        $this->assertCount(1, $this->desk()->upcoming());
    }

    public function test_the_floor_shows_only_this_branch(): void
    {
        VenueCourt::create([
            'venue_id' => $this->hsr->id, 'name' => 'HSR Table 1',
            'kind' => 'table', 'seats' => 2, 'price' => 200, 'is_active' => true,
        ]);

        PartnerBranchContext::select($this->kora->id);

        $names = array_column($this->desk()->floor(), 'name');

        $this->assertContains('Table 04', $names);
        $this->assertNotContains('HSR Table 1', $names);
    }

    // ---------------------------------------------------------------
    //  Seating
    // ---------------------------------------------------------------

    public function test_seating_a_walk_in_creates_a_real_booking(): void
    {
        PartnerBranchContext::select($this->kora->id);

        Livewire::test(PartnerDesk::class)
            ->call('openSeat', $this->table4->id)
            ->set('guestName', 'Dev Menon')
            ->set('guestPhone', '9876543210')
            ->set('partySize', '4')
            ->set('hours', '2')
            ->call('seat')
            ->assertSet('seatingCourtId', null);

        $this->assertDatabaseHas('bookings', [
            'venue_id' => $this->kora->id,
            'venue_court_id' => $this->table4->id,
            'guest_name' => 'Dev Menon',
            'channel' => 'offline',
        ]);
    }

    public function test_a_party_too_big_for_the_table_is_refused(): void
    {
        PartnerBranchContext::select($this->kora->id);

        Livewire::test(PartnerDesk::class)
            ->call('openSeat', $this->table4->id)
            ->set('partySize', '9')          // Table 04 seats 4
            ->call('seat')
            ->assertSet('seatingCourtId', $this->table4->id); // sheet stays open

        $this->assertDatabaseMissing('bookings', ['venue_court_id' => $this->table4->id]);
    }

    public function test_a_unit_with_no_stated_capacity_never_blocks_on_party_size(): void
    {
        PartnerBranchContext::select($this->kora->id);
        $pool = VenueCourt::where('name', 'Pool')->firstOrFail();

        Livewire::test(PartnerDesk::class)
            ->call('openSeat', $pool->id)
            ->set('partySize', '12')
            ->call('seat')
            ->assertSet('seatingCourtId', null);

        $this->assertDatabaseHas('bookings', ['venue_court_id' => $pool->id]);
    }

    public function test_it_cannot_seat_on_another_branchs_unit(): void
    {
        $theirs = VenueCourt::create([
            'venue_id' => $this->hsr->id, 'name' => 'HSR Table 1',
            'kind' => 'table', 'seats' => 4, 'price' => 200, 'is_active' => true,
        ]);

        PartnerBranchContext::select($this->kora->id);

        // A tampered id from another branch must not book anything.
        Livewire::test(PartnerDesk::class)
            ->call('openSeat', $theirs->id)
            ->set('guestName', 'Nope')
            ->call('seat');

        $this->assertDatabaseMissing('bookings', ['venue_court_id' => $theirs->id]);
    }

    public function test_it_renders_the_floor(): void
    {
        PartnerBranchContext::select($this->kora->id);

        Livewire::test(PartnerDesk::class)
            ->assertOk()
            ->assertSee('Table 04')
            ->assertSee('Seat a walk-in')
            ->assertSee('free now');
    }

    public function test_it_asks_which_floor_when_nothing_is_selected(): void
    {
        Livewire::test(PartnerDesk::class)
            ->assertOk()
            ->assertSee('Which floor are you on?')
            ->assertSee('Koramangala')
            ->assertSee('HSR');
    }
}
