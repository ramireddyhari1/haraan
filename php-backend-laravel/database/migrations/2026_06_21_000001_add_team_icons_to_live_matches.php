<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Team icons chosen at create time. Each side may have either an uploaded custom
 * logo (stored on the public disk, served from /storage/...) or a default emblem
 * (an emoji token). A logo, when present, takes precedence over the emblem.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_matches', function (Blueprint $table) {
            $table->string('home_logo')->nullable()->after('away_full');
            $table->string('away_logo')->nullable()->after('home_logo');
            $table->string('home_emblem')->nullable()->after('away_logo');
            $table->string('away_emblem')->nullable()->after('home_emblem');
        });
    }

    public function down(): void
    {
        Schema::table('live_matches', function (Blueprint $table) {
            $table->dropColumn(['home_logo', 'away_logo', 'home_emblem', 'away_emblem']);
        });
    }
};
