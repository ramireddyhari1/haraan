<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Remember WHICH Google place a venue/event was picked from, not just where it
     * is. Coordinates answer "draw a pin here"; a place_id answers "this is that
     * exact listing" — the only handle that can later pull the place's photos,
     * opening hours or rating from Google.
     *
     * Google's terms let a place_id be stored indefinitely (unlike the photos and
     * details themselves, which must be re-fetched), so capturing it at pick time
     * is the cheap, permanent half of the work. It's set for free inside the same
     * Autocomplete session the form already runs — nothing extra is billed.
     *
     * Nullable + no backfill: existing rows keep working off coordinates alone,
     * and a place_id appears the next time someone re-picks the venue in the form.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            if (! Schema::hasColumn('events', 'place_id')) {
                $table->string('place_id', 255)->nullable()->after('map_link');
            }
        });

        Schema::table('venues', function (Blueprint $table): void {
            if (! Schema::hasColumn('venues', 'place_id')) {
                $table->string('place_id', 255)->nullable()->after('map_link');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('place_id');
        });

        Schema::table('venues', function (Blueprint $table): void {
            $table->dropColumn('place_id');
        });
    }
};
