<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory-gated dynamic pricing ("early bird → phase 1 → phase 2"). Each phase
 * is {label, price, capacity}; the tier's live unit price is the first phase that
 * still has room given how many have already sold. Null/empty = flat `price`.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table): void {
            $table->json('pricing_phases')->nullable()->after('min_price');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table): void {
            $table->dropColumn('pricing_phases');
        });
    }
};
