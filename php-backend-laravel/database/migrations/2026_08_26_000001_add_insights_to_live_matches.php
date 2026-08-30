<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a written match analysis lives.
 *
 * The FIGURES behind the Insights tab are derived on every request by replaying the ball
 * log — they are cheap, deterministic, and must never drift from the scorecard. The PROSE
 * is not: it costs a model call, and a match analysed twice would come back worded
 * differently each time, so a reader refreshing the tab would watch the verdict on their
 * own match quietly rewrite itself.
 *
 * So the written half is stored once, on the match it describes. Null is not an error: it
 * means this match has no written analysis yet (no API key, the call failed, or nobody has
 * opened the tab), and the tab shows its computed figures alone — which is most of the
 * value and all of the truth.
 *
 * `insights_at` records WHEN it was written, so an analysis produced at 4 overs can be
 * recognised as stale once the innings has moved on and rewritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_matches', function (Blueprint $table): void {
            $table->text('insights')->nullable()->after('timeline');
            $table->timestamp('insights_at')->nullable()->after('insights');
            // Balls bowled at the moment of writing. The cheapest possible staleness check:
            // if the match has moved on, the analysis is out of date.
            $table->unsignedInteger('insights_balls')->nullable()->after('insights_at');
        });
    }

    public function down(): void
    {
        Schema::table('live_matches', function (Blueprint $table): void {
            $table->dropColumn(['insights', 'insights_at', 'insights_balls']);
        });
    }
};
