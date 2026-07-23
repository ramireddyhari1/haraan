<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Haraan ActionBoard reputation ledger — one row per penalty against a player.
 *
 * Trust score = START + (ranked matches × recovery) − Σ(penalty amounts),
 * clamped. Serial abusers (disputes, fake tournaments, rejected verifications)
 * fall below the action thresholds and lose organize/verify privileges.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reputation_events', function (Blueprint $table) {
            $table->id();
            $table->string('player_id')->index();           // users.player_id (penalized)
            $table->string('type');                          // match_dispute|verification_rejection|fake_tournament|repeated_abuse
            $table->unsignedInteger('amount');               // positive magnitude subtracted from trust
            $table->unsignedBigInteger('match_id')->nullable();
            $table->unsignedBigInteger('reported_by')->nullable(); // user id who filed it
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_organizer')->default(false)->after('trust_score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reputation_events');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_organizer');
        });
    }
};
