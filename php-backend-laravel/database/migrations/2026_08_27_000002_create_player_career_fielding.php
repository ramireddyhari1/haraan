<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The third discipline.
 *
 * Batting and bowling were both recoverable from the ball log because every delivery
 * names a striker and a bowler. A catch names nobody: the scorer asked HOW the batter
 * was out and never who did it, so a career could show four wickets and not one of the
 * catches that took them. The scorer now records the fielder on a catch, run-out or
 * stumping, and this is where the replay adds them up.
 *
 * Rows only ever appear for dismissals scored AFTER the fielder question existed —
 * older matches have no fielder in their payload, and inventing one is not an option.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_career_fielding', function (Blueprint $table): void {
            $table->id();
            // Squad player id (== users.player_id). Guests carry no career line.
            $table->string('player_id')->unique();
            $table->string('player_name')->nullable();
            $table->unsignedInteger('catches')->default(0);
            $table->unsignedInteger('run_outs')->default(0);
            $table->unsignedInteger('stumpings')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_career_fielding');
    }
};
