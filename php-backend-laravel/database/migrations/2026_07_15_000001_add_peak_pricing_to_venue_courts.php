<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional peak pricing per court: a higher rate that applies on selected weekdays and/or
 * within a time window (e.g. evenings and weekends). When it doesn't apply, the court's base
 * price stands. This is the self-serve dynamic pricing Playo makes venues call support for.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('venue_courts', function (Blueprint $table): void {
            // Higher hourly rate; null = no peak pricing (base price always).
            $table->unsignedInteger('peak_price')->nullable()->after('price');
            // Weekdays it applies on, as 3-letter names ["Sat","Sun"]. Empty = every day.
            $table->json('peak_days')->nullable()->after('peak_price');
            // Time window it applies within, "HH:MM". Both null = the whole day.
            $table->string('peak_start', 5)->nullable()->after('peak_days');
            $table->string('peak_end', 5)->nullable()->after('peak_start');
        });
    }

    public function down(): void
    {
        Schema::table('venue_courts', function (Blueprint $table): void {
            foreach (['peak_price', 'peak_days', 'peak_start', 'peak_end'] as $col) {
                if (Schema::hasColumn('venue_courts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
