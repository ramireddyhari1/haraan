<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sport-neutral match events, and the per-sport score shape.
 *
 * `live_matches` is a cricket table wearing a `sport` column: overs, crr, batters,
 * bowler, partnership, last_wicket, over_summary, projected_score. Football and
 * badminton cannot be expressed in any of it, and bolting them on would either
 * corrupt cricket's meaning or add a dozen mostly-null columns.
 *
 * So: one events table both new sports read, and one JSON blob for the score shape
 * that is genuinely per-sport. **Cricket's columns are untouched** — the one sport
 * that currently works takes no migration risk.
 *
 *   football   kind = goal|own_goal|assist|yellow|red|sub_in|sub_out|period
 *   badminton  kind = point   detail = winner|error   (score captured per row)
 *
 * `sport_state` carries what a scoreboard needs but a timeline can't express:
 *   badminton { games:[[21,18],[19,21]], serving:"home", target:21, cap:30 }
 *   football  { half:2, clock_min:67, added:3 }
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('live_match_id')->constrained()->cascadeOnDelete();

            // Denormalised so a feed can filter without joining the match.
            $table->string('sport', 20)->default('football');

            // Ordering is by `sequence`, never by minute: two goals in the 67th
            // must keep the order they were scored in, and badminton has no clock.
            $table->unsignedInteger('sequence');
            $table->unsignedSmallInteger('minute')->nullable();

            // 'home' | 'away'. Nullable for neutral events (period start/end).
            $table->string('side', 5)->nullable();

            $table->string('kind', 20);

            // Who did it. Free-text name so a gully match can record a player who
            // has no Haraan account; player_id links them when they do.
            $table->string('player_name')->nullable();
            $table->foreignId('player_id')->nullable()->constrained('users')->nullOnDelete();

            // Secondary actor: the assister on a goal, the player coming off on a sub.
            $table->string('related_name')->nullable();

            // Running score AFTER this event, so a timeline row renders without
            // replaying every prior event.
            $table->unsignedSmallInteger('home_score')->nullable();
            $table->unsignedSmallInteger('away_score')->nullable();

            // winner|error for badminton; anything else a sport wants to qualify.
            $table->string('detail', 30)->nullable();
            $table->string('note')->nullable();

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['live_match_id', 'sequence']);
            $table->index(['live_match_id', 'kind']);
        });

        Schema::table('live_matches', function (Blueprint $table): void {
            $table->json('sport_state')->nullable()->after('sport');
        });
    }

    public function down(): void
    {
        Schema::table('live_matches', function (Blueprint $table): void {
            $table->dropColumn('sport_state');
        });

        Schema::dropIfExists('match_events');
    }
};
