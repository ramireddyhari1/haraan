<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Footage a paired camera sent back, one row per clip.
 *
 * Clips, not a stream. Cricket is played in discrete deliveries with long gaps, so a
 * camera that records eight seconds around a ball and uploads it needs no signalling
 * server, no TURN relay and no held-open socket — and it survives the ground Wi-Fi that a
 * live stream would not. `over_ball` is what the scorer actually searches by.
 *
 * Deliberately NOT storing any verdict. What a phone can honestly provide is footage
 * to look at; adjudication from one uncalibrated 30fps camera is a different problem
 * and inventing a column for it would invite something to fill it in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_device_clips', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('match_id')->index();
            $table->unsignedBigInteger('device_id')->index();
            $table->string('role', 40);
            // Stored on the `public` disk; the column holds the disk-relative path.
            $table->string('path');
            $table->unsignedBigInteger('bytes')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            // "9.4" — the delivery the camera believes this covers, as the scorer's
            // screen showed it when the clip was cut. Nullable: a camera started
            // mid-over has nothing honest to put here.
            $table->string('over_ball', 12)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_device_clips');
    }
};
