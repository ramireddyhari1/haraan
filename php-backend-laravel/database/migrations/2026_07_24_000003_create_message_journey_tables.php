<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbound journeys (automation Phase 1a) — the messages Haraan sends on a
 * schedule rather than in response to an action: event reminders and the
 * post-event review request.
 *
 *  - scheduled_messages: the queue. `dedupe_key` is UNIQUE and is the whole
 *    safety story — enqueueing is re-run by cron every few minutes, so without
 *    it a partner's customers get the same reminder every tick. Rendering
 *    happens at dispatch (not enqueue) so a rescheduled event sends the new
 *    time, and a cancelled booking sends nothing at all.
 *  - messaging_opt_outs: who has said stop. Enforced for journeys only —
 *    ticket delivery is a transaction the customer paid for, and suppressing
 *    it would be worse than annoying them.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('scheduled_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->string('channel')->default('whatsapp');
            $table->string('recipient');
            $table->string('template_key');                 // 'event.reminder_24h', 'review.request'
            $table->string('category')->default('utility');
            // Everything needed to re-render at send time; the booking/event is
            // re-read then, so this is context, not a frozen message body.
            $table->json('payload')->nullable();
            $table->string('context_type')->nullable();
            $table->unsignedBigInteger('context_id')->nullable();

            // The idempotency key: one row per (journey step, booking) forever.
            $table->string('dedupe_key')->unique();

            $table->timestamp('send_after');
            $table->string('status')->default('pending');   // pending|sent|failed|skipped|cancelled
            $table->string('skip_reason')->nullable();      // opted_out|cancelled|no_phone|too_late
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->unsignedBigInteger('message_log_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            // The dispatcher's only query: due, still pending.
            $table->index(['status', 'send_after'], 'sched_msg_due');
            $table->index(['partner_id', 'status']);
            $table->foreign('partner_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('messaging_opt_outs', function (Blueprint $table) {
            $table->id();
            $table->string('channel')->default('whatsapp');
            $table->string('recipient');
            // Null = opted out of everything, from every partner. A per-partner
            // row only silences that one partner.
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->string('reason')->nullable();           // 'stop_keyword', 'admin', 'complaint'
            $table->timestamp('opted_out_at');
            $table->timestamps();

            $table->index(['channel', 'recipient']);
            $table->foreign('partner_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_opt_outs');
        Schema::dropIfExists('scheduled_messages');
    }
};
