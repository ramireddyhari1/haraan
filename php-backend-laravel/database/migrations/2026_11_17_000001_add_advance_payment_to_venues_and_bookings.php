<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Advance payment for venue bookings.
 *
 *  - venues.advance_percent: set by Haraan in /control. NULL or 0 = the customer pays the
 *    whole booking online (today's behaviour). 1–99 = the customer may pay only that share of
 *    the court price online and settle the rest at the venue.
 *  - bookings.pay_at_venue: the part of a booking's court price the customer chose to pay at
 *    the venue. The online charge is amountCharged() − pay_at_venue; the partner desk sees it
 *    as balance due.
 *
 * ADD COLUMN only. On SQLite any ->change() on `venues` rebuilds the table and the FK cascade
 * wipes every court, slot and review — never alter an existing venues column here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('venues', 'advance_percent')) {
            Schema::table('venues', function (Blueprint $table): void {
                $table->decimal('advance_percent', 5, 2)->nullable();
            });
        }

        if (! Schema::hasColumn('bookings', 'pay_at_venue')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->decimal('pay_at_venue', 10, 2)->default(0);
            });
        }
    }

    public function down(): void
    {
        // Deliberately a no-op: dropColumn on SQLite rebuilds the table and cascades.
    }
};
