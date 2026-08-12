<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-set custom label for the convenience fee, shown to buyers on the order
 * summary in place of the default "Convenience fee" wording (e.g. "Booking fee",
 * "Platform charge"). Nullable — when empty the default label is used.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('convenience_fee_label')->nullable()->after('convenience_fee_value');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('convenience_fee_label');
        });
    }
};
