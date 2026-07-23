<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshotted recipients for activity-based notification segments (e.g. "active this
 * week", "inactive 14+ days"). Static segments (all/district/state/sport/user) still
 * resolve dynamically at read time in Notification::scopeForUser — but an activity
 * segment must be frozen at send time, otherwise a targeted user would stop matching
 * the moment they open the app to read it, and the message would vanish. Populated by
 * Notification when it flips to "sent".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_id')->constrained('notifications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->unique(['notification_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_recipients');
    }
};
