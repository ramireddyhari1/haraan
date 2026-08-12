<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Player-to-player follows — the social spine of ActionBoard.
 *
 * Deliberately one-directional and unconfirmed, Twitter-style rather than
 * Facebook-style: following someone needs no approval, so discovering a player by
 * handle and keeping an eye on their matches is a single tap. A mutual pair is
 * simply two rows, which `mutuals()` reads back cheaply.
 *
 * Separate from `host_followers` (fans following an event organiser) on purpose —
 * that is a person→brand relationship with different privacy and notification
 * rules. Merging them would mean every query needs a discriminator forever.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_follows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('followee_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            // One row per direction — following twice is a no-op, not a duplicate.
            $table->unique(['follower_id', 'followee_id']);
            // "Who follows this player" — the follower-count and profile queries.
            $table->index(['followee_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_follows');
    }
};
