<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('player_match_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('live_matches')->onDelete('cascade');
            $table->string('player_id')->index(); // References users.player_id
            $table->string('player_name');
            $table->integer('runs')->default(0);
            $table->integer('balls')->default(0);
            $table->integer('wickets')->default(0);
            $table->string('overs_bowled')->default('0.0');
            $table->integer('runs_conceded')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_match_stats');
    }
};
