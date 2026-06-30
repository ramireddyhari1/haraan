<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase B — venue-slot bookings. A booking is now either an event ticket or a venue
 * slot reservation, distinguished by `booking_type`. Venue columns are nullable (event
 * bookings leave them null) and `event_id` becomes nullable (venue bookings have no event).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookings', 'booking_type')) {
                $table->string('booking_type')->default('event')->index()->after('status');
            }
            if (! Schema::hasColumn('bookings', 'venue_id')) {
                $table->unsignedBigInteger('venue_id')->nullable()->index()->after('event_id');
            }
            if (! Schema::hasColumn('bookings', 'venue_slot_id')) {
                $table->unsignedBigInteger('venue_slot_id')->nullable()->after('venue_id');
            }
            if (! Schema::hasColumn('bookings', 'slot_date')) {
                $table->date('slot_date')->nullable()->after('venue_slot_id');
            }
            if (! Schema::hasColumn('bookings', 'slot_label')) {
                $table->string('slot_label')->nullable()->after('slot_date');
            }
        });

        // Venue bookings have no event — relax the NOT NULL on event_id.
        Schema::table('bookings', function (Blueprint $table): void {
            $table->unsignedBigInteger('event_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            foreach (['booking_type', 'venue_id', 'venue_slot_id', 'slot_date', 'slot_label'] as $col) {
                if (Schema::hasColumn('bookings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
