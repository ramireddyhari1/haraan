<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Session revocation for stateless JWTs.
 *
 * Until now `/api/auth/logout` invalidated nothing — tokens are stateless with a 7-day
 * TTL, so signing out only forgot the token locally and the token itself stayed usable
 * for up to a week. That was survivable at one token per device; the multi-account
 * switcher deliberately stores several at once, so a real kill switch is required.
 *
 * Every issued JWT carries the user's `token_version` as `tv`. The auth middleware
 * compares the two and rejects any mismatch, so bumping this column invalidates every
 * token that user holds, instantly and everywhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedInteger('token_version')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('token_version');
        });
    }
};
