<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A court's own daily hours ("the nets open 6 AM – 6 PM") inside the venue's hours.
 * Both null = the court follows the venue's hours, which is every court before this.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_courts', function (Blueprint $table): void {
            if (! Schema::hasColumn('venue_courts', 'open_time')) {
                $table->string('open_time', 5)->nullable();
            }
            if (! Schema::hasColumn('venue_courts', 'close_time')) {
                $table->string('close_time', 5)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('venue_courts', function (Blueprint $table): void {
            $table->dropColumn(['open_time', 'close_time']);
        });
    }
};
