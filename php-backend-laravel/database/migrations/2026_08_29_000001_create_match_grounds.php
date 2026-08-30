<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grounds, as things rather than as strings.
 *
 * `live_matches.venue` is free text typed by whoever created the match, and it has
 * already forked in the wild — "Saipeta Ground, Kadapa" and "Saipeta Ground" are one
 * ground stored twice. Nothing per-ground can be computed on top of that: two matches
 * at the same maidan look like two different venues, so a ground with five games shows
 * as five grounds with one.
 *
 * This is the canonical row those strings resolve TO. `place_id` is the identity when
 * Google can name the place; `name_key` is a normalised fallback so the whole thing
 * still works with no Maps key and for grounds Google has never heard of — which, for
 * gully cricket, is most of them.
 *
 * The stat columns are a CACHE of a replay over every completed match here, refreshed
 * when a match at this ground completes. Recomputing them per request would mean
 * replaying an entire season's ball log to draw one card.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_grounds', function (Blueprint $table): void {
            $table->id();
            // Google's identity for the place. Null for grounds Places cannot resolve,
            // which is normal and not an error.
            $table->string('place_id')->nullable()->unique();
            // The fallback identity: lower-cased, punctuation-stripped, district-suffix
            // removed. This is what merges "Saipeta Ground, Kadapa" with "Saipeta Ground".
            $table->string('name_key')->index();
            $table->string('name');
            $table->string('formatted_address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('locality')->nullable();
            $table->string('district')->nullable();

            // ── The cached read on this ground ──
            $table->unsignedInteger('matches_played')->default(0);
            $table->unsignedInteger('first_innings_avg')->default(0);
            $table->unsignedInteger('highest_total')->default(0);
            $table->unsignedInteger('best_individual')->default(0);
            $table->string('best_individual_by')->nullable();
            $table->unsignedInteger('boundary_percent')->default(0);
            $table->string('run_rate', 8)->nullable();
            // Numerator and denominator both stored: "4 wins" is a claim, "4 of 7" is a
            // fact, and the card is required to show the sample it is speaking from.
            $table->unsignedInteger('batting_first_wins')->default(0);
            $table->unsignedInteger('decided_matches')->default(0);
            $table->timestamp('stats_at')->nullable();

            $table->timestamps();
        });

        Schema::table('live_matches', function (Blueprint $table): void {
            $table->unsignedBigInteger('ground_id')->nullable()->index()->after('venue');
        });
    }

    public function down(): void
    {
        Schema::table('live_matches', function (Blueprint $table): void {
            $table->dropColumn('ground_id');
        });
        Schema::dropIfExists('match_grounds');
    }
};
