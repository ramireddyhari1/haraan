<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\User;
use App\Services\BookingLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Money state on a booking is derived, never hand-set.
 *
 * The invariant these tests exist to hold:
 *
 *     bookings.amount_paid === SUM(booking_payments.amount)
 *
 * If someone later writes amount_paid directly, assertLedgerIntact() catches it
 * here rather than a partner catching it in their settlement.
 */
class BookingLedgerTest extends TestCase
{
    use RefreshDatabase;

    private BookingLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ledger = app(BookingLedger::class);
    }

    private ?User $desk = null;

    private function staff(): User
    {
        return $this->desk ??= User::create([
            'name' => 'Lakshmi Devi',
            'email' => 'desk@haraan.test',
            'password' => Hash::make('secret123'),
            'role' => 'PARTNER',
            'status' => 'active',
        ]);
    }

    private function booking(float $total = 4400): Booking
    {
        return Booking::create([
            'quantity' => 1,
            'total_amount' => $total,
            'status' => 'CONFIRMED',
            'booking_type' => 'venue',
            'user_id' => $this->staff()->id,
            'channel' => 'offline',
        ]);
    }

    /** The invariant, checked across every booking in the database. */
    private function assertLedgerIntact(): void
    {
        foreach (Booking::all() as $booking) {
            $sum = (float) BookingPayment::where('booking_id', $booking->id)->sum('amount');

            $this->assertEqualsWithDelta(
                $sum,
                (float) $booking->amount_paid,
                0.001,
                "amount_paid drifted from the ledger on booking {$booking->id}",
            );
        }
    }

    public function test_a_new_venue_booking_starts_unpaid(): void
    {
        $booking = $this->booking();

        $this->assertSame('unpaid', $booking->payment_status);
        $this->assertSame(0.0, (float) $booking->amount_paid);
        $this->assertSame(4400.0, $booking->balanceDue());
        $this->assertTrue($booking->hasBalanceDue());
        $this->assertLedgerIntact();
    }

    /** The ₹500-advance booking that is the default in India. */
    public function test_an_advance_then_the_balance_walks_unpaid_to_partial_to_paid(): void
    {
        $booking = $this->booking();
        $desk = $this->staff();

        $this->ledger->collect($booking, 500, 'upi', $desk, 'UPI-8891');
        $booking->refresh();

        $this->assertSame('partial', $booking->payment_status);
        $this->assertSame(500.0, (float) $booking->amount_paid);
        $this->assertSame(3900.0, $booking->balanceDue());

        $this->ledger->collect($booking, 3900, 'cash', $desk);
        $booking->refresh();

        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame(0.0, $booking->balanceDue());
        $this->assertFalse($booking->hasBalanceDue());
        $this->assertLedgerIntact();
    }

    /** Ten people splitting ₹4,400 needs no new concept — ten rows, one booking. */
    public function test_a_split_payment_across_a_group_settles_the_booking(): void
    {
        $booking = $this->booking();
        $desk = $this->staff();

        foreach (range(1, 10) as $i) {
            $this->ledger->collect($booking, 440, 'upi', $desk, "UPI-{$i}");
        }

        $booking->refresh();

        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame(4400.0, (float) $booking->amount_paid);
        $this->assertCount(10, $booking->payments);
        $this->assertLedgerIntact();
    }

    public function test_a_full_refund_reads_refunded_not_unpaid(): void
    {
        $booking = $this->booking();

        $this->ledger->collect($booking, 4400, 'online', null, 'pay_abc');
        $this->ledger->refund($booking, 4400, 'online', null, 'rfnd_abc');
        $booking->refresh();

        $this->assertSame('refunded', $booking->payment_status);
        $this->assertSame(0.0, (float) $booking->amount_paid);
        // A refunded booking is not a chase target.
        $this->assertFalse($booking->hasBalanceDue());
        $this->assertLedgerIntact();
    }

    public function test_a_partial_refund_reads_part_refunded(): void
    {
        $booking = $this->booking();

        $this->ledger->collect($booking, 4400, 'online');
        $this->ledger->refund($booking, 2200, 'online');
        $booking->refresh();

        $this->assertSame('part_refunded', $booking->payment_status);
        $this->assertSame(2200.0, (float) $booking->amount_paid);
        $this->assertLedgerIntact();
    }

    public function test_refunds_are_stored_as_negative_rows(): void
    {
        $booking = $this->booking();

        $this->ledger->collect($booking, 1000);
        $refund = $this->ledger->refund($booking, 400);

        $this->assertSame(-400.0, (float) $refund->amount);
        $this->assertTrue($refund->isRefund());
        $this->assertSame(600.0, (float) $booking->refresh()->amount_paid);
    }

    /** Cash-drawer money is identifiable — this is the shift close-out query. */
    public function test_the_ledger_answers_who_collected_what_over_the_counter(): void
    {
        $desk = $this->staff();
        $booking = $this->booking();

        $this->ledger->collect($booking, 1400, 'cash', $desk);
        $this->ledger->collect($booking, 1000, 'upi', $desk);
        $this->ledger->collect($booking, 2000, 'online');   // gateway — no collector

        $overTheCounter = BookingPayment::where('collected_by', $desk->id)
            ->get()
            ->filter(fn (BookingPayment $p): bool => $p->isOverTheCounter())
            ->sum('amount');

        $this->assertSame(2400.0, (float) $overTheCounter);
        $this->assertNull(BookingPayment::where('method', 'online')->first()->collected_by);
    }

    public function test_recompute_repairs_a_booking_edited_out_of_band(): void
    {
        $booking = $this->booking();
        $this->ledger->collect($booking, 4400);

        // Simulate a direct write that bypassed the ledger.
        $booking->forceFill(['amount_paid' => 0, 'payment_status' => 'unpaid'])->save();

        $this->ledger->recompute($booking->refresh());

        $this->assertSame('paid', $booking->refresh()->payment_status);
        $this->assertLedgerIntact();
    }

    public function test_status_derivation_table(): void
    {
        $cases = [
            //  paid, total, hasRefund, expected
            [0.0, 1000.0, false, 'unpaid'],
            [400.0, 1000.0, false, 'partial'],
            [1000.0, 1000.0, false, 'paid'],
            [1200.0, 1000.0, false, 'paid'],      // overpaid still reads paid
            [0.0, 1000.0, true, 'refunded'],
            [600.0, 1000.0, true, 'part_refunded'],
        ];

        foreach ($cases as [$paid, $total, $hasRefund, $expected]) {
            $this->assertSame(
                $expected,
                $this->ledger->statusFor($paid, $total, $hasRefund),
                "statusFor({$paid}, {$total}, " . var_export($hasRefund, true) . ')',
            );
        }
    }
}
