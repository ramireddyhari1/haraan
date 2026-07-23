<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Village / town / area where the match was played — finer than the stamped
 * district. Optional, captured at creation (typed or auto-filled from GPS), and
 * shown with the venue on the live card for true local identity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_matches', function (Blueprint $table) {
            $table->string('locality')->nullable()->after('district');
        });
    }

    public function down(): void
    {
        Schema::table('live_matches', function (Blueprint $table) {
            $table->dropColumn('locality');
        });
    }
};
