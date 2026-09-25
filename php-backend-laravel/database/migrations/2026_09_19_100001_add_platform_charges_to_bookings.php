<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The charges /control sets but checkout never collected, now stored per order (first row,
 * beside convenience_fee):
 *   platform_fee   — the platform fee the CUSTOMER paid (0 when the host pays it)
 *   gateway_fee    — the gateway fee the CUSTOMER paid (0 when the host pays it)
 *   tax_amount     — tax on the order (always customer-paid)
 *   host_deduction — what comes off the host's payout: host-paid platform/gateway fees on
 *                    events, Pulse commission on online venue bookings
 *
 * All default 0, so every existing booking reads exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->decimal('platform_fee', 10, 2)->default(0)->after('convenience_fee');
            $table->decimal('gateway_fee', 10, 2)->default(0)->after('platform_fee');
            $table->decimal('tax_amount', 10, 2)->default(0)->after('gateway_fee');
            $table->decimal('host_deduction', 10, 2)->default(0)->after('tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['platform_fee', 'gateway_fee', 'tax_amount', 'host_deduction']);
        });
    }
};
