<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — localization console. One row per (key, locale). Admins manage app
 * copy for en/te/ta/kn/ml/hi from /control; the app pulls bundles via
 * GET /api/i18n/{locale} and overlays them, so wording/translation fixes ship
 * without an app release. See App\Models\Translation.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->string('group')->nullable()->index(); // organizational label, e.g. match_detail
            $table->string('key');                          // dot key the app looks up
            $table->string('locale', 8);
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['key', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
