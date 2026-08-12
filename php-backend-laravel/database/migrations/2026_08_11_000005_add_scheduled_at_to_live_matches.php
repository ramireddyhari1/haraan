<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A match may now be created for a future kick-off time. `scheduled_at` holds that
 * time; NULL means "play now" (created to be started immediately). The lifecycle
 * status column is unchanged — a match is 'Scheduled' until its toss goes Live —
 * so the app's Scheduled list is `status = Scheduled` regardless of this column,
 * and `scheduled_at` just adds a start time + countdown when the creator picked one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_matches', function (Blueprint $table): void {
            $table->timestamp('scheduled_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('live_matches', function (Blueprint $table): void {
            $table->dropColumn('scheduled_at');
        });
    }
};
