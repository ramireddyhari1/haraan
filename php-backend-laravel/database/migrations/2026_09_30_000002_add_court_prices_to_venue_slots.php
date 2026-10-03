<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A slot's price per court: {"12": 500, "13": 800}. Courts at one venue cost different
 * amounts, so one price per slot could only be right for one of them.
 *
 * Nullable and not backfilled — null means "no per-court price", exactly what every row
 * meant before. Adding a column is safe on SQLite; only ->change() rebuilds the table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('venue_slots', 'court_prices')) {
            Schema::table('venue_slots', function (Blueprint $table): void {
                $table->json('court_prices')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('venue_slots', 'court_prices')) {
            Schema::table('venue_slots', function (Blueprint $table): void {
                $table->dropColumn('court_prices');
            });
        }
    }
};
