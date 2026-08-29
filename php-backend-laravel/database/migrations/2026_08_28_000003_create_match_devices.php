<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-device match sessions.
 *
 * A match is scored on one phone, but there is more than one thing worth pointing a
 * camera at — the stumps for a review, the bowler's action, and whatever comes after
 * those. This table is the join between a match and every OTHER device taking part in
 * it, and it is deliberately about DEVICES AND ROLES rather than about cameras: the
 * next feature that needs a second phone should need no new pairing code.
 *
 * Two secrets, doing two different jobs:
 *
 *   · `pair_token` is what goes in the QR code and the link. Short-lived, single-use,
 *     and NOT the match id — a pairing link that leaked would otherwise be a permanent
 *     invitation into a match. It buys exactly one claim, once, within ten minutes.
 *   · `session_token` is minted at claim time and is what the paired device sends for
 *     the rest of the match. Revoking a device clears it, which is what makes "remove
 *     this phone" actually mean something.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_devices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('match_id')->index();
            // LBW_AI_CAMERA | BOWLER_ANALYSIS_CAMERA — the role the second device plays.
            // Stored as a string, not an enum: the whole point is that roles are added.
            $table->string('role', 40);
            $table->string('pair_token', 32)->unique();
            $table->timestamp('token_expires_at')->nullable();
            // pending → connected → revoked. Expiry is derived from token_expires_at
            // rather than stored, so a clock is never the thing that grants access.
            $table->string('status', 20)->default('pending')->index();
            $table->string('session_token', 64)->nullable()->unique();
            // What the scorer sees in the connected-devices list.
            $table->string('device_name')->nullable();
            $table->string('device_platform', 40)->nullable();
            $table->timestamp('connected_at')->nullable();
            // Drives the live/stale dot on the scoring screen; a device that stops
            // sending is shown as lost rather than silently kept "connected".
            $table->timestamp('last_seen_at')->nullable();
            // The scorer who opened the pairing. Only they may list or revoke.
            $table->unsignedBigInteger('created_by')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_devices');
    }
};
