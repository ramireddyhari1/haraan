<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Real career-batting totals, aggregated by replaying the ball-by-ball `match_actions`
 * log across every completed match (the same source the scorecard trusts). Kept separate
 * from the existing (partly-synthetic) User.career_* columns so this stays honest and the
 * legacy leaderboard pipeline is untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_career_batting', function (Blueprint $table) {
            $table->id();
            $table->string('player_id')->unique();   // == User.player_id / squad member id
            $table->string('player_name')->default('');
            $table->unsignedInteger('innings')->default(0);      // times they actually batted
            $table->unsignedInteger('runs')->default(0);
            $table->unsignedInteger('balls')->default(0);
            $table->unsignedInteger('fours')->default(0);
            $table->unsignedInteger('sixes')->default(0);
            $table->unsignedInteger('outs')->default(0);         // dismissals — the divisor for AVG
            $table->unsignedInteger('high_score')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_career_batting');
    }
};
