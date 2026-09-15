<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ad impressions and clicks.
 *
 * Until now a sponsor could be sold a slot but never told what it did: nothing recorded a
 * view or a tap. `ad_events` is the raw, de-duplicated log (one impression per viewer per
 * placement per 30 minutes; a double-tapped click counts once), and the two counters on
 * `ads` are the running totals the console lists without scanning the log.
 *
 * Viewers are identified only by a salted hash of the app's install id (or the web session),
 * never the raw value, and the signed-in user id when there is one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ad_id')->constrained('ads')->cascadeOnDelete();
            $table->string('kind', 12);          // impression | click
            $table->string('placement', 40);
            $table->string('surface', 10);       // app | web
            $table->string('viewer_hash', 64)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('match_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['ad_id', 'kind', 'created_at']);
            $table->index(['ad_id', 'viewer_hash', 'kind', 'created_at']);
        });

        Schema::table('ads', function (Blueprint $table): void {
            $table->unsignedBigInteger('impressions_count')->default(0);
            $table->unsignedBigInteger('clicks_count')->default(0);
            $table->index(['placement', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table): void {
            $table->dropIndex(['placement', 'is_active']);
            $table->dropColumn(['impressions_count', 'clicks_count']);
        });
        Schema::dropIfExists('ad_events');
    }
};
