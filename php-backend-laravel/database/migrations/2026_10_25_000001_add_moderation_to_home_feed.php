<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Managing the ActionBoard Home feed from /control.
 *
 *  - player_posts / post_comments gain a moderation `status` (visible | hidden | removed) plus
 *    who decided it, so a moderator can take something down without destroying the evidence a
 *    report or an appeal needs. Every existing row defaults to `visible` — nothing disappears
 *    on deploy.
 *  - player_posts gains pinning (top of the feed, optionally until a date) and a `featured`
 *    flag (a ranking boost + a "Featured" label).
 *  - post_reports: the Report action on a post or a comment. A worklist, like player_reports.
 *  - feed_restrictions: stop one account posting, commenting, or appearing in the feed, for a
 *    while or for good, without suspending the whole account.
 *
 * Only additive column adds — no ->change() — so SQLite never rebuilds these tables
 * (see the venues ->change() cascade).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_posts', function (Blueprint $table): void {
            $table->string('status', 16)->default('visible');
            $table->timestamp('pinned_at')->nullable();
            $table->timestamp('pinned_until')->nullable();
            $table->boolean('featured')->default(false);
            $table->unsignedInteger('open_reports')->default(0);
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->string('moderation_reason', 300)->nullable();
            $table->index(['status', 'id']);
        });

        Schema::table('post_comments', function (Blueprint $table): void {
            $table->string('status', 16)->default('visible');
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
        });

        Schema::create('post_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained('player_posts')->cascadeOnDelete();
            // Null = the post itself is reported; set = one comment under it.
            $table->foreignId('comment_id')->nullable()->constrained('post_comments')->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 32);
            $table->string('details', 500)->nullable();
            $table->string('status', 16)->default('open');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['post_id', 'reporter_id']);
        });

        Schema::create('feed_restrictions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // no_posting | no_comments | hidden_from_feed
            $table->string('type', 24);
            $table->timestamp('until')->nullable();
            $table->string('reason', 300)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('lifted_at')->nullable();
            $table->foreignId('lifted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_restrictions');
        Schema::dropIfExists('post_reports');

        Schema::table('post_comments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('moderated_by');
            $table->dropColumn(['status', 'moderated_at']);
        });

        Schema::table('player_posts', function (Blueprint $table): void {
            $table->dropIndex(['status', 'id']);
            $table->dropConstrainedForeignId('moderated_by');
            $table->dropColumn(['status', 'pinned_at', 'pinned_until', 'featured', 'open_reports', 'moderated_at', 'moderation_reason']);
        });
    }
};
