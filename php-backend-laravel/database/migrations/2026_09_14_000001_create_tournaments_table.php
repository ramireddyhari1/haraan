<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Player-hosted tournaments, every sport the app can score. A tournament is its own record —
 * identity (banner, logo, name, category), who can play, where and when, the playing format
 * every fixture follows, and who to call — rather than a single match with
 * `match_type = tournament`, which had nowhere to keep any of that.
 *
 * Cricket's format has columns of its own (overs, balls, innings, days, ball). Every other
 * sport's numbers live in `format_rules`, shaped by App\Models\Tournament::SPORTS, which is
 * also what the API validates them against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // cricket | football | badminton | volleyball | basketball | kabaddi | tennis | table_tennis
            $table->string('sport', 20)->default('cricket');

            // Identity
            $table->string('name', 120);
            // open | corporate | community | school | college | university | series | other
            $table->string('category', 20);
            // The organiser's own word when category = other ("Police Cup", "Temple festival").
            $table->string('category_other', 60)->nullable();
            $table->text('description')->nullable();
            // Public-disk paths ("/storage/tournaments/…"), null until uploaded.
            $table->string('banner_path')->nullable();
            $table->string('logo_path')->nullable();

            // Who can play. Racquet sports say it through `events` instead of `gender`.
            $table->string('gender', 10)->nullable();      // men | women | mixed
            $table->string('age_group', 12)->default('open');

            // Where and when
            $table->string('city', 100);
            $table->string('venue', 150)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->date('start_date');
            $table->date('end_date');

            // Playing format — the rules every fixture in the tournament follows.
            // A preset key from Tournament::SPORTS[sport]['formats'], or 'custom'.
            $table->string('match_format', 20);
            // The organiser's name for a custom format ("Super 8", "Monsoon 6s").
            $table->string('format_name', 60)->nullable();
            // Non-cricket numbers: halves, half_minutes, best_of, points_to, periods…
            $table->json('format_rules')->nullable();
            // Racquet sports: which draws run (mens_singles, mixed_doubles…).
            $table->json('events')->nullable();
            // Sport-specific extras: knockout tiebreak, shuttle type, weight limit.
            $table->json('options')->nullable();
            // Cricket only.
            $table->unsignedSmallInteger('overs_per_innings')->nullable();
            $table->unsignedSmallInteger('balls_per_innings')->nullable();
            $table->unsignedTinyInteger('innings_per_side')->nullable();
            $table->unsignedTinyInteger('match_days')->nullable();
            $table->string('ball_type', 20)->nullable();
            // Null for racquet sports played one-a-side or in pairs.
            $table->unsignedTinyInteger('players_per_side')->nullable();
            // Pitch, pitch-surface or court. Null where the sport has no meaningful choice.
            $table->string('surface', 20)->nullable();

            // Draw and entries
            // knockout | league | league_knockout | groups_knockout
            $table->string('structure', 30);
            // Teams for team sports, entries per event for racquet sports.
            $table->unsignedSmallInteger('teams_count')->nullable();
            $table->unsignedInteger('entry_fee')->nullable();
            $table->string('prize_pool', 120)->nullable();

            // Organiser contact — who a team calls to enter.
            $table->string('organizer_name', 100);
            $table->string('organizer_phone', 20);

            $table->timestamps();

            $table->index(['user_id', 'start_date']);
            $table->index(['sport', 'city', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
