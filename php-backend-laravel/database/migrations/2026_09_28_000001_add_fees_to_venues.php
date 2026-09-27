<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Venue fees the admin names themselves ("Floodlight charge", "Maintenance fee"), each flat ₹
 * or % of the slot subtotal, charged on top of the existing convenience fee.
 *
 * ADD COLUMN only. On SQLite any ->change() on `venues` rebuilds the table and the FK
 * cascade wipes every court, slot and review (see the 2026-09-25 incident) — never alter
 * an existing venues column here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('venues', 'fees')) {
            return;
        }

        Schema::table('venues', function (Blueprint $table): void {
            $table->json('fees')->nullable();
        });
    }

    public function down(): void
    {
        // Deliberately a no-op: dropColumn on SQLite rebuilds `venues` and cascades into
        // its courts/slots/reviews. A stray nullable column is harmless.
    }
};
