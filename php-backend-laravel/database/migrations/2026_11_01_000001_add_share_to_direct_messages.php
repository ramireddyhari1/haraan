<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A message can carry a shared match, venue or player.
 *
 * Stored as a type and a reference, never as a copy of the card. The card is built when the
 * thread is read, for the person reading it — so a shared live match shows the score as it is
 * now, a venue that has been taken down stops advertising itself, and a private match only
 * shows its details to people who are allowed to see it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('direct_messages', function (Blueprint $table): void {
            $table->string('share_type', 16)->nullable()->after('is_forwarded');
            $table->string('share_ref', 64)->nullable()->after('share_type');
        });
    }

    public function down(): void
    {
        Schema::table('direct_messages', function (Blueprint $table): void {
            $table->dropColumn(['share_type', 'share_ref']);
        });
    }
};
