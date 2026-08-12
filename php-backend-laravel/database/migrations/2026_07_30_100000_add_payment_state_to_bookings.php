<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Separate "how much money actually arrived" from `bookings.status`.
 *
 * Until now status did both jobs: revenue everywhere is "sum total_amount where
 * lower(status) is in a paid set", so a CONFIRMED booking silently meant "paid in
 * full". That holds for Razorpay event orders and is simply false for venues —
 * BookingService::reserveVenue() writes CONFIRMED with no payment step at all, so
 * a ₹4,400 turf booking with a ₹500 advance was indistinguishable from one paid
 * in full. Advance/balance, refunds, cash collection and settlement all need the
 * two concerns split.
 *
 * `amount_paid` is DERIVED — it is the sum of the booking_payments ledger and is
 * only ever written by BookingLedger. Never set it from a caller.
 *
 * @see \App\Services\BookingLedger
 * @see docs/venue-partner-phase-1.md
 */
return new class extends Migration
{
    /** Statuses the reporting layer already treats as money collected. */
    private const PAID_STATUSES = ['confirmed', 'paid', 'completed', 'checked_in'];

    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->decimal('amount_paid', 10, 2)->default(0)->after('total_amount');
            $table->string('payment_status', 20)->default('unpaid')->after('amount_paid');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->index(['venue_id', 'payment_status'], 'bookings_venue_payment_idx');
        });

        // Backfill: everything the reporting layer counts as revenue today must keep
        // counting once those queries move onto amount_paid. Marking these `paid`
        // makes that later switch a no-op for existing rows instead of zeroing
        // history. Cancelled/expired keep the column defaults (unpaid, 0).
        DB::table('bookings')
            ->whereIn(DB::raw('lower(status)'), self::PAID_STATUSES)
            ->update([
                'amount_paid' => DB::raw('total_amount'),
                'payment_status' => 'paid',
            ]);
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex('bookings_venue_payment_idx');
            $table->dropColumn(['amount_paid', 'payment_status']);
        });
    }
};
