<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blocking and reporting — the two safety actions a player can take about another
 * player, and the moderation queue behind them.
 *
 * A block is deliberately ONE row in one direction (I blocked them), not a mutual
 * state: only the blocker can lift it. Every gate that consults it therefore has to
 * check BOTH directions — "am I blocked by them" matters as much as "did I block
 * them" — which is why `blocked_id` carries its own index.
 *
 * Reports are separate from blocks on purpose. Blocking is instant and private and
 * needs no review; reporting is a message to a human and carries a lifecycle. Merging
 * them would mean a block waits on moderation, which is exactly backwards for safety.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blocker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            // Blocking twice is a no-op, not a duplicate — a double-tap can't break it.
            $table->unique(['blocker_id', 'blocked_id']);
            // The reverse lookup every gate needs: "who has blocked this user".
            $table->index('blocked_id');
        });

        Schema::create('player_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reported_id')->constrained('users')->cascadeOnDelete();
            // A short machine key ('spam', 'harassment', …) — the human wording lives in
            // the app so it can be reworded without a migration.
            $table->string('reason', 40);
            $table->text('details')->nullable();
            // Lower-case throughout. Mixed-case status columns elsewhere in this schema
            // are why so many queries have to say lower(status).
            $table->string('status', 20)->default('open');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // The moderation queue's own ordering.
            $table->index(['status', 'created_at']);
            // "Everything ever reported about this player" — the only view that tells a
            // moderator whether one complaint is a pattern.
            $table->index('reported_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_reports');
        Schema::dropIfExists('player_blocks');
    }
};
