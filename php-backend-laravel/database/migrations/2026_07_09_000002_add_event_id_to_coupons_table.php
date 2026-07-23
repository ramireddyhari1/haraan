<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table): void {
            // Null = global coupon (works on any event). Set = scoped to one event.
            $table->foreignId('event_id')->nullable()->after('id')
                ->constrained('events')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('event_id');
        });
    }
};
