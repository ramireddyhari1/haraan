<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily view counters for host profiles (Phase 3 analytics). One row per profile
 * per day (incremented, session-deduped) rather than a row per hit — cheap to
 * store and to chart.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('host_profile_views', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('host_profile_id');
            $table->date('day');
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();
            $table->unique(['host_profile_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('host_profile_views');
    }
};
