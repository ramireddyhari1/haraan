<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A settlement can carry a partner-specific commission. Store the rate and
 * amount with the batch so both the payout history and future available balance
 * remain correct after finance has settled it.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('payout_batches', function (Blueprint $table): void {
            $table->decimal('commission_percent', 5, 2)->default(0)->after('amount');
            $table->decimal('commission_amount', 10, 2)->default(0)->after('commission_percent');
        });
    }

    public function down(): void
    {
        Schema::table('payout_batches', function (Blueprint $table): void {
            $table->dropColumn(['commission_percent', 'commission_amount']);
        });
    }
};
