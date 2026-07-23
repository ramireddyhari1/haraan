<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-event, host-set convenience fee. Type is none | flat | percent; value is a
 * flat ₹ amount or a percentage of the ticket subtotal. The computed fee is stored
 * once per order on the first booking row (`bookings.convenience_fee`).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('convenience_fee_type')->default('none')->after('price');
            $table->decimal('convenience_fee_value', 10, 2)->default(0)->after('convenience_fee_type');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->decimal('convenience_fee', 10, 2)->default(0)->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn(['convenience_fee_type', 'convenience_fee_value']);
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn('convenience_fee');
        });
    }
};
