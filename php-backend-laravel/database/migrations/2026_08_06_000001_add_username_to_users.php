<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the player handle used to find someone when building a squad.
 *
 * Until now the only way to add a teammate to a match was to type their Player ID
 * (HRN-000123) — an account number nobody knows by heart, which made the squad step
 * unusable unless both players were sitting together. A username is the thing a
 * player can actually tell a friend.
 *
 * NULLABLE on purpose: every existing account predates this column and must keep
 * working untouched. New profiles are asked for one at setup; existing players stay
 * findable by name and Player ID until they choose a handle. Uniqueness is enforced
 * on the stored value, which is always normalised to lowercase (see User::normalizeUsername).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 30)->nullable()->unique()->after('player_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Drop the index explicitly: SQLite keeps the unique index around otherwise.
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
