<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Internal Admin Notes for user accounts.
 *
 * Provides a structured, auditable scratchpad for operators to leave notes on accounts:
 *  - Categorized (general, support, risk, financial, moderation)
 *  - Pinned support (floats to the top of User 360 profile)
 *  - Confidential support (restricted to Super-Admins and Operations)
 *  - Author tracking for accountability
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 30)->default('general');
            $table->string('title', 160)->nullable();
            $table->text('content');
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_confidential')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_pinned', 'created_at'], 'user_notes_lookup_idx');
            $table->index('author_id', 'user_notes_author_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notes');
    }
};
