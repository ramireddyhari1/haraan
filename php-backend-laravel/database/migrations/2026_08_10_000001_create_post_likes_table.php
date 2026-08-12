<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Likes on player photo posts — the ❤ on the Instagram-style Home feed.
 * One row per (post, user); the unique index makes a like idempotent so a
 * double-tap on a slow connection can never double-count.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_likes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained('player_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['post_id', 'user_id']);
            $table->index(['post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_likes');
    }
};
