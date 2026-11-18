<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ActionBoard chat, tier 3: polls, voice notes, message requests, team groups.
 *
 * - Polls hang off a message (one poll per message) with their options and one vote row per
 *   person per option — counts are computed, never stored, so they can't drift.
 * - Voice notes are a stored audio file plus its length, so the bubble can show "0:12"
 *   before anything downloads.
 * - `conversation_participants.request_status`: 'pending' on the RECIPIENT's row while a
 *   message request waits, 'accepted' once they say yes; null for an ordinary chat.
 * - `chat_request_declines` remembers a "no" so the same person can't ask again.
 * - `conversations.match_id` ties a team group to the match it was made for, so there is
 *   only ever one per match.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_polls')) {
            Schema::create('chat_polls', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('direct_message_id')->unique()->constrained('direct_messages')->cascadeOnDelete();
                $table->string('question', 200);
                $table->boolean('allow_multiple')->default(false);
                $table->timestamps();
            });
            Schema::create('chat_poll_options', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('chat_poll_id')->constrained('chat_polls')->cascadeOnDelete();
                $table->string('label', 80);
                $table->unsignedTinyInteger('position')->default(0);
            });
            Schema::create('chat_poll_votes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('chat_poll_id')->constrained('chat_polls')->cascadeOnDelete();
                $table->foreignId('chat_poll_option_id')->constrained('chat_poll_options')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['chat_poll_option_id', 'user_id']);
                $table->index(['chat_poll_id', 'user_id']);
            });
        }

        Schema::table('direct_messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('direct_messages', 'audio_path')) {
                $table->string('audio_path', 255)->nullable();
                $table->unsignedInteger('audio_duration_ms')->nullable();
            }
        });

        Schema::table('conversation_participants', function (Blueprint $table): void {
            if (! Schema::hasColumn('conversation_participants', 'request_status')) {
                $table->string('request_status', 12)->nullable();
            }
        });

        Schema::table('conversations', function (Blueprint $table): void {
            if (! Schema::hasColumn('conversations', 'match_id')) {
                $table->unsignedBigInteger('match_id')->nullable()->index();
            }
        });

        if (! Schema::hasTable('chat_request_declines')) {
            Schema::create('chat_request_declines', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['requester_id', 'recipient_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_request_declines');
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn('match_id');
        });
        Schema::table('conversation_participants', function (Blueprint $table): void {
            $table->dropColumn('request_status');
        });
        Schema::table('direct_messages', function (Blueprint $table): void {
            $table->dropColumn(['audio_path', 'audio_duration_ms']);
        });
        Schema::dropIfExists('chat_poll_votes');
        Schema::dropIfExists('chat_poll_options');
        Schema::dropIfExists('chat_polls');
    }
};
