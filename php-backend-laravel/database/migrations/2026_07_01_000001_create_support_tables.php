<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-app support chat. One thread per user (reopened if closed); messages are
 * exchanged between the app user and Haraan admins/assigned workers who reply
 * from the Filament control panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_threads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject')->nullable();
            // open → user is waiting, pending → admin replied, closed → resolved.
            $table->string('status')->default('open');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            // Unread counters, one per side, so both the app badge and the admin
            // list can show "needs attention" without scanning messages.
            $table->unsignedInteger('user_unread_count')->default(0);
            $table->unsignedInteger('admin_unread_count')->default(0);
            $table->timestamps();

            $table->index(['status', 'last_message_at']);
        });

        Schema::create('support_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('thread_id')->constrained('support_threads')->cascadeOnDelete();
            // 'user' (from the app) or 'admin' (from the control panel).
            $table->string('sender_type');
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['thread_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_threads');
    }
};
