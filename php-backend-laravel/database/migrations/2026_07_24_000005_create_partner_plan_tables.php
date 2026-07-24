<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partner plans and entitlements (automation Phase 2a) — what a partner is
 * allowed to do, and how much of it.
 *
 *  - partner_plans: the catalogue. `included_conversations` is the quota, and
 *    `features` is the capability list an entitlement check reads. Prices live
 *    here too but nothing charges yet (phase 2b).
 *  - partner_subscriptions: which plan a partner is on and whether it's healthy.
 *    `external_id` is where the Razorpay subscription id will land.
 *  - partner_credits: prepaid conversation top-ups. Overage is sold as packs
 *    rather than a variable recurring debit, because RBI e-mandates fix a
 *    maximum amount and require 24h pre-debit notice — metered card billing in
 *    India is a support nightmare. Grants are positive rows; consumption is
 *    derived from usage above the plan quota, so nothing double-counts.
 *
 * Note what ISN'T here: there is no "transactional messages" quota. Ticket
 * delivery and OTPs are never gated by a plan — a lapsed subscription must not
 * stop a customer receiving the ticket they paid for.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('partner_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();                  // starter | growth | pro
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedInteger('price_inr')->default(0);  // whole rupees, per month
            $table->unsignedInteger('included_conversations')->default(0);
            $table->json('features')->nullable();              // ['automations.journeys', …]
            $table->boolean('is_default')->default(false);     // what a partner has with no subscription
            $table->boolean('is_active')->default(true);       // visible for selection
            $table->unsignedSmallInteger('sort')->default(100);
            $table->timestamps();
        });

        Schema::create('partner_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id');
            $table->unsignedBigInteger('plan_id');
            // active | halted (payment failed) | cancelled | trialing
            $table->string('status')->default('active');
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('cancel_at')->nullable();
            $table->string('external_id')->nullable();         // Razorpay subscription id
            $table->string('source')->default('admin');        // admin | razorpay
            $table->text('note')->nullable();
            $table->timestamps();

            // One live subscription per partner; history is kept by leaving
            // cancelled rows in place, so this is an index rather than a unique.
            $table->index(['partner_id', 'status']);
            $table->foreign('partner_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('plan_id')->references('id')->on('partner_plans');
        });

        Schema::create('partner_credits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id');
            $table->unsignedInteger('conversations');          // how many this grant is worth
            $table->string('source')->default('grant');        // grant | purchase
            $table->string('reference')->nullable();           // Razorpay payment id, once purchasable
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index('partner_id');
            $table->foreign('partner_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_credits');
        Schema::dropIfExists('partner_subscriptions');
        Schema::dropIfExists('partner_plans');
    }
};
