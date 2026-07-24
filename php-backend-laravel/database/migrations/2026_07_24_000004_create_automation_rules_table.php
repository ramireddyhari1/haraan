<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inbound auto-reply rules (automation Phase 1b).
 *
 * A rule turns "what the customer typed" into "what we say back". Two triggers:
 *   keyword  — the message contains/equals one of `keywords`
 *   fallback — nothing else matched (the away message)
 *
 * partner_id null = platform rule, applying to every partner on the shared
 * sender. Partner-owned rules win over platform ones, and inside each scope
 * `priority` decides — so a venue can override the generic "we'll get back to
 * you" without anyone editing shared copy.
 *
 * STOP/START/HELP are deliberately NOT rules: they're compliance, they must work
 * identically for every partner, and nobody should be able to disable or
 * reword their way out of an opt-out.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->string('channel')->default('whatsapp');
            $table->string('name');
            $table->string('trigger_type')->default('keyword');  // keyword | fallback
            $table->json('keywords')->nullable();                // lowercased match terms
            $table->string('match_type')->default('contains');   // contains | exact
            $table->text('reply_body');
            $table->unsignedSmallInteger('priority')->default(100); // lower wins
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['channel', 'is_active', 'priority']);
            $table->foreign('partner_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_rules');
    }
};
