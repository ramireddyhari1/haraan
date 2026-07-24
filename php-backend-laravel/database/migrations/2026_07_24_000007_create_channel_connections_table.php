<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Linked messaging accounts (automation Phase 3) — which partner owns which
 * Instagram account.
 *
 * This solves something WhatsApp couldn't. On the shared WhatsApp sender an
 * inbound message carries no hint of which organiser it's about, so attribution
 * is a heuristic (whoever last talked to that number). An Instagram DM arrives
 * on a SPECIFIC account, so `external_id` maps it to exactly one partner. No
 * guessing.
 *
 * `access_token` is encrypted at rest via the model cast — a page token can
 * read and send DMs, so a database dump must not hand it over in the clear.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('channel_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id');
            $table->string('channel')->default('instagram');
            // The Instagram professional account id that DMs arrive on.
            $table->string('external_id');
            $table->string('username')->nullable();      // @handle, for the admin UI
            $table->string('page_id')->nullable();       // linked Facebook page
            $table->text('access_token')->nullable();    // encrypted
            $table->timestamp('token_expires_at')->nullable();
            $table->string('status')->default('active'); // active | disconnected | error
            $table->text('last_error')->nullable();
            $table->timestamps();

            // One partner per account: the routing lookup depends on it.
            $table->unique(['channel', 'external_id']);
            $table->index(['partner_id', 'channel']);
            $table->foreign('partner_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_connections');
    }
};
