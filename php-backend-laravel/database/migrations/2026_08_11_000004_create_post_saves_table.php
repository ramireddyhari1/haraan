<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved (bookmarked) posts — the bookmark icon on a post. One row per (post, user); the
 * unique index keeps a save idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_saves', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained('player_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['post_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_saves');
    }
};
