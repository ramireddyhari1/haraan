<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a minimum-TICKETS rule to coupons.
 *
 * The studio only had `min_order` — a ₹ amount — and hosts kept reading it as a ticket
 * count: entering "2" meant "orders over ₹2", so a single ticket qualified and the
 * intended "2 tickets or more" offer applied to everyone. The two rules are genuinely
 * different (₹1,000 of one ticket vs two ₹99 ones), so this is its own column rather
 * than a reinterpretation of the old one.
 *
 * Null / 0 = no minimum, which is what every existing coupon reads as. Nothing changes
 * for coupons already out there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table): void {
            if (! Schema::hasColumn('coupons', 'min_tickets')) {
                $table->integer('min_tickets')->nullable()->after('min_order');
            }
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table): void {
            if (Schema::hasColumn('coupons', 'min_tickets')) {
                $table->dropColumn('min_tickets');
            }
        });
    }
};
