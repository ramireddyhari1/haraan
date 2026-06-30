<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Haraan ActionBoard XP ledger — one row per player per settled match.
 *
 * The ledger is the source of truth for both All-Time and (via season_month)
 * the monthly District/State/India leaderboards in Sprint 4. User totals
 * (ranked_xp / casual_xp) are re-aggregated from these rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_xp_ledger', function (Blueprint $table) {
            $table->id();
            $table->string('player_id')->index();          // users.player_id
            $table->unsignedBigInteger('match_id')->index();
            $table->unsignedInteger('xp')->default(0);      // final awarded XP

            // Breakdown (for transparency / debugging the economy)
            $table->unsignedInteger('base_xp')->default(0);
            $table->string('trust_level')->default('low');
            $table->float('trust_multiplier')->default(0.25);
            $table->float('diversity_multiplier')->default(1.0);
            $table->boolean('is_ranked')->default(false);
            $table->boolean('won')->default(false);
            $table->boolean('mom')->default(false);

            // Same-opponent decay key + monthly bucketing
            $table->string('opponent_key')->nullable()->index();
            $table->string('season_month', 7)->index();     // 'YYYY-MM'

            $table->timestamp('awarded_at')->nullable();
            $table->timestamps();

            $table->unique(['player_id', 'match_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('ranked_xp')->default(0)->after('rank_country');
            $table->unsignedBigInteger('casual_xp')->default(0)->after('ranked_xp');
            $table->unsignedTinyInteger('trust_score')->default(100)->after('casual_xp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_xp_ledger');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ranked_xp', 'casual_xp', 'trust_score']);
        });
    }
};
