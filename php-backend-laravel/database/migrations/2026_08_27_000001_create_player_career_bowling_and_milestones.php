<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The rest of a real cricket career.
 *
 * Batting detail already lands in `player_career_batting`, but the milestones a player
 * actually remembers — fifties, hundreds — were nowhere, and bowling survived only as
 * three aggregate columns on `users` (wickets, runs conceded, overs). A profile built on
 * that can show an economy rate and nothing else: no best figures, no hauls, no count of
 * how many times they actually bowled.
 *
 * Both are filled by CareerBattingService::rebuildAll(), which already replays every
 * completed match ball by ball — these are counters on a pass it was making anyway.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_career_batting', function (Blueprint $table): void {
            // Innings crossing 30 / 50 / 100. Counted per innings during the replay, so
            // they cannot be derived from the totals after the fact.
            $table->unsignedInteger('thirties')->default(0)->after('high_score');
            $table->unsignedInteger('fifties')->default(0)->after('thirties');
            $table->unsignedInteger('hundreds')->default(0)->after('fifties');
        });

        Schema::create('player_career_bowling', function (Blueprint $table): void {
            $table->id();
            // Squad player id (== users.player_id). Guests carry no career line.
            $table->string('player_id')->unique();
            $table->string('player_name')->nullable();
            // Innings actually bowled in — not matches played, which is what an economy
            // rate divided by "matches" would silently assume.
            $table->unsignedInteger('innings')->default(0);
            $table->unsignedInteger('balls')->default(0);
            $table->unsignedInteger('runs')->default(0);
            $table->unsignedInteger('wickets')->default(0);
            // Best bowling in an innings, as the pair that reads "4/23". Runs are the
            // tiebreak, so 4/23 beats 4/31 the way the scorebook says it does.
            $table->unsignedInteger('best_wickets')->default(0);
            $table->unsignedInteger('best_runs')->default(0);
            $table->unsignedInteger('three_fers')->default(0);
            $table->unsignedInteger('five_fers')->default(0);
            $table->unsignedInteger('maidens')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_career_bowling');
        Schema::table('player_career_batting', function (Blueprint $table): void {
            $table->dropColumn(['thirties', 'fifties', 'hundreds']);
        });
    }
};
