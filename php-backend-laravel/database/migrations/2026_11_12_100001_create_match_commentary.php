<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Live commentary — the scorer talks, everyone on the match detail screen can listen.
 *
 * The voice itself never touches this database: it goes phone → LiveKit → listeners. These
 * rows are the record of who went on air, for how long, to how many, and how it ended, plus
 * the reports viewers file against a commentator. /control reads both to take someone off air.
 *
 * Going on air is a Hero feature (`matches.live_commentary`); listening is free and needs no
 * plan, so it has no entitlement row.
 */
return new class extends Migration
{
    private const KEY = 'matches.live_commentary';

    private const PLANS = ['free' => false, 'pro' => false, 'hero' => true];

    public function up(): void
    {
        Schema::create('match_commentary_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('match_id')->constrained('live_matches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The LiveKit room. One room per session, so a fresh start never inherits listeners
            // or a stale publisher from the last one.
            $table->string('room', 80)->unique();
            $table->timestamp('started_at');
            // The commentator's phone beats while on air. A phone that dies stops beating and
            // the session falls off air on its own (MatchCommentary::STALE_AFTER_SECONDS).
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->boolean('muted')->default(false);
            $table->timestamp('ended_at')->nullable();
            // commentator | match_finished | time_limit | silence | admin | reports | stale
            $table->string('ended_reason', 24)->nullable();
            $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('listeners_now')->default(0);
            $table->unsignedInteger('peak_listeners')->default(0);
            $table->timestamps();

            $table->index(['match_id', 'ended_at']);
            $table->index('ended_at');
        });

        Schema::create('match_commentary_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('session_id')->constrained('match_commentary_sessions')->cascadeOnDelete();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            // Guests can report too; one report per listener per session either way.
            $table->string('reporter_key', 64);
            $table->string('reason', 24);
            $table->string('details', 300)->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'reporter_key']);
        });

        $now = now();
        if (! DB::table('member_features')->where('key', self::KEY)->exists()) {
            DB::table('member_features')->insert([
                'key' => self::KEY,
                'name' => 'Live commentary',
                'description' => 'Call your match live from the scorer. Everyone watching hears you, ball by ball. Listening is free for everyone.',
                'type' => 'boolean', 'unit' => null, 'sort' => 95, 'is_visible' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $plans = DB::table('member_plans')->pluck('id', 'code');
        foreach (self::PLANS as $code => $enabled) {
            $planId = $plans[$code] ?? null;
            if ($planId === null) {
                continue;
            }
            $exists = DB::table('member_plan_entitlements')
                ->where('plan_id', $planId)->where('feature_key', self::KEY)->exists();
            if (! $exists) {
                DB::table('member_plan_entitlements')->insert([
                    'plan_id' => $planId, 'feature_key' => self::KEY, 'enabled' => $enabled,
                    'limit_value' => null, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('member_plan_entitlements')->where('feature_key', self::KEY)->delete();
        DB::table('member_entitlement_overrides')->where('feature_key', self::KEY)->delete();
        DB::table('member_features')->where('key', self::KEY)->delete();
        Schema::dropIfExists('match_commentary_reports');
        Schema::dropIfExists('match_commentary_sessions');
    }
};
