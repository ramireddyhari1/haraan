<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Venue-slot checkout was the only paid flow with no fee and no coupon support: the
 * order summary could show a rate and a total and nothing else, because nothing else
 * existed. This gives venues the same two levers events already have.
 *
 * Deliberately mirrors the event columns (`convenience_fee_type` / `_value`, none|flat|
 * percent) rather than inventing a second shape, so {@see \App\Models\Venue::convenienceFeeFor()}
 * can be a straight port of the event method and a reader only has to learn one idea.
 *
 * Coupons were scoped by `event_id` alone, where null meant "any event" — which quietly
 * excluded venues. `venue_id` scopes a code to one venue; `scope` says which side of the
 * product a global code is allowed on, so an existing null/null coupon keeps behaving
 * exactly as it does today (events only) instead of silently becoming valid on turf.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table): void {
            $table->string('convenience_fee_type')->default('none')->after('price');
            $table->decimal('convenience_fee_value', 10, 2)->default(0)->after('convenience_fee_type');
        });

        Schema::table('coupons', function (Blueprint $table): void {
            $table->unsignedBigInteger('venue_id')->nullable()->after('event_id');
            // event | venue | all. Existing rows default to 'event', preserving today's
            // behaviour for every coupon already in the table.
            $table->string('scope')->default('event')->after('venue_id');
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table): void {
            $table->dropColumn(['convenience_fee_type', 'convenience_fee_value']);
        });

        Schema::table('coupons', function (Blueprint $table): void {
            $table->dropColumn(['venue_id', 'scope']);
        });
    }
};
