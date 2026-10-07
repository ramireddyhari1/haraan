<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Haraan employee who looks after a partner — one per partner account, picked in
 * /control and shown as a card on top of the partner's Venues screen (app + web).
 *
 * `phone` is the number the partner sees. Left blank, the card falls back to the shared
 * support WhatsApp from Branding, so an employee's personal number is never exposed
 * unless an admin types it in on purpose.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('partner_managers')) {
            return;
        }

        Schema::create('partner_managers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 60)->nullable();
            $table->string('intro', 160)->nullable();
            $table->string('hours', 60)->nullable();
            $table->string('phone', 20)->nullable();
            $table->boolean('show_call')->default(true);
            $table->boolean('show_whatsapp')->default(true);
            $table->boolean('show_chat')->default(true);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_managers');
    }
};
