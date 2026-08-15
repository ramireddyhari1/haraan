<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Branches, business types and capabilities — the multi-outlet spine.
 *
 * A partner has always been able to own many venues (`venues.partner_id` has no
 * unique constraint, and PartnerController::overview() already sums across all of
 * them). What was missing was the *dimension*: nothing said which outlet a venue
 * was, what kind of business ran it, or what that outlet could actually do.
 *
 * Three ideas, deliberately kept separate:
 *
 *  - `venues.kind`        what a branch IS            (sports court / café floor)
 *  - `users.business_type` what the business RUNS      (drives the default preset)
 *  - `capabilities`        what a branch may DO        (bookings, events, …)
 *
 * `users.partner_type` is NOT touched. It keeps exactly one job — which console
 * mounts at login (see User::canManage()) — and `business_type` carries the
 * meaning from here on. Collapsing the two would make a café silently inherit
 * every lane test written as `partner_type !== 'event'`.
 *
 * Capabilities are nullable on BOTH levels and mean "inherit": null on a venue
 * falls back to the business, null on the business falls back to the preset for
 * its type. One Big Bean outlet has six consoles and a stage; another is a
 * twelve-seat coffee counter that takes no bookings at all — so the override has
 * to exist per branch, not just per business. See App\Support\PartnerCapabilities.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table): void {
            // sports | cafe | event_venue. Existing rows are all sports venues.
            $table->string('kind')->default('sports')->after('category');
            // "Koramangala" — what the switcher and every internal table shows, so
            // the brand name doesn't repeat down a column of three branches.
            $table->string('branch_label')->nullable()->after('kind');
            // "BB-KOR" — short, human, safe to read out at a desk or print on a report.
            $table->string('branch_code', 24)->nullable()->after('branch_label');
            // Null = inherit from the business.
            $table->json('capabilities')->nullable()->after('amenities');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('business_type')->nullable()->after('partner_type');
            $table->json('capabilities')->nullable()->after('business_type');
        });

        // Backfill: an event host runs an event venue, everyone else runs a sports
        // venue — the historical default a null partner_type already meant.
        DB::table('users')->where('role', 'PARTNER')->update([
            'business_type' => DB::raw("case when partner_type = 'event' then 'event_venue' else 'sports' end"),
        ]);

        // `venues.location` is already the area ("Bandra", "Koramangala"), which is
        // what an owner would have typed as the branch name anyway. Seeding it means
        // a switcher on an existing multi-venue partner reads sensibly on day one
        // instead of showing three blanks.
        DB::table('venues')->whereNull('branch_label')->update([
            'branch_label' => DB::raw('location'),
        ]);
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table): void {
            $table->dropColumn('kind');
            $table->dropColumn('branch_label');
            $table->dropColumn('branch_code');
            $table->dropColumn('capabilities');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('business_type');
            $table->dropColumn('capabilities');
        });
    }
};
