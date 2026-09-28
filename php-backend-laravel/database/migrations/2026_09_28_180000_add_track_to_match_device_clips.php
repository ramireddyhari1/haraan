<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ball track the camera phone measured while it filmed the clip.
 *
 * The camera runs its own tracker during every delivery, stamped with the sensor's clock.
 * Until now that track stayed on the phone and was thrown away, so the scorer could only
 * see a ball path by asking a model to re-find the ball in the video. Stored here, it is
 * what the side-on flight view is drawn from — measured, immediate, and free.
 *
 * Sanitised JSON (see App\Support\ClipTrack), never the raw request body. Null for clips
 * with no sightings and every clip recorded before this existed. Added nullable only — no
 * ->change(), which on SQLite rebuilds the table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_device_clips', function (Blueprint $table): void {
            $table->text('track')->nullable()->after('ball_seq');
        });
    }

    public function down(): void
    {
        Schema::table('match_device_clips', function (Blueprint $table): void {
            $table->dropColumn('track');
        });
    }
};
