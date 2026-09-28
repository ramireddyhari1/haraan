<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which BALL a clip belongs to.
 *
 * The scorer's BALL tap now arms the paired camera, and each tap carries a number
 * (App\Support\BallInPlay). The camera stamps its clip with that number so REVIEW can open
 * the clip for the ball just bowled — not merely the newest file, which on a slow upload
 * is the ball before.
 *
 * Null for clips filmed with the camera's own button outside a BALL window, and for every
 * clip recorded before this existed. An added nullable column only — no ->change(), which
 * on SQLite rebuilds the table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_device_clips', function (Blueprint $table): void {
            $table->unsignedInteger('ball_seq')->nullable()->after('over_ball');
        });
    }

    public function down(): void
    {
        Schema::table('match_device_clips', function (Blueprint $table): void {
            $table->dropColumn('ball_seq');
        });
    }
};
