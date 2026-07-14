<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A venue booking now reserves a specific court for a time window, not just a slot row.
 * This is what makes overlap detection correct: two bookings conflict when they share a
 * court on a date and their [start,end) windows overlap — regardless of sport.
 *
 * Times are stored as normalised 24h "HH:MM" strings (sports venues run within a single
 * day, no midnight wrap), so a plain range comparison is enough.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookings', 'venue_court_id')) {
                $table->unsignedBigInteger('venue_court_id')->nullable()->index()->after('venue_slot_id');
            }
            if (! Schema::hasColumn('bookings', 'start_time')) {
                $table->string('start_time', 5)->nullable()->after('slot_date');
            }
            if (! Schema::hasColumn('bookings', 'end_time')) {
                $table->string('end_time', 5)->nullable()->after('start_time');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            foreach (['venue_court_id', 'start_time', 'end_time'] as $col) {
                if (Schema::hasColumn('bookings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
