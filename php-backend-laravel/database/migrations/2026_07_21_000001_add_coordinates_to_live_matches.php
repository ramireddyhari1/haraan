<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Real coordinates on every match — the missing piece for "matches near me".
 *
 * Until now a match carried only free-text place data (`venue`, `locality`,
 * `district`), so proximity could never be more than a string comparison. The
 * app now demands a GPS fix before a public match can be created, and stamps
 * the fix here.
 *
 * Nullable because every pre-existing row predates the requirement; those sort
 * last in the near-me list rather than disappearing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_matches', function (Blueprint $table) {
            // 10,7 → ~1cm resolution, far more than a playing ground needs.
            $table->decimal('latitude', 10, 7)->nullable()->after('locality');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');

            // Bounding-box prefilter for the near-me feed (a full haversine over
            // every row doesn't scale; the box narrows candidates first).
            $table->index(['latitude', 'longitude'], 'live_matches_geo_idx');
        });
    }

    public function down(): void
    {
        Schema::table('live_matches', function (Blueprint $table) {
            $table->dropIndex('live_matches_geo_idx');
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
