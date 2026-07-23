<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('venue_slots', function (Blueprint $table): void {
            // Per-slot price override for peak pricing (e.g. weekend/evening slots
            // cost more). Null = fall back to the venue's default price.
            $table->decimal('price', 10, 2)->nullable()->after('capacity');
        });
    }

    public function down(): void
    {
        Schema::table('venue_slots', function (Blueprint $table): void {
            $table->dropColumn('price');
        });
    }
};
