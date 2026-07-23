<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The ad / promo banner layout in the home feed (e.g. the sponsored card).
        Schema::create('ads', function (Blueprint $table): void {
            $table->id();
            $table->string('sponsor')->nullable();          // "ChatGPT", brand label
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('image')->nullable();            // background / banner image
            $table->string('logo')->nullable();             // sponsor logo
            $table->string('cta_text')->default('Try Now');
            $table->string('cta_url')->nullable();
            $table->string('placement')->default('events'); // events / gamehub feed
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        // Curated cards for the "For You" and "Trending" home sections.
        Schema::create('feed_items', function (Blueprint $table): void {
            $table->id();
            $table->string('section');                       // for_you / trending
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('image')->nullable();
            $table->string('badge')->nullable();            // "LIVE", "Comedy", etc.
            $table->string('rating')->nullable();
            $table->string('link_type')->nullable();        // event / venue / match / url
            $table->string('link_id')->nullable();          // target id or url
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_items');
        Schema::dropIfExists('ads');
    }
};
