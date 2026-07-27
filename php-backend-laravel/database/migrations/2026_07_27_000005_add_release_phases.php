<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sequential ticket release ("Release Phases"). An event defines an ordered list
 * of named phases (Early Bird → Phase 2 → …); each ticket tier is assigned to a
 * phase via its index. Phase 0 is on sale immediately; a later phase opens once
 * every capacity-bearing tier in the earlier phases has sold out (see
 * Event::phaseReleased()).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            // Ordered list of { name: string } phase definitions.
            $table->json('release_phases')->nullable()->after('tickets_per_slot');
        });

        Schema::table('ticket_types', function (Blueprint $table): void {
            // Which phase this tier belongs to (index into events.release_phases).
            $table->unsignedInteger('release_phase')->default(0)->after('visible');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('release_phases');
        });
        Schema::table('ticket_types', function (Blueprint $table): void {
            $table->dropColumn('release_phase');
        });
    }
};
