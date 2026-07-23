<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ActionBoard is multi-sport (Cricket, Football, Badminton, Basketball, …). The player
     * profile gains a `primary_sport` plus a flexible `sport_attributes` JSON bag so each sport
     * carries its own fields (cricket role/styles, football position/foot, …) without a new
     * migration per sport. The legacy cricket columns stay for back-compat.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('primary_sport')->nullable()->after('nationality');
            $table->json('sport_attributes')->nullable()->after('primary_sport');
        });

        // Existing players with cricket fields filled in are, by definition, cricketers.
        DB::table('users')
            ->whereNull('primary_sport')
            ->whereNotNull('player_role')
            ->update(['primary_sport' => 'Cricket']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['primary_sport', 'sport_attributes']);
        });
    }
};
