<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quoted replies and forwarding for direct messages.
 *
 * `reply_to_id` points at another message in the SAME conversation — enforced in the service,
 * because a reply quoting something from a thread you can't see would leak it. It is nullable
 * and `nullOnDelete`: unsending the quoted message must not take the reply with it, the quote
 * simply stops resolving and renders as "unsent".
 *
 * `is_forwarded` is a flag, not a link back to the original. A forward is a NEW message from
 * the person forwarding it — following a pointer into a conversation the reader may not be a
 * member of is exactly the leak this app should not have. The flag only says "these aren't my
 * words", which is the honest part of what a forwarded label means.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('direct_messages', function (Blueprint $table): void {
            $table->foreignId('reply_to_id')->nullable()->after('body')
                ->constrained('direct_messages')->nullOnDelete();
            $table->boolean('is_forwarded')->default(false)->after('reply_to_id');
        });
    }

    public function down(): void
    {
        Schema::table('direct_messages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reply_to_id');
            $table->dropColumn('is_forwarded');
        });
    }
};
