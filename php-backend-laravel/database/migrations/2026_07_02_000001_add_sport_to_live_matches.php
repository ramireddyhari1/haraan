<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ActionBoard goes multi-sport. Until now every match was implicitly cricket;
 * this stamps each row with its sport so create/feed/detail can branch. Existing
 * rows backfill to 'cricket' — the only sport that had a real create flow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_matches', function (Blueprint $table) {
            $table->string('sport', 24)->default('cricket')->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('live_matches', function (Blueprint $table) {
            $table->dropColumn('sport');
        });
    }
};
