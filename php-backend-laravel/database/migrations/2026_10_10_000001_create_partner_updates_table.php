<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What Haraan changed on a partner's account, as the partner is told about it:
 * one row per change made from /control (admin, finance, a Haraan manager), fanned
 * out live to the partner app, as a push, and on WhatsApp. See App\Support\PartnerUpdates.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('partner_updates')) {
            return;
        }

        Schema::create('partner_updates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('partner_id');
            $table->string('kind', 48);               // payout_account.changed, payout_batch.paid, venue.updated …
            $table->string('title', 160);
            $table->text('body')->nullable();
            $table->string('screen', 24)->default('home'); // where the app opens it: payouts | venues | home | account
            $table->string('actor_name', 120)->nullable();
            $table->string('actor_kind', 16)->nullable(); // manager | finance | haraan
            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->timestamp('seen_at')->nullable();
            $table->timestamp('whatsapp_sent_at')->nullable();
            $table->timestamps();

            $table->foreign('partner_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['partner_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_updates');
    }
};
