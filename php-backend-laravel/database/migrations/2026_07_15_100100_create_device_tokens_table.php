<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FCM/push registration tokens per device. Populated now (Phase 1) via
 * /api/devices/register so that Phase 2 (real background push) can fan a
 * notification out to a segment's devices without another migration or deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token')->unique();
            $table->string('platform')->default('android'); // android | ios | web
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
