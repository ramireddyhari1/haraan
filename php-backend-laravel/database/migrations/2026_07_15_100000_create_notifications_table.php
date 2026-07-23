<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin/Haraan-team broadcast notifications shown in the app's bell inbox.
 *
 * One row = one message aimed at an audience segment (not fanned out per user).
 * `notification_reads` records who has opened each one, so the unread badge and
 * "mark read" work without duplicating the message body per recipient.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('image_url')->nullable();
            // Optional tap target, e.g. "event:12", "match:35", or a full URL. The
            // app maps known prefixes to screens; unknown values just don't navigate.
            $table->string('deep_link')->nullable();
            // Audience segment. `type` in: all | district | state | sport | user.
            // `value` holds the district/state/sport name or the target user id.
            $table->string('audience_type')->default('all');
            $table->string('audience_value')->nullable();
            // draft = composed but not delivered; sent = live in users' inboxes.
            $table->string('status')->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'sent_at']);
            $table->index(['audience_type', 'audience_value']);
        });

        Schema::create('notification_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_id')->constrained('notifications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at')->useCurrent();

            $table->unique(['notification_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_reads');
        Schema::dropIfExists('notifications');
    }
};
