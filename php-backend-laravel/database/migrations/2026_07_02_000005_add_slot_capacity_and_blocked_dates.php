<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // How many bookings a slot accepts per date (courts/turfs/spots).
        // Existing slots default to 1 to preserve today's one-booking behaviour.
        Schema::table('venue_slots', function (Blueprint $table): void {
            $table->unsignedInteger('capacity')->default(1)->after('time');
        });

        // Owner-blocked dates (holidays / maintenance) — no bookings taken that day.
        Schema::create('venue_blocked_dates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['venue_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_blocked_dates');

        Schema::table('venue_slots', function (Blueprint $table): void {
            $table->dropColumn('capacity');
        });
    }
};
