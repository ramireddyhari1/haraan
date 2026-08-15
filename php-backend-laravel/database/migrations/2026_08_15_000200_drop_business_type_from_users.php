<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Collapse `users.business_type` back into `users.partner_type`.
 *
 * Added one day earlier (2026_08_15_000100) on the theory that "which console
 * you mount" and "what kind of business you run" were two facts. They are one.
 * Two columns for one fact can disagree, and this pair was guaranteed to: no
 * screen ever wrote `business_type` — the admin's partner form only ever offered
 * "Venue owner" and "Event organiser" — so it was a backfilled column that then
 * sat frozen while `partner_type` moved.
 *
 * `partner_type` is now the single dimension, with a third value:
 *
 *     event → Event host   → events lane
 *     venue → Sports venue → gamehub lane
 *     cafe  → Café venue   → cafe lane
 *
 * The `up()` promotes any café identified by the old column before dropping it,
 * so a partner typed as a café during the one day both existed keeps that
 * meaning instead of silently reverting to a sports venue.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'business_type')) {
            return;
        }

        // Carry the one value that `partner_type` couldn't previously express.
        DB::table('users')
            ->where('role', 'PARTNER')
            ->where('business_type', 'cafe')
            ->update(['partner_type' => 'cafe']);

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('business_type');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'business_type')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('business_type')->nullable()->after('partner_type');
        });

        // Reconstruct it from the surviving single source of truth.
        DB::table('users')->where('role', 'PARTNER')->update([
            'business_type' => DB::raw(
                "case partner_type
                    when 'event' then 'event_venue'
                    when 'cafe'  then 'cafe'
                    else 'sports'
                 end"
            ),
        ]);
    }
};
