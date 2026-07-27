<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which session a ticket booking is for. Nullable + SET NULL so deleting a slot
 * never erases the revenue history of tickets sold against it.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('event_slot_id')
                ->nullable()
                ->after('ticket_type_id')
                ->constrained('event_slots')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('event_slot_id');
        });
    }
};
