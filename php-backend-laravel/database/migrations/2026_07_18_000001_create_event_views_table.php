<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-view tracking for event detail opens — the foundation for real Views analytics
 * (unique / returning visitors, views by hour/day, traffic source, device, viewer location).
 * Previously only a single events.views counter existed, which can't answer any of those.
 *
 * One row per detail open. `visitor_key` = the signed-in user id when known, else a stable
 * hash of ip+user-agent, so unique/returning can be computed without storing raw IPs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('visitor_key', 64)->index();   // user:<id> or ip-ua hash
            $table->string('source', 32)->default('direct')->index(); // instagram/whatsapp/search/home/shared/app/web/direct
            $table->string('device', 16)->default('other');           // android/iphone/ipad/web/other
            $table->string('district')->nullable();
            $table->string('state')->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['event_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_views');
    }
};
