<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campaign themes for the app's two home lanes (Events, Pulse). While a row is live, the
 * lane's header takes the campaign colours and an optional decoration (image or Lottie),
 * then returns to the normal palette when the window closes. Served by GET /api/section-themes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('section_themes', function (Blueprint $table): void {
            $table->id();
            // 'events' | 'pulse'. Pulse is GameHub's UI name; the key is the lane, not the label.
            $table->string('section', 20)->index();
            // Admin label (and the accessibility description in the app). Not drawn on screen.
            $table->string('campaign_name', 80);
            // #RRGGBB. Only primary is required; the app derives the rest when blank.
            $table->string('accent_primary', 9);
            $table->string('accent_deep', 9)->nullable();
            $table->string('accent_tint', 9)->nullable();
            $table->string('on_primary', 9)->nullable();
            // Public-disk path or absolute URL: PNG/WebP image or a Lottie .json animation.
            $table->string('decoration')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            // Two overlapping campaigns on one lane: the higher priority wins.
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['section', 'is_active', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_themes');
    }
};
