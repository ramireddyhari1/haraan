<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turns the flat ticket tier into a flexible pricing model. One `ticket_types`
 * row can now represent a bundle (admits N per ticket), an add-on, or a
 * pay-what-you-want donation, plus an optional on-sale window (early bird).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table): void {
            // standard | addon | donation — drives client UX and booking rules.
            $table->string('kind')->default('standard')->after('name');
            // People admitted per ticket (Group/Couple/Family bundles). 1 = normal.
            $table->unsignedInteger('admits')->default(1)->after('price');
            // Pay-what-you-want floor for donation tiers (null otherwise).
            $table->decimal('min_price', 10, 2)->nullable()->after('admits');
            // Early-bird / timed sale window (null = always on sale).
            $table->timestamp('sales_start')->nullable()->after('sort');
            $table->timestamp('sales_end')->nullable()->after('sales_start');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table): void {
            $table->dropColumn(['kind', 'admits', 'min_price', 'sales_start', 'sales_end']);
        });
    }
};
