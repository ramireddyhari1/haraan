<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where each customer is in the WhatsApp booking bot's conversation: which step, and
 * what they've picked so far. One row per phone number. New table only.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('whatsapp_bot_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('phone', 32)->unique(); // E.164
            $table->string('state', 32)->default('start');
            $table->json('data')->nullable();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->timestamp('last_activity_at')->nullable();
            // Venue staff replied in the desk: the bot stays quiet until then.
            $table->timestamp('paused_until')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_bot_sessions');
    }
};
