<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table): void {
            // Structured price chart (Playo-style): variants → day groups → time bands.
            // Shape: [{ label, groups: [{ days, rows: [{ time, price }] }] }]
            $table->json('price_chart')->nullable()->after('price');
            // Small disclaimer shown under the price-chart header.
            $table->string('price_note')->nullable()->after('price_chart');
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table): void {
            $table->dropColumn(['price_chart', 'price_note']);
        });
    }
};
