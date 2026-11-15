<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A per-booking payout recorded only an amount and a status, so nobody could
 * later tell how the money went out or trace the transfer. These are the same
 * transfer facts payout_batches already keeps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table): void {
            if (! Schema::hasColumn('payouts', 'method')) {
                $table->string('method', 20)->nullable()->after('status'); // upi, bank, cash
            }
            if (! Schema::hasColumn('payouts', 'reference')) {
                $table->string('reference', 120)->nullable()->after('method'); // UTR / bank ref
            }
            if (! Schema::hasColumn('payouts', 'note')) {
                $table->text('note')->nullable()->after('reference');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table): void {
            $table->dropColumn(['method', 'reference', 'note']);
        });
    }
};
