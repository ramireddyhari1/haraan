<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reviews from the venue page itself (app + website), and moderation for them.
 *
 *   user_id        who wrote it — one review per person per venue, re-rating updates it.
 *                  NULL for the booking-link (/r/{code}) reviews, whose author is a guest.
 *   source         app | web | link — where it came from, shown in /control.
 *   hidden_reason  why /control hid it (is_active = false), shown to admins only.
 *   deleted_at     soft delete, so /control can "see deleted" and restore.
 *
 * Columns are only ADDED: no change to an existing column, so SQLite never rebuilds
 * the table (see the venues ->change() cascade landmine).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_reviews', function (Blueprint $table): void {
            if (! Schema::hasColumn('venue_reviews', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->index();
            }
            if (! Schema::hasColumn('venue_reviews', 'source')) {
                $table->string('source', 16)->nullable();
            }
            if (! Schema::hasColumn('venue_reviews', 'hidden_reason')) {
                $table->string('hidden_reason', 160)->nullable();
            }
            if (! Schema::hasColumn('venue_reviews', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('venue_reviews', function (Blueprint $table): void {
            $table->dropIndex(['user_id']);
            $table->dropColumn(['user_id', 'source', 'hidden_reason', 'deleted_at']);
        });
    }
};
