<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ActionBoard chat, tier 1: push, mute, photos and group management.
 *
 * - `conversation_participants.muted_until` — per person, per thread. A far-future date is
 *   "until I turn it back on"; null is not muted. One column instead of a flag + expiry
 *   pair, so a mute can never be "on" with an expiry already in the past.
 * - `direct_messages.image_path/width/height` — a photo message. The size is stored so the
 *   bubble can reserve the right shape before the image loads, instead of jumping.
 * - `direct_messages.kind` — null for anything a person wrote; 'system' for the
 *   "Ravi added Kiran" lines a group needs so nobody is added or removed silently.
 * - `conversations.avatar` — a group's own photo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_participants', function (Blueprint $table): void {
            if (! Schema::hasColumn('conversation_participants', 'muted_until')) {
                $table->timestamp('muted_until')->nullable();
            }
        });

        Schema::table('direct_messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('direct_messages', 'image_path')) {
                $table->string('image_path', 255)->nullable();
                $table->unsignedSmallInteger('image_width')->nullable();
                $table->unsignedSmallInteger('image_height')->nullable();
            }
            if (! Schema::hasColumn('direct_messages', 'kind')) {
                $table->string('kind', 16)->nullable();
            }
        });

        Schema::table('conversations', function (Blueprint $table): void {
            if (! Schema::hasColumn('conversations', 'avatar')) {
                $table->string('avatar', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn('avatar');
        });
        Schema::table('direct_messages', function (Blueprint $table): void {
            $table->dropColumn(['image_path', 'image_width', 'image_height', 'kind']);
        });
        Schema::table('conversation_participants', function (Blueprint $table): void {
            $table->dropColumn('muted_until');
        });
    }
};
