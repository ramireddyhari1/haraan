<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messaging ledger (automation Phase 0) — measurement before monetisation.
 *
 * Today we fire WhatsApp/SMS at Twilio and keep nothing but a log line, so
 * "how many messages did partner 36 send last month, and what did it cost?"
 * is unanswerable. That question has to be answerable BEFORE conversation
 * quotas can be priced into partner plans, and it's also what a billing
 * dispute gets settled with.
 *
 *  - message_conversations: the 24-hour billing window Meta actually charges
 *    for — one row per (partner, channel, recipient, category) window. Meter
 *    conversations, not messages, because that's the unit of cost.
 *  - message_log: every send ATTEMPT, including the ones that never left the
 *    building (disabled/unconfigured/unroutable), so silent delivery failures
 *    stop being invisible.
 *  - messaging_usage: the rolled-up per-period counters the quota check will
 *    read. Derivable from the log, kept separate so the hot path is one
 *    increment rather than an aggregate over a growing table.
 *  - message_templates: the registry that becomes the approved-template
 *    catalogue. Empty for now — Phase 0 only records what already goes out.
 *
 * partner_id is nullable throughout: platform-owned sends (login OTP) belong
 * to no partner and must never be billed to one.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('message_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->string('channel');                          // whatsapp | sms | instagram
            $table->string('recipient');                        // E.164 phone / IG scoped id
            $table->string('category')->default('utility');     // utility|marketing|authentication|service
            $table->timestamp('opened_at');
            $table->timestamp('expires_at');                    // opened_at + 24h
            $table->unsignedInteger('message_count')->default(0);
            $table->timestamps();

            // The meter's lookup: newest live window for this tuple.
            $table->index(['partner_id', 'channel', 'recipient', 'category', 'expires_at'], 'msg_conv_lookup');
            $table->foreign('partner_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('message_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->string('channel');
            $table->string('direction')->default('out');        // out | in (inbound lands in Phase 1)
            $table->string('recipient');
            $table->string('category')->default('utility');
            $table->string('template_key')->nullable();         // null = free-form (inside a 24h window)
            $table->string('context_type')->nullable();         // e.g. booking — what triggered it
            $table->unsignedBigInteger('context_id')->nullable();
            $table->string('provider')->default('twilio');
            $table->string('provider_message_id')->nullable();  // Twilio SID — the key a cost backfill joins on
            $table->string('status');                           // sent|failed|disabled|unconfigured|unroutable
            $table->text('error')->nullable();
            // Cost is unknown at send time (Twilio prices asynchronously). Left null in
            // Phase 0 and backfilled from the provider SID; micros keeps it integer.
            $table->unsignedBigInteger('cost_micros')->nullable();
            $table->string('currency', 8)->nullable();
            $table->timestamps();

            $table->index(['partner_id', 'created_at']);
            $table->index(['channel', 'status']);
            $table->index('provider_message_id');
            $table->foreign('partner_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('conversation_id')->references('id')->on('message_conversations')->nullOnDelete();
        });

        Schema::create('messaging_usage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->string('channel');
            // Calendar month for now. Phase 2 re-keys these to the subscription's
            // billing period — a partner who subscribes on the 20th must not get a
            // free quota reset on the 1st.
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('conversations_opened')->default(0);
            $table->unsignedInteger('messages_sent')->default(0);
            $table->unsignedInteger('messages_failed')->default(0);
            $table->unsignedBigInteger('cost_micros')->default(0);
            $table->timestamps();

            $table->index(['partner_id', 'period_start', 'channel'], 'msg_usage_period');
            $table->foreign('partner_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();                    // 'booking.ticket', 'event.reminder_24h'
            $table->unsignedBigInteger('partner_id')->nullable(); // null = platform-owned catalogue
            $table->string('name');
            $table->string('channel')->default('whatsapp');
            $table->string('category')->default('utility');
            $table->string('locale', 12)->default('en');
            $table->text('body');
            $table->json('variables')->nullable();              // ordered placeholder names
            $table->string('provider_template_id')->nullable(); // Twilio Content SID once approved
            $table->string('status')->default('draft');         // draft|submitted|approved|rejected
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('partner_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
        Schema::dropIfExists('messaging_usage');
        Schema::dropIfExists('message_log');
        Schema::dropIfExists('message_conversations');
    }
};
