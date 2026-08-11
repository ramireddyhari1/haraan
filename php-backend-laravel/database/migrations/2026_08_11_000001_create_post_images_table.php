<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extra images for a photo post — carousel support. One row per image beyond (and including)
 * the cover. `player_posts.image_path` stays the cover for older clients; posts created before
 * this table simply have no rows and read as single-image (the feed falls back to the cover).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained('player_posts')->cascadeOnDelete();
            // Root-relative "/storage/posts/x.jpg", same shape as player_posts.image_path.
            $table->string('image_path');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['post_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_images');
    }
};
