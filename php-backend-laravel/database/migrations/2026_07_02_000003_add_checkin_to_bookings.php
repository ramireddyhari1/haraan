<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            // How many of this booking's `quantity` tickets have arrived.
            // Supports partial check-in for group bookings.
            $table->unsignedInteger('checked_in_count')->default(0)->after('status');
            // First check-in time (arrival curve); null = nobody checked in yet.
            $table->timestamp('checked_in_at')->nullable()->after('checked_in_count');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['checked_in_count', 'checked_in_at']);
        });
    }
};
