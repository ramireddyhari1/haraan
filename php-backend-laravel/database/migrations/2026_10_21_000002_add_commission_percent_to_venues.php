<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Haraan's commission on one venue's online bookings, set in /control. NULL = the platform
 * default (Platform rules → Fees → Pulse commission); 0 = free.
 *
 * ADD COLUMN only. On SQLite any ->change() on `venues` rebuilds the table and the FK
 * cascade wipes every court, slot and review (see the 2026-09-25 incident) — never alter
 * an existing venues column here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('venues', 'commission_percent')) {
            return;
        }

        Schema::table('venues', function (Blueprint $table): void {
            $table->decimal('commission_percent', 5, 2)->nullable();
        });
    }

    public function down(): void
    {
        // Deliberately a no-op: dropColumn on SQLite rebuilds `venues` and cascades into
        // its courts/slots/reviews. A stray nullable column is harmless.
    }
};
