<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            // Which curated app rails this event appears in, e.g. ["for_you","trending"].
            // Null/empty = the app falls back to showing it everywhere.
            $table->json('placements')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('placements');
        });
    }
};
