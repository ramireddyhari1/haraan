<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every rupee that moves against a booking, with its method and who took it.
 *
 * `amount` is SIGNED — positive is money in, negative is a refund. That way
 * bookings.amount_paid is one SUM() and can never disagree with itself because a
 * caller forgot to read a `direction` column. Reporting that wants gross-in vs
 * refunds-out filters on the sign.
 *
 * This one table is what makes the "₹500 advance, balance at the venue" booking
 * representable, and it is also the shift-close-out query: `collected_by` +
 * `collected_at` already answer "how much cash did Lakshmi take on Saturday
 * evening", so cash reconciliation only has to add the drawer count on top.
 *
 * @see \App\Services\BookingLedger
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();

            // Signed. Positive = collected, negative = refunded.
            $table->decimal('amount', 10, 2);

            // cash | upi | card | online | wallet | adjustment
            $table->string('method', 20)->default('cash');

            // The staff member who physically took it. Null for online/gateway money.
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();

            // razorpay_payment_id, a UPI reference, a paper receipt number…
            $table->string('reference')->nullable();
            $table->string('note')->nullable();

            $table->timestamp('collected_at');
            $table->timestamps();

            $table->index(['booking_id', 'collected_at']);
            // Shift close-out reads this pair directly.
            $table->index(['collected_by', 'collected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_payments');
    }
};
