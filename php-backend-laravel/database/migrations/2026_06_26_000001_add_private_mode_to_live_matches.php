<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Private match mode for ActionBoard.
 *
 * A private match is a pure scoreboard for a closed group: it never enters the
 * verification/XP pipeline (zero XP, never ranked), is hidden from every public
 * feed and leaderboard, and is reachable only by its creator, its squad, or
 * anyone holding its short `join_code`. The flag is immutable once created.
 *
 * This is orthogonal to `match_type` (XP ceiling) and `visibility`
 * (LOCAL/FEATURED reach, admin-granted).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_matches', function (Blueprint $table) {
            if (!Schema::hasColumn('live_matches', 'is_private')) {
                $table->boolean('is_private')->default(false)->after('is_ranked');
            }
            // Short shareable code (e.g. HRN-7K2Q). Only set for private matches.
            if (!Schema::hasColumn('live_matches', 'join_code')) {
                $table->string('join_code', 16)->nullable()->unique()->after('is_private');
            }
        });
    }

    public function down(): void
    {
        Schema::table('live_matches', function (Blueprint $table) {
            if (Schema::hasColumn('live_matches', 'join_code')) {
                $table->dropColumn('join_code');
            }
        });
    }
};
