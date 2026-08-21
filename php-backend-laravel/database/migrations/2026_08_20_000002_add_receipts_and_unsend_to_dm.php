<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery receipts and unsend for direct messages.
 *
 * `last_delivered_at` is genuinely different from the `last_read_at` already on this table,
 * and the difference is the whole point of a second tick: DELIVERED means the recipient's
 * app has pulled the message down (it stamps on the thread-LIST fetch), READ means they
 * opened the conversation. Without the split, a "delivered" tick would either be a guess or
 * a synonym for "the server accepted it" — which is what the first tick already says.
 *
 * `deleted_at` on the message keeps the row when someone unsends: the thread stays ordered
 * and every other client can be told the message is gone, instead of a hole appearing in
 * the history with no explanation. The BODY is cleared at the same time, so an unsent
 * message really is unreadable rather than merely hidden by the UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_participants', function (Blueprint $table): void {
            $table->timestamp('last_delivered_at')->nullable()->after('last_read_at');
        });

        Schema::table('direct_messages', function (Blueprint $table): void {
            $table->timestamp('deleted_at')->nullable()->after('body')->index();
        });
    }

    public function down(): void
    {
        Schema::table('conversation_participants', function (Blueprint $table): void {
            $table->dropColumn('last_delivered_at');
        });

        Schema::table('direct_messages', function (Blueprint $table): void {
            $table->dropColumn('deleted_at');
        });
    }
};
