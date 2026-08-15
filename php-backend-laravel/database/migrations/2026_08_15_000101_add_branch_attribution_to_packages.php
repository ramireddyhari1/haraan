<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which branch sold a pass, and which branch spent it.
 *
 * `venue_packages.venue_id` already says where a pass may be USED (null = any of
 * the partner's venues). Neither of the ledger tables recorded where the money
 * actually moved:
 *
 *  - a pass sold at Koramangala looked identical to one sold at HSR, so no branch
 *    could be credited for selling it;
 *  - a redemption stores only `booking_id`, and a walk-in redemption has no
 *    booking — so the branch that gave away the session was unrecoverable.
 *
 * Both are write-once facts about a past event, which is why they are columns on
 * the ledger rows rather than something derived later from the booking. Once a
 * session has been spent there is no other record of where it happened, and a
 * number you cannot reconstruct is one you must store at the time.
 *
 * Nullable, because every row that exists today predates branches — a null means
 * "single-branch era", not "unknown branch", and reports read it that way.
 *
 * Plain indexed columns, NOT `foreignId()->constrained()`. On SQLite — the default
 * connection here — adding a constrained FK to an existing table makes Laravel
 * rebuild it through a `__temp__` copy, which is a needless risk against live
 * ledger tables for a constraint SQLite only enforces when `pragma foreign_keys`
 * happens to be on. `staff_venues` sets the same precedent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_packages', function (Blueprint $table): void {
            $table->unsignedBigInteger('sold_at_venue_id')->nullable()->after('partner_id');
            $table->index('sold_at_venue_id');
        });

        Schema::table('package_redemptions', function (Blueprint $table): void {
            $table->unsignedBigInteger('redeemed_at_venue_id')->nullable()->after('booking_id');
            $table->index('redeemed_at_venue_id');
        });
    }

    public function down(): void
    {
        Schema::table('customer_packages', function (Blueprint $table): void {
            $table->dropIndex(['sold_at_venue_id']);
            $table->dropColumn('sold_at_venue_id');
        });

        Schema::table('package_redemptions', function (Blueprint $table): void {
            $table->dropIndex(['redeemed_at_venue_id']);
            $table->dropColumn('redeemed_at_venue_id');
        });
    }
};
