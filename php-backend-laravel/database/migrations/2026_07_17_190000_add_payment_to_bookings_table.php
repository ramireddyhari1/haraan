<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment columns for the reserve→confirm flow. A booking is created PENDING (holding
 * inventory) with a `razorpay_order_id` and a `reserved_until` deadline; once the payment
 * signature verifies it flips to CONFIRMED and records `razorpay_payment_id`. Expired
 * PENDING holds are swept and their inventory restored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('razorpay_order_id')->nullable()->after('status')->index();
            $table->string('razorpay_payment_id')->nullable()->after('razorpay_order_id');
            $table->timestamp('reserved_until')->nullable()->after('razorpay_payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['razorpay_order_id', 'razorpay_payment_id', 'reserved_until']);
        });
    }
};
