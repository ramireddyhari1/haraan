<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Venue rating / ratings_count / reviews_count were hand-typed "starter values" in /control,
 * so venues with no reviews showed invented ratings (e.g. 4.2 ★ from 120). From now on they
 * are derived from real, visible venue_reviews rows (Venue::refreshRating()); this brings
 * every existing venue into line once. A venue with no reviews ends at '0' / 0 / 0.
 *
 * Data only — deliberately NO column change. On SQLite, altering a `venues` column rebuilds
 * the table, and the foreign-key cascade on drop deletes every venue's courts, slots, reviews
 * and blocks. That is why "no rating" is '0' rather than NULL (the column is NOT NULL on prod).
 */
return new class extends Migration
{
    public function up(): void
    {
        $stats = DB::table('venue_reviews')
            ->whereNotNull('venue_id')
            ->where('is_active', true)
            ->groupBy('venue_id')
            ->selectRaw('venue_id, COUNT(*) as n, AVG(rating) as avg')
            ->get()
            ->keyBy('venue_id');

        foreach (DB::table('venues')->pluck('id') as $id) {
            $row = $stats->get($id);
            $count = (int) ($row->n ?? 0);

            DB::table('venues')->where('id', $id)->update([
                'rating'        => $count > 0 ? number_format((float) $row->avg, 1, '.', '') : '0',
                'ratings_count' => $count,
                'reviews_count' => $count,
            ]);
        }
    }

    public function down(): void
    {
        // The hand-typed values are not recoverable here; take them from the pre-deploy DB
        // backup if ever needed.
    }
};
