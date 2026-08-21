<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which sport each XP award came from.
 *
 * The District and State boards had no sport filter, and the honest reason was not the UI:
 * the ledger recorded how much XP a player earned and in which month, but never at what.
 * So "top volleyball players in YSR Kadapa" was a question the data could not answer, and a
 * filter chip would have been a control that quietly did nothing.
 *
 * Denormalised from `live_matches.sport` rather than joined at query time: a leaderboard is
 * a GROUP BY over the whole month, and every existing row is backfilled below, so the two
 * can never disagree unless a match changes sport after it settles — which nothing does.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_xp_ledger', function (Blueprint $table): void {
            $table->string('sport', 20)->default('cricket')->after('match_id')->index();
        });

        // Backfill from the matches the rows came from. Everything created before the sport
        // column existed IS cricket, which is what the default already says.
        DB::statement(
            "update match_xp_ledger set sport = coalesce((
                select lower(m.sport) from live_matches m where m.id = match_xp_ledger.match_id
            ), 'cricket')"
        );
    }

    public function down(): void
    {
        Schema::table('match_xp_ledger', function (Blueprint $table): void {
            $table->dropColumn('sport');
        });
    }
};
