<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Post-match rewards (docs/post-match-rewards-design.md).
 *
 *  - reward_sponsors / reward_programs / reward_rules: what can be won, configured in /control.
 *  - reward_code_pools / reward_codes: sponsor-supplied unique codes, encrypted at rest.
 *  - reward_grants: the reward ledger. One row per thing a player was given, with an explicit
 *    state (locked → available → claimed → redeemed, or expired / revoked) and a unique
 *    dedupe_key so re-running the engine can never grant twice.
 *  - bonus_xp_ledger: Bonus XP. Deliberately NOT match_xp_ledger — nothing here is read by
 *    PlayerXpLedgerService, users.casual_xp/ranked_xp or any leaderboard.
 *  - badge_definitions / player_badges: badges are persisted with the moment they unlocked.
 *  - player_streaks: the weekly play streak.
 *  - rewarded_ad_sessions: AdMob rewarded-ad sessions, verified server-side only.
 *  - coupons gain an owner so a reward coupon works for one account only.
 *  - notifications gain a source so reward notices stay out of the admin composer list.
 *  - Four member features (rewards.*) with launch values per plan.
 */
return new class extends Migration
{
    /** @var array<string, array{type: string, name: string, description: string, unit: ?string, sort: int, plans: array<string, array{0: bool, 1: int|null}>}> */
    private const FEATURES = [
        'rewards.ad_unlock_skip' => [
            'type' => 'boolean', 'name' => 'Unlock rewards without ads',
            'description' => 'Rewards that normally need a short video unlock without one.',
            'unit' => null, 'sort' => 100,
            'plans' => ['free' => [false, null], 'pro' => [true, null], 'hero' => [true, null]],
        ],
        'rewards.member_programs' => [
            'type' => 'boolean', 'name' => 'Member-only rewards',
            'description' => 'Post-match rewards reserved for members.',
            'unit' => null, 'sort' => 110,
            'plans' => ['free' => [false, null], 'pro' => [true, null], 'hero' => [true, null]],
        ],
        'rewards.offers_per_match' => [
            'type' => 'limit', 'name' => 'Partner offers per match',
            'description' => 'How many sponsored rewards you can win from one match.',
            'unit' => 'offers', 'sort' => 120,
            'plans' => ['free' => [true, 1], 'pro' => [true, 2], 'hero' => [true, 3]],
        ],
        'rewards.bonus_xp_boost' => [
            'type' => 'limit', 'name' => 'Bonus XP boost',
            'description' => 'Extra Bonus XP on every reward. Never counts toward rankings.',
            'unit' => '% extra', 'sort' => 130,
            'plans' => ['free' => [false, 0], 'pro' => [true, 10], 'hero' => [true, 25]],
        ],
    ];

    public function up(): void
    {
        Schema::create('reward_sponsors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('category', 20)->default('other');      // payments|food|sports_brand|ott|other
            $table->string('logo')->nullable();
            $table->string('brand_color', 9)->nullable();
            $table->string('website_url')->nullable();
            $table->string('contact_name', 120)->nullable();
            $table->string('contact_email', 190)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('reward_programs', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 12)->default('haraan');           // haraan|sponsored
            $table->foreignId('sponsor_id')->nullable()->constrained('reward_sponsors')->nullOnDelete();
            $table->string('name', 120);
            $table->string('status', 10)->default('draft');          // draft|live|paused|ended
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->integer('priority')->default(100);
            $table->json('sports')->nullable();                       // null = every sport
            $table->json('match_types')->nullable();                  // null = every type
            $table->boolean('members_only')->default(false);
            $table->unsignedInteger('budget_total')->nullable();      // grants; null = unlimited
            $table->unsignedInteger('budget_daily')->nullable();
            $table->unsignedInteger('grants_count')->default(0);
            $table->string('headline', 120)->nullable();
            $table->string('description', 500)->nullable();
            $table->string('disclosure', 300)->nullable();
            $table->string('terms_url')->nullable();
            $table->string('card_image')->nullable();
            $table->string('brand_color', 9)->nullable();
            $table->timestamps();
            $table->index(['status', 'priority']);
        });

        Schema::create('reward_program_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('reward_programs')->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('grants')->default(0);
            $table->unique(['program_id', 'day']);
        });

        Schema::create('reward_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('reward_programs')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('trigger', 20);                            // match_completed|match_settled
            $table->json('conditions')->nullable();
            $table->string('reward_type', 20);                        // bonus_xp|haraan_coupon|sponsor_code|membership_trial|offer_link
            $table->json('payload')->nullable();
            $table->string('unlock_method', 12)->default('auto');     // auto|rewarded_ad
            $table->unsignedInteger('per_user_daily_cap')->nullable();
            $table->unsignedInteger('per_user_total_cap')->nullable();
            $table->unsignedSmallInteger('expires_after_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort')->default(100);
            $table->timestamps();
        });

        Schema::create('reward_code_pools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sponsor_id')->nullable()->constrained('reward_sponsors')->nullOnDelete();
            $table->string('name', 120);
            $table->string('instructions', 300)->nullable();          // "Apply at checkout on the brand's app"
            $table->unsignedInteger('low_stock_threshold')->default(20);
            $table->timestamps();
        });

        Schema::create('reward_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pool_id')->constrained('reward_code_pools')->cascadeOnDelete();
            $table->text('code');                                     // encrypted cast
            $table->string('code_hash', 64);                          // HMAC, for de-duplicating imports
            $table->unsignedBigInteger('grant_id')->nullable()->unique();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();
            $table->unique(['pool_id', 'code_hash']);
            $table->index(['pool_id', 'grant_id']);
        });

        Schema::create('reward_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('match_id')->nullable()->index();
            $table->foreignId('program_id')->nullable()->constrained('reward_programs')->nullOnDelete();
            $table->foreignId('rule_id')->nullable()->constrained('reward_rules')->nullOnDelete();
            $table->string('source', 12);                             // haraan|sponsored
            $table->string('type', 20);                               // + badge|streak (records)
            $table->string('status', 12);                             // locked|available|claimed|redeemed|expired|revoked
            $table->boolean('needs_verification')->default(false);
            $table->boolean('needs_ad')->default(false);
            $table->string('dedupe_key', 190)->unique();
            $table->string('title', 160);
            $table->string('description', 500)->nullable();
            $table->json('value')->nullable();
            $table->integer('bonus_xp')->nullable();
            $table->unsignedBigInteger('coupon_id')->nullable();
            $table->unsignedBigInteger('reward_code_id')->nullable();
            $table->json('override_ids')->nullable();
            $table->string('status_reason', 60)->nullable();
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedBigInteger('revoked_by')->nullable();
            $table->timestamp('expiry_warned_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['status', 'expires_at']);
            $table->index(['rule_id', 'user_id', 'created_at']);
        });

        Schema::create('bonus_xp_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('amount');                                // negative = reversal
            $table->string('reason', 120);
            $table->unsignedBigInteger('grant_id')->nullable()->index();
            $table->unsignedBigInteger('match_id')->nullable()->index();
            $table->string('dedupe_key', 190)->unique();
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('badge_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('name', 80);
            $table->string('description', 200)->nullable();
            $table->string('icon', 40)->default('EmojiEvents');       // vector glyph name the app maps
            $table->string('tier', 10)->default('bronze');            // bronze|silver|gold
            $table->string('metric', 40);                             // BadgeMetrics registry
            $table->unsignedInteger('threshold')->default(1);
            $table->boolean('show_progress')->default(true);
            $table->unsignedInteger('bonus_xp')->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('sort')->default(100);
            $table->timestamps();
        });

        Schema::create('player_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('badge_key', 40);
            $table->unsignedBigInteger('match_id')->nullable();
            $table->timestamp('unlocked_at');
            $table->timestamp('celebrated_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'badge_key']);
            $table->index(['user_id', 'match_id']);
        });

        Schema::create('player_streaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 20)->default('play_week');
            $table->unsignedInteger('current')->default(0);
            $table->unsignedInteger('best')->default(0);
            $table->string('last_period', 10)->nullable();            // ISO week, e.g. 2026-W38
            $table->unsignedBigInteger('last_match_id')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'kind']);
        });

        Schema::create('rewarded_ad_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('nonce', 64)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('grant_id')->constrained('reward_grants')->cascadeOnDelete();
            $table->string('provider', 12)->default('admob');
            $table->string('ad_unit', 120)->nullable();
            $table->string('status', 10)->default('pending');         // pending|verified|expired|rejected
            $table->string('transaction_id', 120)->nullable()->unique();
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status', 'verified_at']);
        });

        Schema::create('reward_match_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('match_id');
            $table->timestamp('seen_at');
            $table->unique(['user_id', 'match_id']);
        });

        Schema::create('reward_evaluations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('match_id');
            $table->string('trigger', 20);
            $table->timestamp('evaluated_at');
            $table->unique(['match_id', 'trigger']);
        });

        Schema::table('users', function (Blueprint $table) {
            // Set once a player's pre-existing badges were recorded without celebrating them,
            // so the first match after launch doesn't announce ten old badges at once.
            $table->timestamp('rewards_baselined_at')->nullable();
        });

        Schema::table('coupons', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_user_id')->nullable()->index();
            $table->string('source', 12)->default('manual');
            $table->unsignedBigInteger('reward_grant_id')->nullable();
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->string('source', 12)->default('composer')->index();
        });

        $this->seedMemberFeatures();
        $this->seedBadges();
    }

    private function seedMemberFeatures(): void
    {
        $now = now();
        $plans = DB::table('member_plans')->pluck('id', 'code');

        foreach (self::FEATURES as $key => $f) {
            if (! DB::table('member_features')->where('key', $key)->exists()) {
                DB::table('member_features')->insert([
                    'key' => $key, 'name' => $f['name'], 'description' => $f['description'],
                    'type' => $f['type'], 'unit' => $f['unit'], 'sort' => $f['sort'], 'is_visible' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            foreach ($f['plans'] as $code => [$enabled, $limit]) {
                $planId = $plans[$code] ?? null;
                if ($planId === null || DB::table('member_plan_entitlements')->where('plan_id', $planId)->where('feature_key', $key)->exists()) {
                    continue;
                }
                DB::table('member_plan_entitlements')->insert([
                    'plan_id' => $planId, 'feature_key' => $key, 'enabled' => $enabled,
                    'limit_value' => $limit, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    /** The ten badges the profile always showed (same keys, icons, tiers), plus two streak badges. */
    private function seedBadges(): void
    {
        $now = now();
        $rows = [
            ['first_match', 'First Match', 'SportsCricket', 'bronze', 'matches_played', 1, true, 10],
            ['first_win', 'First Win', 'EmojiEvents', 'bronze', 'wins', 1, false, 20],
            ['fifty', 'Half Century', 'Star', 'silver', 'high_score', 50, true, 30],
            ['century', 'First Century', 'WorkspacePremium', 'gold', 'high_score', 100, true, 40],
            ['mom', 'Man of the Match', 'MilitaryTech', 'silver', 'player_of_match', 1, false, 50],
            ['mvp5', 'MVP x5', 'MilitaryTech', 'gold', 'player_of_match', 5, true, 60],
            ['streak5', '5-Win Streak', 'Whatshot', 'gold', 'best_win_streak', 5, true, 70],
            ['veteran', '10 Matches', 'Shield', 'silver', 'matches_played', 10, true, 80],
            ['top100', 'District Top 100', 'TrendingUp', 'bronze', 'district_rank', 100, false, 90],
            ['wkts50', '50 Wickets', 'SportsCricket', 'gold', 'career_wickets', 50, true, 100],
            ['week_streak4', '4-Week Streak', 'Whatshot', 'silver', 'play_streak_weeks', 4, true, 110],
            ['week_streak12', '12-Week Streak', 'Whatshot', 'gold', 'play_streak_weeks', 12, true, 120],
        ];

        foreach ($rows as [$key, $name, $icon, $tier, $metric, $threshold, $progress, $sort]) {
            if (DB::table('badge_definitions')->where('key', $key)->exists()) {
                continue;
            }
            DB::table('badge_definitions')->insert([
                'key' => $key, 'name' => $name, 'icon' => $icon, 'tier' => $tier, 'metric' => $metric,
                'threshold' => $threshold, 'show_progress' => $progress, 'bonus_xp' => 0,
                'is_active' => true, 'sort' => $sort, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $keys = array_keys(self::FEATURES);
        DB::table('member_plan_entitlements')->whereIn('feature_key', $keys)->delete();
        DB::table('member_entitlement_overrides')->whereIn('feature_key', $keys)->delete();
        DB::table('member_features')->whereIn('key', $keys)->delete();

        Schema::table('notifications', function (Blueprint $t) {
            $t->dropIndex(['source']);
            $t->dropColumn('source');
        });
        Schema::table('coupons', function (Blueprint $t) {
            $t->dropIndex(['owner_user_id']);
            $t->dropColumn(['owner_user_id', 'source', 'reward_grant_id']);
        });
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('rewards_baselined_at'));

        foreach (['reward_evaluations', 'reward_match_views', 'rewarded_ad_sessions', 'player_streaks', 'player_badges',
            'badge_definitions', 'bonus_xp_ledger', 'reward_grants', 'reward_codes', 'reward_code_pools', 'reward_rules',
            'reward_program_days', 'reward_programs', 'reward_sponsors'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
