<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ActionBoard chat, tier 2: per-person list housekeeping and message edits.
 *
 * - `pinned_at` / `archived_at` — this person's list only; nobody else is told.
 * - `cleared_upto_id` — "Delete chat": messages up to this id are gone from THIS person's
 *   view (the other side keeps theirs). An id, not a time, so two messages in the same
 *   second can't land on the wrong side of the line.
 * - `direct_messages.edited_at` — set when the sender corrects a message; the bubble says
 *   "edited" so a changed line is never passed off as the original.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_participants', function (Blueprint $table): void {
            if (! Schema::hasColumn('conversation_participants', 'pinned_at')) {
                $table->timestamp('pinned_at')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->unsignedBigInteger('cleared_upto_id')->nullable();
            }
        });

        Schema::table('direct_messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('direct_messages', 'edited_at')) {
                $table->timestamp('edited_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('direct_messages', function (Blueprint $table): void {
            $table->dropColumn('edited_at');
        });
        Schema::table('conversation_participants', function (Blueprint $table): void {
            $table->dropColumn(['pinned_at', 'archived_at', 'cleared_upto_id']);
        });
    }
};
