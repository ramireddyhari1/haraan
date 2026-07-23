<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — runtime feature flags. Admins toggle app capabilities from /control
 * with no release. Resolution layers (see FeatureFlag::isEnabledFor):
 *   enabled (master) → app-version gate → org/district targeting → % rollout.
 * Resolved per user and exposed through GET /api/config.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();              // machine name the app checks
            $table->string('name');                        // human label for the console
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(false);    // master switch
            $table->unsignedTinyInteger('rollout_percentage')->default(100); // 0–100
            $table->json('organization_ids')->nullable();  // null/empty = all districts
            $table->string('min_app_version')->nullable(); // e.g. "1.4.0"
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flags');
    }
};
