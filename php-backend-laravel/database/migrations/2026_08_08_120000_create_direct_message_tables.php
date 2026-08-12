<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Player-to-player direct messages.
 *
 * Deliberately NOT an extension of `support_threads`: that table models one user
 * talking to the support desk (a single `user_id` plus an `assigned_to` admin, with
 * `admin_unread_count`). There is no second participant in it, so it cannot represent
 * two players talking.
 *
 * Modelled as conversation + participants rather than a `user_a`/`user_b` pair, so
 * group threads (a squad chat) need no migration later — only a third row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->id();
            // Denormalised so the conversation LIST renders without touching the
            // messages table — the list is the hottest read in a messenger.
            $table->timestamp('last_message_at')->nullable();
            $table->string('last_message_preview', 200)->nullable();
            $table->foreignId('last_sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('last_message_at');
        });

        Schema::create('conversation_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Per-participant, so each side's badge is independent.
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            // One row per person per conversation — also the guard that stops a
            // double-tap creating two identical threads.
            $table->unique(['conversation_id', 'user_id']);
            $table->index(['user_id', 'conversation_id']);
        });

        Schema::create('direct_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            // The thread read: newest-first within one conversation.
            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direct_messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
    }
};
