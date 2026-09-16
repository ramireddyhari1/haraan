<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Member (player / fan) plans — Free, Pro, Hero. Entirely separate from the partner
 * billing tables: a member subscription never reads partner_plans and the partner
 * webhook never reads these. See docs/member-subscriptions-design.md.
 *
 *  - member_plans / member_plan_prices: the catalogue. A price is immutable once it has
 *    a Razorpay plan behind it (Razorpay plans can't be edited), so a price change is a
 *    new row and existing subscribers keep what they signed up for.
 *  - member_features / member_plan_entitlements: what each plan is worth. Keys and types
 *    are owned by code (App\Support\Membership\MemberFeature) because code enforces them;
 *    the values are owned by /control.
 *  - member_subscriptions: one row per Razorpay subscription or admin grant. Status mirrors
 *    Razorpay; periods come from Razorpay's own entity, never computed here.
 *  - member_payments / member_subscription_events: the money and the audit trail. Their
 *    UNIQUE provider ids are what make a redelivered webhook harmless.
 *  - member_usage_counters: monthly quotas, one row per user/feature/period, row-locked
 *    when consumed.
 *
 * The catalogue rows are inserted here rather than by a seeder so a deploy's `migrate`
 * leaves a working Free plan. Paid prices ship INACTIVE and unlinked: nothing is
 * purchasable until an admin creates the Razorpay plan from /control.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('member_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 60);
            $table->string('tagline', 120)->nullable();
            $table->text('description')->nullable();
            // Ordering of value: an upgrade is a move to a higher rank.
            $table->unsignedSmallInteger('rank')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(100);
            $table->timestamps();
        });

        Schema::create('member_plan_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('member_plans')->cascadeOnDelete();
            $table->string('interval', 10);                     // month | year
            $table->unsignedInteger('amount_paise');
            $table->string('currency', 3)->default('INR');
            $table->string('razorpay_plan_id')->nullable()->unique();
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->index(['plan_id', 'interval', 'is_active']);
        });

        Schema::create('member_features', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('name', 80);
            $table->string('description', 255)->nullable();
            $table->string('type', 10);                         // boolean | limit | quota
            $table->string('unit', 30)->nullable();              // "reviews / month"
            $table->unsignedSmallInteger('sort')->default(100);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        Schema::create('member_plan_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('member_plans')->cascadeOnDelete();
            $table->string('feature_key', 60);
            $table->boolean('enabled')->default(false);
            $table->unsignedInteger('limit_value')->nullable(); // null = unlimited
            $table->timestamps();

            $table->unique(['plan_id', 'feature_key']);
        });

        Schema::create('member_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('member_plans');
            $table->foreignId('price_id')->nullable()->constrained('member_plan_prices')->nullOnDelete();
            $table->string('provider', 20);                     // razorpay | admin
            $table->string('status', 20);
            $table->string('provider_subscription_id')->nullable()->unique();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('starts_at')->nullable();          // scheduled start (downgrades)
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('replaces_subscription_id')->nullable()->constrained('member_subscriptions')->nullOnDelete();
            $table->string('change_type', 12)->default('new');   // new | upgrade | downgrade | interval
            $table->unsignedInteger('paid_count')->default(0);
            $table->timestamp('last_event_at')->nullable();
            $table->timestamp('checkout_expires_at')->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'current_period_end']);
        });

        Schema::create('member_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('member_subscriptions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider_payment_id')->unique();
            $table->string('provider_invoice_id')->nullable();
            $table->unsignedInteger('amount_paise');
            $table->string('currency', 3)->default('INR');
            $table->string('status', 20);                        // captured | failed | refunded
            $table->string('method', 30)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('member_subscription_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->nullable()->constrained('member_subscriptions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('provider_event_id')->nullable()->unique();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['subscription_id', 'id']);
        });

        Schema::create('member_entitlement_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key', 60);
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('limit_value')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('reason', 255);
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'feature_key']);
        });

        Schema::create('member_usage_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key', 60);
            $table->date('period_start');
            $table->unsignedInteger('used')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'feature_key', 'period_start']);
        });

        $this->seedCatalogue();
    }

    private function seedCatalogue(): void
    {
        $now = now();

        $features = [
            ['ads.hidden', 'No ads', 'Browse matches, events and venues without sponsored placements.', 'boolean', null, 10],
            ['ai.career_read', 'AI career read', 'Haraan AI writes a read on your game from your own figures.', 'boolean', null, 20],
            ['ai.delivery_review', 'AI delivery reviews', 'Frame-by-frame LBW and delivery reviews from your camera clips.', 'quota', 'reviews / month', 30],
            ['tournaments.active_hosted', 'Tournaments you host', 'Tournaments you can run at the same time.', 'limit', 'at a time', 40],
            ['matches.camera_angles', 'Camera angles per match', 'Phones you can pair as review cameras on one match.', 'limit', 'angles', 50],
            ['profile.member_badge', 'Member badge', 'A Pro or Hero mark on your player profile.', 'boolean', null, 60],
            ['support.priority', 'Priority support', 'Your support conversations go to the front of the queue.', 'boolean', null, 70],
        ];

        foreach ($features as [$key, $name, $description, $type, $unit, $sort]) {
            DB::table('member_features')->insert([
                'key' => $key, 'name' => $name, 'description' => $description, 'type' => $type,
                'unit' => $unit, 'sort' => $sort, 'is_visible' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $plans = [
            'free' => ['Free', 'Everything you need to play and follow.', 0, true, true, 10],
            'pro' => ['Pro', 'For the player who wants to see their game.', 10, false, true, 20],
            'hero' => ['Hero', 'For captains, organisers and the everyday grinder.', 20, false, true, 30],
        ];

        // [enabled, limit] — limit null = unlimited; booleans ignore limit.
        $matrix = [
            'ads.hidden' => ['free' => [false, null], 'pro' => [true, null], 'hero' => [true, null]],
            'ai.career_read' => ['free' => [false, null], 'pro' => [true, null], 'hero' => [true, null]],
            'ai.delivery_review' => ['free' => [true, 2], 'pro' => [true, 20], 'hero' => [true, null]],
            'tournaments.active_hosted' => ['free' => [true, 1], 'pro' => [true, 3], 'hero' => [true, null]],
            'matches.camera_angles' => ['free' => [true, 1], 'pro' => [true, 2], 'hero' => [true, 2]],
            'profile.member_badge' => ['free' => [false, null], 'pro' => [true, null], 'hero' => [true, null]],
            'support.priority' => ['free' => [false, null], 'pro' => [false, null], 'hero' => [true, null]],
        ];

        // Proposed launch prices. Inactive and unlinked until /control creates the Razorpay plan.
        $prices = [
            'pro' => [['month', 9900], ['year', 99900]],
            'hero' => [['month', 24900], ['year', 249900]],
        ];

        foreach ($plans as $code => [$name, $tagline, $rank, $default, $active, $sort]) {
            $planId = DB::table('member_plans')->insertGetId([
                'code' => $code, 'name' => $name, 'tagline' => $tagline, 'rank' => $rank,
                'is_default' => $default, 'is_active' => $active, 'sort' => $sort,
                'created_at' => $now, 'updated_at' => $now,
            ]);

            foreach ($matrix as $key => $values) {
                [$enabled, $limit] = $values[$code];
                DB::table('member_plan_entitlements')->insert([
                    'plan_id' => $planId, 'feature_key' => $key, 'enabled' => $enabled,
                    'limit_value' => $limit, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            foreach ($prices[$code] ?? [] as [$interval, $amount]) {
                DB::table('member_plan_prices')->insert([
                    'plan_id' => $planId, 'interval' => $interval, 'amount_paise' => $amount,
                    'currency' => 'INR', 'is_active' => false, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('member_usage_counters');
        Schema::dropIfExists('member_entitlement_overrides');
        Schema::dropIfExists('member_subscription_events');
        Schema::dropIfExists('member_payments');
        Schema::dropIfExists('member_subscriptions');
        Schema::dropIfExists('member_plan_entitlements');
        Schema::dropIfExists('member_features');
        Schema::dropIfExists('member_plan_prices');
        Schema::dropIfExists('member_plans');
    }
};
