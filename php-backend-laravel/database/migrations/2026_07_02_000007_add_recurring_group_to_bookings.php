<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            // Links the venue bookings created by one "book weekly" series so
            // they can be listed / cancelled together later.
            $table->string('recurring_group')->nullable()->after('slot_label');
            $table->index('recurring_group');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex(['recurring_group']);
            $table->dropColumn('recurring_group');
        });
    }
};
