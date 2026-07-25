<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unified, admin-set list of order fees (Convenience fee, Gateway fee, …). Each
 * entry is { label, type: flat|percent, value }; all are charged on top of the
 * ticket subtotal at checkout and shown as their own lines to buyers. This
 * supersedes the single convenience_fee_* columns, whose value is migrated in as
 * the first fee row. The old columns are left in place (unused) for rollback.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->json('fees')->nullable()->after('convenience_fee_label');
        });

        // Fold any existing single convenience fee into the new list so nothing
        // that was already being charged silently drops to zero.
        foreach (DB::table('events')->whereIn('convenience_fee_type', ['flat', 'percent'])->get() as $e) {
            if ((float) $e->convenience_fee_value <= 0) {
                continue;
            }

            DB::table('events')->where('id', $e->id)->update([
                'fees' => json_encode([[
                    'label' => $e->convenience_fee_label ?: 'Convenience fee',
                    'type'  => $e->convenience_fee_type,
                    'value' => (float) $e->convenience_fee_value,
                ]]),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('fees');
        });
    }
};
