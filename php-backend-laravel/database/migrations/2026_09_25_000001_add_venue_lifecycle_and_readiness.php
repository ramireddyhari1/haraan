<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table): void {
            if (! Schema::hasColumn('venues', 'status')) {
                $table->string('status')->default('draft')->after('is_bookable');
            }
            if (! Schema::hasColumn('venues', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('status');
            }
            // NO ->change() on `venues`: on SQLite it rebuilds the table and the foreign-key
            // cascade deletes every venue's courts, slots, reviews and blocks. Ratings are
            // handled (data only) by 2026_09_25_000002_derive_venue_ratings_from_reviews.
        });

        // Set default lifecycle states for existing records:
        $venues = DB::table('venues')->get();
        foreach ($venues as $v) {
            $hasCourts = DB::table('venue_courts')
                ->where('venue_id', $v->id)
                ->where('is_active', true)
                ->exists();
            $hasSlots = DB::table('venue_slots')
                ->where('venue_id', $v->id)
                ->exists();
            $hasPrice = (int) $v->price > 0 || DB::table('venue_courts')
                ->where('venue_id', $v->id)
                ->where('is_active', true)
                ->where('price', '>', 0)
                ->exists();
            $images = json_decode((string) $v->images, true);
            $hasImages = is_array($images) && count(array_filter($images)) > 0;

            $isReady = $hasCourts && $hasSlots && $hasPrice && $hasImages;

            if ($v->is_active && $isReady) {
                DB::table('venues')->where('id', $v->id)->update([
                    'status' => 'published',
                    'published_at' => $v->updated_at ?? now(),
                ]);
            } else {
                DB::table('venues')->where('id', $v->id)->update([
                    'status' => 'draft',
                    'is_active' => false,
                    'is_bookable' => false,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table): void {
            if (Schema::hasColumn('venues', 'published_at')) {
                $table->dropColumn('published_at');
            }
            if (Schema::hasColumn('venues', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
