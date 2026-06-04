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
        Schema::create('live_matches', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('home');
            $table->string('away');
            $table->string('home_full')->nullable();
            $table->string('away_full')->nullable();
            $table->integer('home_score')->default(0);
            $table->integer('away_score')->default(0);
            $table->string('score_text')->nullable();
            $table->string('overs')->default('0.0');
            $table->string('status')->default('Scheduled'); // Scheduled, Live, Completed
            $table->string('time')->nullable();
            $table->string('venue')->nullable();
            $table->string('competition')->nullable();
            $table->string('referee')->nullable();
            $table->string('crr')->default('0.00');
            $table->string('decision')->nullable();
            $table->string('run_rate')->default('0.00');
            
            // JSON Columns for complex data
            $table->json('probability')->nullable();
            $table->json('projected_score')->nullable();
            $table->json('batters')->nullable();
            $table->json('bowler')->nullable();
            
            $table->string('partnership')->nullable();
            $table->string('last_wicket')->nullable();
            
            $table->json('over_summary')->nullable();
            $table->json('timeline')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_matches');
    }
};
