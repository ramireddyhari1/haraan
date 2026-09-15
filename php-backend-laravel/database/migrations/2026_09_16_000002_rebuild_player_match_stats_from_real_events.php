<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One honest per-match, per-player stats table for every sport, and per-sport careers.
 *
 * `player_match_stats` was written only by PlayerStatsService::freezeMatchStats(), which
 * padded squads with IPL names and filled batters' runs with mt_rand. Every existing row came
 * from that generator, so none of them is a fact — they are cleared here and rebuilt by
 * `php artisan stats:rebuild` from the real logs (match_actions for cricket, match_events for
 * everything else).
 *
 * The cricket columns stay (the web profile reads `runs`), and the table grows the columns
 * every sport needs:
 *
 *   sport       which sport's stats `stats` holds
 *   side        home | away
 *   player_key  the row's identity inside the match: the registered player_id, or
 *               "guest:<side>:<name>" for a typed-in player with no account
 *   user_id     the account, when there is one
 *   played      true for every squad member; stats alone don't decide who played
 *   result      win | loss | draw | null (match not finished)
 *   stats       the sport's own figures (goals/assists; pts/reb/ast; raid points; aces…)
 *
 * `player_sport_careers` is the career rollup per player per sport, rebuilt incrementally for
 * just the players of a match when it completes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The fabricated rows are set aside, not destroyed: if anything about the rebuild ever
        // needs to be checked against what was there before, the old table is still readable.
        // Drop `player_match_stats_legacy` once the rebuilt careers have been reviewed.
        if (! Schema::hasTable('player_match_stats_legacy')) {
            Schema::create('player_match_stats_legacy', function (Blueprint $table): void {
                $table->unsignedBigInteger('id');
                $table->unsignedBigInteger('match_id');
                $table->string('player_id')->nullable();
                $table->string('player_name')->nullable();
                $table->integer('runs')->default(0);
                $table->integer('balls')->default(0);
                $table->integer('wickets')->default(0);
                $table->string('overs_bowled')->default('0.0');
                $table->integer('runs_conceded')->default(0);
                $table->timestamps();
            });
            DB::statement('INSERT INTO player_match_stats_legacy (id, match_id, player_id, player_name, runs, balls, wickets, overs_bowled, runs_conceded, created_at, updated_at)
                SELECT id, match_id, player_id, player_name, runs, balls, wickets, overs_bowled, runs_conceded, created_at, updated_at FROM player_match_stats');
        }

        DB::table('player_match_stats')->delete();

        Schema::table('player_match_stats', function (Blueprint $table): void {
            $table->string('player_id')->nullable()->change();
            $table->string('sport', 20)->default('cricket')->after('match_id');
            $table->string('side', 5)->nullable()->after('sport');
            $table->string('player_key', 160)->nullable()->after('side');
            $table->foreignId('user_id')->nullable()->after('player_id')->constrained('users')->nullOnDelete();
            $table->boolean('played')->default(true)->after('player_name');
            $table->string('result', 5)->nullable()->after('played');
            $table->json('stats')->nullable()->after('runs_conceded');

            $table->unique(['match_id', 'player_key']);
            $table->index(['player_id', 'sport']);
        });

        Schema::create('player_sport_careers', function (Blueprint $table): void {
            $table->id();
            $table->string('player_id')->index();
            $table->string('sport', 20);
            $table->unsignedInteger('matches')->default(0);
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('losses')->default(0);
            $table->unsignedInteger('draws')->default(0);
            $table->json('totals')->nullable();
            $table->json('bests')->nullable();
            $table->timestamp('last_match_at')->nullable();
            $table->timestamps();

            $table->unique(['player_id', 'sport']);
        });

        // "Finished" gets one marker. Cricket's scorer ends a chase by writing the result INTO
        // `status` ("KDW won by 3 wickets"), so every `lower(status) = 'completed'` query —
        // careers, form, ground records — silently skipped every naturally finished cricket
        // match. `completed_at` is set by whichever path ends a match, and backfilled here.
        Schema::table('live_matches', function (Blueprint $table): void {
            $table->timestamp('completed_at')->nullable()->after('status');
            $table->index('status');
            $table->index(['sport', 'status']);
            $table->index('completed_at');
        });

        DB::table('live_matches')
            ->whereNull('completed_at')
            ->where(function ($q): void {
                $q->whereRaw('lower(status) in (?, ?, ?)', ['completed', 'finished', 'match tied'])
                    ->orWhereRaw('lower(status) like ?', ['% won by %']);
            })
            ->update(['completed_at' => DB::raw('coalesce(updated_at, created_at)')]);
    }

    public function down(): void
    {
        Schema::table('live_matches', function (Blueprint $table): void {
            $table->dropIndex(['completed_at']);
            $table->dropIndex(['sport', 'status']);
            $table->dropIndex(['status']);
            $table->dropColumn('completed_at');
        });

        Schema::dropIfExists('player_sport_careers');
        // The legacy copy is deliberately kept on rollback; it is the only record of the old rows.

        Schema::table('player_match_stats', function (Blueprint $table): void {
            $table->dropIndex(['player_id', 'sport']);
            $table->dropUnique(['match_id', 'player_key']);
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['sport', 'side', 'player_key', 'played', 'result', 'stats']);
        });
    }
};
