<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Group direct messages. The 1:1 schema already stored participants in a join table,
 * so a group is the same conversation with more than two members — this only adds the
 * three fields that distinguish and name one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            // A 1:1 has no title (the name is "the other person"); a group does.
            $table->boolean('is_group')->default(false)->after('id');
            $table->string('title')->nullable()->after('is_group');
            // Who made it — used for the "created the group" system line and nothing
            // privileged in v1 (any member may leave; only the creator picked members).
            $table->foreignId('created_by')->nullable()->after('last_sender_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['is_group', 'title']);
        });
    }
};
