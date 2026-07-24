<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billing (automation Phase 2b) — the money side of plans.
 *
 *  - partner_plans.razorpay_plan_id links our tier to a plan created in the
 *    Razorpay dashboard. Without it a tier can't be subscribed to, only assigned
 *    by an admin.
 *  - credit_packs: prepaid conversation top-ups, sold as ONE-TIME orders. Not a
 *    metered recurring debit, because RBI e-mandates fix a maximum amount and
 *    require 24h pre-debit notice — variable card billing in India is a support
 *    nightmare. This reuses the order flow already proven in RazorpayGateway.
 *  - partner_credits.reference gets a UNIQUE index: it holds the Razorpay
 *    payment id, and it is the only thing standing between a retried webhook
 *    and a partner being granted the same pack twice.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('partner_plans', function (Blueprint $table) {
            $table->string('razorpay_plan_id')->nullable()->after('features');
        });

        Schema::create('credit_packs', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedInteger('conversations');
            $table->unsignedInteger('price_inr');
            $table->boolean('is_active')->default(false);
            $table->unsignedSmallInteger('sort')->default(100);
            $table->timestamps();
        });

        // A payment can only ever grant credits once, no matter how many times
        // Razorpay redelivers the webhook or the browser replays the callback.
        Schema::table('partner_credits', function (Blueprint $table) {
            $table->unique('reference');
        });
    }

    public function down(): void
    {
        Schema::table('partner_credits', function (Blueprint $table) {
            $table->dropUnique(['reference']);
        });

        Schema::dropIfExists('credit_packs');

        Schema::table('partner_plans', function (Blueprint $table) {
            $table->dropColumn('razorpay_plan_id');
        });
    }
};
