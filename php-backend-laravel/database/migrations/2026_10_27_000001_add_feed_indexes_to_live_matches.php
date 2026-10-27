<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the two match lists every viewer loads.
 *
 *  - (is_private, updated_at): the app + web ActionBoard feed is "public matches, freshest
 *    first, top 200". The existing scope index leads with `visibility`, so that query
 *    scanned and sorted the whole table on every 20s poll.
 *  - (is_private, created_at): the web ActionBoard sidebar ("public matches, newest first").
 *
 * Index-only — no column changes, so SQLite never rebuilds the table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_matches', function (Blueprint $table): void {
            $table->index(['is_private', 'updated_at'], 'live_matches_feed_idx');
            $table->index(['is_private', 'created_at'], 'live_matches_recent_idx');
        });
    }

    public function down(): void
    {
        Schema::table('live_matches', function (Blueprint $table): void {
            $table->dropIndex('live_matches_feed_idx');
            $table->dropIndex('live_matches_recent_idx');
        });
    }
};
