<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Structured operating hours (per weekday) + a cancellation policy on venues.
 *
 * hours_json drives two things the free-text "6 AM–11 PM" string never could: the bookable
 * start-times are generated from open→close (no more hand-entered slots), and bookings are
 * refused on closed days. The old `hours` string stays as a derived display value.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table): void {
            // { "Mon": {"open":"06:00","close":"23:00"}, "Sun": {"closed":true}, ... }
            $table->json('hours_json')->nullable()->after('hours');
            // Start-time granularity for generated slots (minutes).
            $table->unsignedInteger('slot_minutes')->default(60)->after('hours_json');
            // Cancellation policy: free until N hours before, then refund X%.
            $table->unsignedInteger('cancel_free_hours')->nullable()->after('slot_minutes');
            $table->unsignedInteger('cancel_refund_percent')->nullable()->after('cancel_free_hours');
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table): void {
            foreach (['hours_json', 'slot_minutes', 'cancel_free_hours', 'cancel_refund_percent'] as $col) {
                if (Schema::hasColumn('venues', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
