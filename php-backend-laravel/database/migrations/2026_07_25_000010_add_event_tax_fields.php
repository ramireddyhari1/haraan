<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-event, admin-set tax (e.g. GST). Type is none | flat | percent; value is a
 * flat ₹ amount or a percentage of the ticket subtotal — mirroring the convenience
 * fee. Defaults to 'none' so no event charges tax unless an admin turns it on.
 *
 * NOTE: this is the storage + admin field only. Tax is NOT yet applied at checkout;
 * wiring it into the payable total (BookingService + order summaries) is a separate,
 * deliberate step. Until then these columns are informational.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('tax_type')->default('none')->after('convenience_fee_value');
            $table->decimal('tax_value', 10, 2)->default(0)->after('tax_type');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn(['tax_type', 'tax_value']);
        });
    }
};
