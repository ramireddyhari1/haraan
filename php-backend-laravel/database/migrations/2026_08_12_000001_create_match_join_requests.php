<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Join a match near me": an owner can open a scheduled match for players to join
 * (with a number of slots), nearby players send a request, and the owner accepts or
 * declines. `open_to_join` + `slots_needed` mark a match as looking for players;
 * `match_join_requests` is the request ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_matches', function (Blueprint $table): void {
            // The owner is inviting players to fill out the match.
            $table->boolean('open_to_join')->default(false)->after('is_private');
            // How many more players the match is looking for (0 = not looking / full).
            $table->unsignedTinyInteger('slots_needed')->default(0)->after('open_to_join');
        });

        Schema::create('match_join_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('match_id')->index();
            $table->unsignedBigInteger('requester_id')->index();
            $table->string('message', 200)->nullable();
            // pending | accepted | declined | cancelled
            $table->string('status', 12)->default('pending')->index();
            // Which side the owner slotted the player into on accept (home/away), if any.
            $table->string('side', 8)->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            // A player has at most one live (pending) request per match — enforced in
            // code (a declined/cancelled one may be re-sent), so no unique constraint.
            $table->foreign('match_id')->references('id')->on('live_matches')->cascadeOnDelete();
            $table->foreign('requester_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_join_requests');
        Schema::table('live_matches', function (Blueprint $table): void {
            $table->dropColumn(['open_to_join', 'slots_needed']);
        });
    }
};
