<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Venues can host more than one game (e.g. a turf used for both football and box-cricket).
 * `category` stays the primary sport (badge / filter); `sports` is the full list surfaced as
 * icons on the venue card. Backfilled from the existing category so no venue loses its sport.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table): void {
            $table->json('sports')->nullable()->after('category');
        });

        // Backfill: seed each venue's sports list with its current primary category so the
        // card always shows at least one sport icon even before an owner edits the list.
        DB::table('venues')->select('id', 'category')->orderBy('id')->each(function ($v): void {
            DB::table('venues')->where('id', $v->id)->update([
                'sports' => json_encode(array_values(array_filter([$v->category]))),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table): void {
            $table->dropColumn('sports');
        });
    }
};
