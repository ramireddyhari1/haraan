<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Live presence for match detail screens — who is watching a match RIGHT NOW.
 *
 * One row per (match, viewer), rewritten on every heartbeat rather than appended, so the
 * table stays the size of the current audience instead of growing with every poll. A viewer
 * counts as watching while their `last_seen_at` is inside the presence window
 * (MatchViewer::PRESENCE_WINDOW_SECONDS); leaving the screen needs no "goodbye" call —
 * the heartbeat simply stops and the row ages out of the window.
 *
 * `viewer_key` = 'u:<id>' for a signed-in viewer, else a hash of the app's install id, else
 * a hash of ip+user-agent. Never a raw IP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_viewers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('match_id')->constrained('live_matches')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('viewer_key', 64);
            $table->timestamp('last_seen_at')->nullable();

            // One row per viewer per match — the heartbeat upserts against this.
            $table->unique(['match_id', 'viewer_key']);
            // The count query: this match, seen within the window.
            $table->index(['match_id', 'last_seen_at']);
            // The sweep that drops long-dead rows.
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_viewers');
    }
};
