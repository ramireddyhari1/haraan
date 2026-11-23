<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The shortest booking a customer may make, in minutes. Null = one slot. Lets a 30-minute
 * venue say "first hour, then 30-minute steps" (60) instead of selling single 30-minute slots.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('venues', 'min_booking_minutes')) {
            Schema::table('venues', function (Blueprint $table): void {
                $table->unsignedSmallInteger('min_booking_minutes')->nullable()->after('slot_minutes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('venues', 'min_booking_minutes')) {
            Schema::table('venues', function (Blueprint $table): void {
                $table->dropColumn('min_booking_minutes');
            });
        }
    }
};
