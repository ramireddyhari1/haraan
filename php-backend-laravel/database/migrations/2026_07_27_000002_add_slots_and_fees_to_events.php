<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Event-level ticket-setup knobs that go with the redesigned authoring UI:
 *  - tickets_per_slot: "Same for all slots" (false) vs "Customize per slot" (true).
 *  - inquiry_phone: WhatsApp number for "contact host" inquiries on this event.
 *  - gateway_fee_* / platform_fee_*: the payment-gateway and platform fees, each
 *    with a "customer pays" vs "host pays" payer. These sit alongside the existing
 *    admin-only `fees` JSON (host-defined convenience fees), which is unchanged.
 *
 * String columns (not enum) for SQLite portability.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->boolean('tickets_per_slot')->default(false)->after('total_slots');
            $table->string('inquiry_phone')->nullable()->after('tickets_per_slot');

            $table->string('gateway_fee_payer')->default('customer')->after('inquiry_phone');
            $table->string('gateway_fee_type')->default('none')->after('gateway_fee_payer');
            $table->decimal('gateway_fee_value', 10, 2)->default(0)->after('gateway_fee_type');

            $table->string('platform_fee_payer')->default('customer')->after('gateway_fee_value');
            $table->string('platform_fee_type')->default('none')->after('platform_fee_payer');
            $table->decimal('platform_fee_value', 10, 2)->default(0)->after('platform_fee_type');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn([
                'tickets_per_slot',
                'inquiry_phone',
                'gateway_fee_payer',
                'gateway_fee_type',
                'gateway_fee_value',
                'platform_fee_payer',
                'platform_fee_type',
                'platform_fee_value',
            ]);
        });
    }
};
