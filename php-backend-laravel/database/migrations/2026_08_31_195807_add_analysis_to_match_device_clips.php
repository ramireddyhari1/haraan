<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The review a clip has been given, stored beside the clip itself.
 *
 * Kept on the clip rather than in its own table because a clip has exactly one review:
 * the footage does not change, so neither does what can be seen in it. Re-reviewing the
 * same seconds of video would cost a Vertex call to produce the same answer.
 *
 * Nullable throughout — a clip that has never been reviewed is the normal case, and most
 * clips never will be. Only an appealed delivery is worth the call.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_device_clips', function (Blueprint $table): void {
            $table->json('analysis')->nullable()->after('over_ball');
            $table->timestamp('analysed_at')->nullable()->after('analysis');
        });
    }

    public function down(): void
    {
        Schema::table('match_device_clips', function (Blueprint $table): void {
            $table->dropColumn(['analysis', 'analysed_at']);
        });
    }
};
