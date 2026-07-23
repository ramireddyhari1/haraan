<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — home-layout CMS. An ordered list of typed blocks that compose the
 * app's GameHub home. Admins reorder/show/hide/schedule sections from /control
 * with no release. Each block can be district-targeted and gated behind a
 * feature flag (see HomeBlock::isVisibleFor). Block "type" tells the app which
 * widget to render; "config" carries type-specific params (e.g. feed section
 * key, ad placement).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('home_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('type');                         // hero | ad_strip | feed_section | venues | leaderboard | actionboard | sports_chips ...
            $table->string('title')->nullable();            // optional section header shown in-app
            $table->json('config')->nullable();             // type-specific params
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('organization_ids')->nullable();   // null/empty = all districts
            $table->string('feature_flag_key')->nullable(); // gate this block behind a flag
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_blocks');
    }
};
