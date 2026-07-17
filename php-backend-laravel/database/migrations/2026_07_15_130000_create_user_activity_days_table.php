<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only "user was active on this day" log — one row per user per calendar day.
 * A single last_seen_at column only holds the latest hit, so it can't draw a DAU line;
 * this table can. Written (idempotently) by User::touchLastSeen() from the same JWT
 * heartbeat, so /control can chart DAU / new-vs-returning / stickiness over time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_activity_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('activity_date');
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'activity_date']);
            $table->index('activity_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activity_days');
    }
};
