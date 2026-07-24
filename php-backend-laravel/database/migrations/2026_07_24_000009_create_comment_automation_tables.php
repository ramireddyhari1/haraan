<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instagram comment-to-DM — someone comments "price?" on an event reel and gets
 * a DM with the booking link. The highest-intent automation in this market, and
 * the one competitors lead with.
 *
 *  - automation_rules.public_reply_body: the optional visible reply under the
 *    comment ("sent you a DM!"). It's what makes the automation legible to
 *    everyone else reading the thread, not just the one person.
 *  - instagram_comment_replies: one row per comment, comment_id UNIQUE. Meta
 *    allows exactly ONE private reply per comment and redelivers webhooks on
 *    retry, so this is both an idempotency guard and a rule-compliance guard —
 *    a second attempt is an API error, not just a duplicate message.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('automation_rules', function (Blueprint $table) {
            $table->text('public_reply_body')->nullable()->after('reply_body');
        });

        Schema::create('instagram_comment_replies', function (Blueprint $table) {
            $table->id();
            // The whole point of the table.
            $table->string('comment_id')->unique();
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->unsignedBigInteger('connection_id')->nullable();
            $table->unsignedBigInteger('rule_id')->nullable();
            $table->string('media_id')->nullable();
            $table->string('commenter_id')->nullable();     // IG-scoped user id
            $table->string('commenter_username')->nullable();
            $table->string('status')->default('sent');      // sent | failed | skipped
            $table->string('skip_reason')->nullable();
            $table->timestamps();

            $table->index(['partner_id', 'created_at']);
            $table->foreign('partner_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('connection_id')->references('id')->on('channel_connections')->nullOnDelete();
            $table->foreign('rule_id')->references('id')->on('automation_rules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_comment_replies');

        Schema::table('automation_rules', function (Blueprint $table) {
            $table->dropColumn('public_reply_body');
        });
    }
};
