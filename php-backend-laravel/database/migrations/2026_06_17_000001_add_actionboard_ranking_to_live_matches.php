<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Haraan ActionBoard — ranking & verification fields.
 *
 * Match type sets the XP *ceiling*; trust level (decided AFTER the match via
 * the verification state machine) sets the *multiplier* that unlocks real XP.
 * Lifecycle:  Scheduled → Live → Completed → [verification_status] → settled/expired
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_matches', function (Blueprint $table) {
            // Match type → XP ceiling (casual=25, league=60, tournament=100)
            $table->string('match_type')->default('casual')->after('competition');
            $table->unsignedInteger('base_xp')->default(25)->after('match_type');

            // Trust → XP multiplier (low .25, medium .75, high 1.0, verified 1.25)
            $table->string('trust_level')->default('low')->after('base_xp');

            // Verification sub-state machine (separate from play `status`)
            // none → pending → settled | expired
            $table->string('verification_status')->default('none')->after('trust_level');
            $table->timestamp('verification_deadline')->nullable()->after('verification_status');

            // Result + Man of the Match (bonuses only count at trust >= medium)
            $table->string('result')->nullable()->after('verification_deadline'); // home|away|tie|no_result
            $table->string('mom_player_id')->nullable()->after('result');

            // Captain confirmations (both → medium trust)
            $table->boolean('home_captain_confirmed')->default(false)->after('mom_player_id');
            $table->boolean('away_captain_confirmed')->default(false)->after('home_captain_confirmed');

            // Higher-trust sources
            $table->unsignedBigInteger('verified_by')->nullable()->after('away_captain_confirmed'); // organizer user id
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->unsignedBigInteger('venue_booking_id')->nullable()->after('verified_at'); // Haraan turf → verified trust

            // Whether this match counts toward Ranked (district/state/india) leaderboards
            $table->boolean('is_ranked')->default(false)->after('venue_booking_id');
        });
    }

    public function down(): void
    {
        Schema::table('live_matches', function (Blueprint $table) {
            $table->dropColumn([
                'match_type', 'base_xp', 'trust_level',
                'verification_status', 'verification_deadline',
                'result', 'mom_player_id',
                'home_captain_confirmed', 'away_captain_confirmed',
                'verified_by', 'verified_at', 'venue_booking_id',
                'is_ranked',
            ]);
        });
    }
};
