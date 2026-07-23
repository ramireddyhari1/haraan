<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Run-of-show for an event — an admin-authored list of {time, title, note}
 * rows shown when a user taps the "Doors Open" card on the detail screen.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->json('schedule')->nullable()->after('good_to_know');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('schedule');
        });
    }
};
