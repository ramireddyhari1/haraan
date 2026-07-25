<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records when a notification's background FCM push was fanned out. Also the
 * idempotency guard for the sender: the model dispatches the push job only when
 * status='sent' AND pushed_at is null, and the job stamps it — so a later edit
 * of an already-sent notification never re-pushes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->timestamp('pushed_at')->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropColumn('pushed_at');
        });
    }
};
