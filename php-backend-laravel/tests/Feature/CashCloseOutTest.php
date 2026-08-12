<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Shifts\Pages\ListShiftSessions;
use App\Filament\Resources\Shifts\ShiftSessionResource;
use App\Models\Booking;
use App\Models\ShiftSession;
use App\Models\User;
use App\Models\Venue;
use App\Services\BookingLedger;
use App\Services\ShiftService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cash reconciliation.
 *
 * The system claims how much cash should be in the drawer, a human counts it, and
 * the difference is recorded against a named person. Only cash creates variance —
 * UPI and card land in the account, gateway money never touches a desk.
 */
class CashCloseOutTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $desk;
    private Venue $venue;
    private BookingLedger $ledger;
    private ShiftService $shifts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = app(BookingLedger::class);
        $this->shifts = app(ShiftService::class);

        $this->owner = User::create([
            'name' => 'Rahul Krishnan', 'email' => 'owner@haraan.test',
            'password' => Hash::make('secret123'), 'role' => 'PARTNER',
            'partner_type' => 'venue', 'status' => 'active',
        ]);

        $this->venue = Venue::create([
            'name' => 'Sportz Arena', 'location' => 'Gachibowli', 'price' => 1400,
            'is_active' => true, 'is_bookable' => true, 'partner_id' => $this->owner->id,
        ]);

        $this->desk = User::create([
            'name' => 'Lakshmi Devi', 'email' => 'desk@haraan.test',
            'password' => Hash::make('secret123'), 'role' => 'PARTNER',
            'partner_type' => 'venue', 'status' => 'active',
            'parent_partner_id' => $this->owner->id,
            'staff_permissions' => ['bookings', 'checkin'],
        ]);

        Filament::setCurrentPanel(Filament::getPanel('partner'));
    }

    private function booking(float $total = 1400): Booking
    {
        return Booking::create([
            'quantity' => 1, 'total_amount' => $total, 'status' => 'CONFIRMED',
            'booking_type' => 'venue', 'user_id' => $this->owner->id,
            'venue_id' => $this->venue->id, 'slot_date' => today()->toDateString(),
            'start_time' => '19:00', 'end_time' => '20:00', 'channel' => 'offline',
        ]);
    }

    // ------------------------------------------------------------- attribution

    /**
     * The design rule: requiring staff to press "start shift" means the day they
     * forget, the cash is unattributed and the control is worthless.
     */
    public function test_taking_cash_opens_a_shift_by_itself(): void
    {
        $this->assertNull($this->shifts->find($this->desk, $this->venue->id));

        $this->ledger->collect($this->booking(), 1400, 'cash', $this->desk);

        $shift = $this->shifts->find($this->desk, $this->venue->id);

        $this->assertNotNull($shift);
        $this->assertTrue($shift->isOpen());
        $this->assertSame(1400.0, $shift->cashMovement());
    }

    public function test_all_cash_in_one_shift_lands_in_one_drawer(): void
    {
        foreach ([1400, 650, 900] as $amount) {
            $this->ledger->collect($this->booking($amount), $amount, 'cash', $this->desk);
        }

        $shifts = ShiftSession::where('user_id', $this->desk->id)->get();

        $this->assertCount(1, $shifts, 'A second drawer must not appear mid-shift.');
        $this->assertSame(2950.0, $shifts->first()->cashMovement());
    }

    public function test_gateway_money_never_joins_a_drawer(): void
    {
        $this->ledger->collect($this->booking(), 1400, 'online', null, 'pay_abc');

        $this->assertNull($this->shifts->find($this->desk, $this->venue->id));
        $this->assertDatabaseHas('booking_payments', ['method' => 'online', 'shift_session_id' => null]);
    }

    public function test_upi_and_card_are_tracked_but_excluded_from_the_drawer(): void
    {
        $this->ledger->collect($this->booking(), 1400, 'cash', $this->desk);
        $this->ledger->collect($this->booking(), 1000, 'upi', $this->desk);
        $this->ledger->collect($this->booking(), 800, 'card', $this->desk);

        $shift = $this->shifts->find($this->desk, $this->venue->id);

        $this->assertSame(1400.0, $shift->cashMovement(), 'Only cash can go missing from a drawer.');
        $this->assertSame(1800.0, $shift->digitalMovement());
        $this->assertSame(1400.0, $shift->expectedCash());
    }

    public function test_a_cash_refund_comes_back_out_of_the_same_drawer(): void
    {
        $booking = $this->booking(1400);
        $this->ledger->collect($booking, 1400, 'cash', $this->desk);
        $this->ledger->refund($booking, 400, 'cash', $this->desk);

        $this->assertSame(1000.0, $this->shifts->find($this->desk, $this->venue->id)->cashMovement());
    }

    public function test_two_staff_keep_separate_drawers(): void
    {
        $this->ledger->collect($this->booking(), 1400, 'cash', $this->desk);
        $this->ledger->collect($this->booking(), 900, 'cash', $this->owner);

        $this->assertSame(1400.0, $this->shifts->find($this->desk, $this->venue->id)->cashMovement());
        $this->assertSame(900.0, $this->shifts->find($this->owner, $this->venue->id)->cashMovement());
    }

    // ---------------------------------------------------------------- close-out

    public function test_the_opening_float_is_part_of_what_must_be_counted(): void
    {
        $shift = $this->shifts->open($this->desk, $this->venue->id, 2000);
        $this->ledger->collect($this->booking(), 1400, 'cash', $this->desk);

        $this->assertSame(3400.0, $shift->refresh()->expectedCash());
    }

    public function test_a_square_drawer_records_zero_variance(): void
    {
        $shift = $this->shifts->open($this->desk, $this->venue->id, 500);
        $this->ledger->collect($this->booking(), 1400, 'cash', $this->desk);

        $this->shifts->close($shift->refresh(), 1900, $this->owner);
        $shift->refresh();

        $this->assertFalse($shift->isOpen());
        $this->assertSame(0.0, (float) $shift->variance);
        $this->assertSame('Square', $shift->varianceLabel());
    }

    public function test_a_short_drawer_is_recorded_against_the_person_on_duty(): void
    {
        $shift = $this->shifts->open($this->desk, $this->venue->id);
        $this->ledger->collect($this->booking(), 1400, 'cash', $this->desk);

        $this->shifts->close($shift->refresh(), 1100, $this->owner, 'Counted twice');
        $shift->refresh();

        $this->assertSame(-300.0, (float) $shift->variance);
        $this->assertSame('Short', $shift->varianceLabel());
        $this->assertSame($this->desk->id, $shift->user_id, 'The variance belongs to whoever was on duty…');
        $this->assertSame($this->owner->id, $shift->closed_by, '…not to whoever closed it.');
    }

    /**
     * A later correction to the ledger must not retroactively make a short shift
     * look square — the close-out is a record of what was true when counted.
     */
    public function test_a_closed_variance_is_frozen_against_later_ledger_edits(): void
    {
        $shift = $this->shifts->open($this->desk, $this->venue->id);
        $this->ledger->collect($this->booking(), 1400, 'cash', $this->desk);

        $this->shifts->close($shift->refresh(), 1100, $this->owner);
        $this->assertSame(-300.0, (float) $shift->refresh()->variance);

        // Money recorded after the fact against the same (now closed) shift.
        $this->ledger->collect($this->booking(), 300, 'cash', $this->desk);

        $this->assertSame(-300.0, (float) $shift->refresh()->variance, 'History must not move.');
    }

    public function test_opening_a_shift_twice_reuses_the_open_one(): void
    {
        $first = $this->shifts->open($this->desk, $this->venue->id, 500);
        $second = $this->shifts->open($this->desk, $this->venue->id, 9999);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(500.0, (float) $second->opening_float, 'The float is not silently overwritten.');
    }

    public function test_cash_taken_with_no_shift_is_surfaced_as_unattributed(): void
    {
        // A payment recorded with no collector — nobody has claimed this money.
        $this->ledger->collect($this->booking(), 700, 'cash', null);

        $this->assertSame(700.0, $this->shifts->unattributedCash($this->venue->id));
    }

    // -------------------------------------------------------------- permissions

    public function test_a_desk_person_sees_only_their_own_drawer(): void
    {
        $this->ledger->collect($this->booking(), 1400, 'cash', $this->desk);
        $this->ledger->collect($this->booking(), 900, 'cash', $this->owner);

        $this->actingAs($this->desk);
        $ids = ShiftSessionResource::getEloquentQuery()->pluck('user_id')->unique()->all();
        $this->assertSame([$this->desk->id], array_values($ids));

        $this->actingAs($this->owner);
        $this->assertCount(2, ShiftSessionResource::getEloquentQuery()->get(), 'An owner sees the whole desk.');
    }

    public function test_a_close_out_cannot_be_deleted_or_edited(): void
    {
        $shift = $this->shifts->open($this->desk, $this->venue->id);
        $this->actingAs($this->owner);

        $this->assertFalse(ShiftSessionResource::canDelete($shift), 'Deleting would destroy the audit trail.');
        $this->assertFalse(ShiftSessionResource::canEdit($shift));
        $this->assertFalse(ShiftSessionResource::canCreate());
    }

    public function test_closing_through_the_table_action_records_the_variance(): void
    {
        $shift = $this->shifts->open($this->desk, $this->venue->id, 500);
        $this->ledger->collect($this->booking(), 1400, 'cash', $this->desk);

        $this->actingAs($this->owner);

        Livewire::test(ListShiftSessions::class)
            ->callTableAction('close', $shift, ['counted_cash' => 1800])
            ->assertHasNoTableActionErrors();

        $shift->refresh();

        $this->assertFalse($shift->isOpen());
        $this->assertSame(1800.0, (float) $shift->counted_cash);
        $this->assertSame(-100.0, (float) $shift->variance);
    }
}
