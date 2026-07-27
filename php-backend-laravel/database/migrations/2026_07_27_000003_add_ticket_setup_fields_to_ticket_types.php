<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The redesigned ticket card's fields:
 *  - event_slot_id: which session this tier belongs to. Null = applies to every
 *    slot ("Same for all slots"); set = one slot only ("Customize per slot").
 *  - description: the optional blurb shown under the tier name.
 *  - visible: hosts can hide a tier from buyers without deleting it.
 *  - bulk_booking + min/max_per_order: allow (and bound) buying many at once.
 *
 * `capacity` stays nullable (null = unlimited); the UI surfaces that as "-1".
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table): void {
            $table->foreignId('event_slot_id')
                ->nullable()
                ->after('event_id')
                ->constrained('event_slots')
                ->cascadeOnDelete();
            $table->text('description')->nullable()->after('name');
            $table->boolean('visible')->default(true)->after('sort');
            $table->boolean('bulk_booking')->default(false)->after('visible');
            $table->unsignedInteger('min_per_order')->nullable()->after('bulk_booking');
            $table->unsignedInteger('max_per_order')->nullable()->after('min_per_order');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('event_slot_id');
            $table->dropColumn([
                'description',
                'visible',
                'bulk_booking',
                'min_per_order',
                'max_per_order',
            ]);
        });
    }
};
