<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-staff scoping (Phase 3): a desk person can be limited to specific venues
 * or events instead of every one their owner has. Two thin pivots keep the
 * whereIn scoping cheap; no FK constraints (SQLite can't ALTER them in and the
 * rows are always scoped in code). A staff member with no rows here still sees
 * all of their owner's records — assignment is an opt-in restriction.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('staff_venues', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('venue_id');
            $table->timestamps();
            $table->unique(['user_id', 'venue_id']);
            $table->index('venue_id');
        });

        Schema::create('staff_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('event_id');
            $table->timestamps();
            $table->unique(['user_id', 'event_id']);
            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_venues');
        Schema::dropIfExists('staff_events');
    }
};
