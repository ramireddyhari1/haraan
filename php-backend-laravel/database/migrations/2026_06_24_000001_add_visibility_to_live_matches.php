<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Haraan geo-scoped match visibility — "local-first, admin-global".
 *
 * Every match is born LOCAL and is visible only inside the district it was
 * created in. An admin can promote a match to FEATURED (visible to everyone).
 * Reach is granted, never chosen by the creator — that keeps the national feed
 * high-signal and spam-free.
 *
 * `district`/`state` are stamped at creation from the creator's profile so a
 * future STATE tier can light up with a pure query change (no backfill).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_matches', function (Blueprint $table) {
            // Reach tier. STATE is intentionally reserved for a later phase; the
            // column is a free-form string so adding it needs no schema change.
            $table->string('visibility')->default('LOCAL')->after('is_ranked'); // LOCAL | FEATURED

            // Geography stamped from the creator at creation time (immutable record
            // of where the match belongs). Indexed for the per-district feed.
            $table->string('district')->nullable()->after('visibility');
            $table->string('state')->nullable()->after('district');

            // Audit trail for promotions to FEATURED.
            $table->timestamp('featured_at')->nullable()->after('state');
            $table->unsignedBigInteger('featured_by')->nullable()->after('featured_at');

            // The hot path: "live LOCAL matches in district X, newest first".
            $table->index(['visibility', 'district', 'status', 'updated_at'], 'live_matches_scope_idx');
        });

        // Stamp existing matches' geography from their creator so they slot into
        // the right district once scoping is enforced.
        DB::table('live_matches')->whereNotNull('user_id')->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $creator = DB::table('users')->where('id', $row->user_id)->first(['district', 'state']);
                    DB::table('live_matches')->where('id', $row->id)->update([
                        'district' => $creator->district ?? null,
                        'state'    => $creator->state ?? null,
                    ]);
                }
            });

        // Grandfather every pre-existing match as FEATURED so nothing disappears
        // from the feed on deploy. New matches default to LOCAL.
        DB::table('live_matches')->update(['visibility' => 'FEATURED']);
    }

    public function down(): void
    {
        Schema::table('live_matches', function (Blueprint $table) {
            $table->dropIndex('live_matches_scope_idx');
            $table->dropColumn(['visibility', 'district', 'state', 'featured_at', 'featured_by']);
        });
    }
};
