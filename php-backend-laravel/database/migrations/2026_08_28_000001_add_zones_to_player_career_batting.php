<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a player's runs actually go.
 *
 * The scorer has been recording a zone on every boundary since the wagon-wheel picker
 * shipped, but nothing ever read those zones outside the match they were scored in. A
 * career wagon wheel is the one chart in cricket that says something about a batter no
 * table can — that they score square, or only straight, or never behind the wicket —
 * and it was already sitting in the ball log.
 *
 * Stored as JSON on the career row rather than as its own table: it is exactly eight
 * numbers, always read together, always written by the same replay.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_career_batting', function (Blueprint $table): void {
            // [{zone,shots,fours,sixes,runs}, …] — only zones the player has actually
            // hit to. Null for a career replayed before this existed.
            $table->text('zones')->nullable()->after('hundreds');
        });
    }

    public function down(): void
    {
        Schema::table('player_career_batting', function (Blueprint $table): void {
            $table->dropColumn('zones');
        });
    }
};
