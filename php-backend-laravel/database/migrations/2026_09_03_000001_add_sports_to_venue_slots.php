<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opening hours belong to a sport, not just to a venue.
 *
 * A slot row was venue-wide: one set of times shared by every court. On a venue running
 * badminton, football and cricket that made "football evenings only" inexpressible — the
 * desk grid offered a 06:00 AM turf cell at the full turf rate, because the 6 AM row that
 * exists for the badminton courts applies to every column.
 *
 * `sports` is the same shape as {@see \App\Models\VenueCourt::$sports}, so eligibility is a
 * set intersection between the slot and the court rather than a new concept: a slot lists
 * the sports it runs for, a court lists the sports it hosts, and a booking needs the two to
 * overlap.
 *
 * Deliberately NOT backfilled. Null means "every sport", which is exactly what every
 * existing row already means, so single-sport venues and today's multi-sport venues both
 * keep behaving as they do now until someone narrows a slot on purpose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_slots', function (Blueprint $table): void {
            $table->json('sports')->nullable()->after('capacity');
        });
    }

    public function down(): void
    {
        Schema::table('venue_slots', function (Blueprint $table): void {
            $table->dropColumn('sports');
        });
    }
};
