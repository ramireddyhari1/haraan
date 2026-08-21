<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The blue tick on a player profile — granted by an admin in /control, never earned
 * automatically and never self-set.
 *
 * Deliberately its own column rather than a reuse of `is_organizer`: being a host and being
 * a verified identity are different claims, and the profile already learned that lesson the
 * hard way (the organiser card once rendered a tick that stood for nothing).
 * `verified_at` exists so support can answer "since when" without a separate audit lookup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_verified')->default(false)->after('is_organizer');
            $table->timestamp('verified_at')->nullable()->after('is_verified');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['is_verified', 'verified_at']);
        });
    }
};
