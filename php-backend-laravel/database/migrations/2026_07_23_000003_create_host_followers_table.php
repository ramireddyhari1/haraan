<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Host follows (Phase 2) — an attendee follows an organiser to hear about new
 * events. host_id/user_id are both user ids (the host is a partner User). Plus a
 * per-event stamp so followers are notified once, when it first goes published.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('host_followers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('host_id');   // the organiser (partner user)
            $table->unsignedBigInteger('user_id');   // the follower
            $table->timestamps();
            $table->unique(['host_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->timestamp('followers_notified_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('host_followers');
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('followers_notified_at');
        });
    }
};
