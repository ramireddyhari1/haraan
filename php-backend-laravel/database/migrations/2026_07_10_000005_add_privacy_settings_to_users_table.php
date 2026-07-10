<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user privacy controls, surfaced in the app's Account → Privacy screen.
 *
 * All default to `true`: Haraan is a public leaderboard product, and every
 * existing player signed up under those terms. Defaulting to `false` would
 * silently empty the district boards on deploy.
 */
return new class extends Migration
{
    /** column => default */
    private const COLUMNS = [
        'privacy_public_profile' => true,   // anyone may open my player profile
        'privacy_show_stats' => true,       // career stats visible on that profile
        'privacy_show_district' => true,    // my district/state shown publicly
        'privacy_discoverable' => true,     // findable in player search
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach (self::COLUMNS as $column => $default) {
                $table->boolean($column)->default($default);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(array_keys(self::COLUMNS));
        });
    }
};
