<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            // Pasted Google Maps place/share link; opened directly for "Directions".
            $table->string('map_link', 600)->nullable()->after('location');
        });
        Schema::table('venues', function (Blueprint $table): void {
            $table->string('map_link', 600)->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('map_link');
        });
        Schema::table('venues', function (Blueprint $table): void {
            $table->dropColumn('map_link');
        });
    }
};
