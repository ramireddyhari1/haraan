<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A bookable unit learns to describe itself: how many it seats, and what it is.
 *
 * `venue_courts` was always the right table for a café — it stores "the physical
 * thing that can hold only one booking at a time", and the conflict rule a pool
 * table needs is exactly the rule a turf needs. What it couldn't say was
 * *"Table 04 · 4 seats"* or *"PS5 · station"*, so a café floor rendered as a list
 * of nameless courts.
 *
 * Two columns, deliberately not one:
 *
 *  - `seats` is CAPACITY, and only some resources have it. A pool table seats
 *    nobody in particular; Table 04 seats four. Null means "not a seating
 *    thing", which is different from zero.
 *  - `kind` is WHAT IT IS, and drives the icon and the wording. Null inherits
 *    the lane's default noun (court on a turf, table in a café) so every
 *    existing row keeps behaving exactly as it does today.
 *
 * Neither is required. A sports venue that never touches them sees no change.
 */
return new class extends Migration
{
    /** Resource kinds the desk knows how to draw. Stored as plain strings. */
    public const KINDS = ['court', 'table', 'station', 'room', 'lane'];

    public function up(): void
    {
        Schema::table('venue_courts', function (Blueprint $table): void {
            // Party size. Null = capacity isn't a meaningful property here.
            $table->unsignedSmallInteger('seats')->nullable()->after('name');
            // court | table | station | room | lane. Null = the lane's default.
            $table->string('kind', 20)->nullable()->after('seats');
        });

        // Existing rows are all sports courts, and saying so explicitly means the
        // desk never has to guess from the venue's lane for historic data.
        DB::table('venue_courts')->whereNull('kind')->update(['kind' => 'court']);
    }

    public function down(): void
    {
        Schema::table('venue_courts', function (Blueprint $table): void {
            $table->dropColumn('seats');
            $table->dropColumn('kind');
        });
    }
};
