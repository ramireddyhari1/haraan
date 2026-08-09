<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Player photo posts — the grid on a player's profile. Photo-only, one row per image,
 * owned by the player. Public: anyone who can see the profile can see the posts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Stored as a root-relative "/storage/posts/x.jpg", same shape as avatars.
            $table->string('image_path');
            $table->string('caption', 300)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_posts');
    }
};
