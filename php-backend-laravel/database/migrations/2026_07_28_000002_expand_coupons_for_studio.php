<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expand `coupons` for the Coupon Studio (event create/edit).
 *
 * Adds the richer coupon model the studio authors. The money-critical fields
 * (type / max_discount / min_order / per_customer_limit / expires_at) are
 * enforced at checkout; the rest (eligibility + phone list, date/time
 * restrictions, multi_event) are persisted now and enforced as a fast-follow,
 * so the UI is real, not decorative. Existing rows read as a flat, non-expiring,
 * everyone-eligible coupon — identical to today's behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table): void {
            if (! Schema::hasColumn('coupons', 'type')) {
                $table->string('type')->default('fixed')->after('code'); // 'fixed' | 'percent'
            }
            if (! Schema::hasColumn('coupons', 'max_discount')) {
                $table->decimal('max_discount', 10, 2)->nullable()->after('discount'); // cap for percent
            }
            if (! Schema::hasColumn('coupons', 'min_order')) {
                $table->decimal('min_order', 10, 2)->default(0)->after('max_discount');
            }
            if (! Schema::hasColumn('coupons', 'per_customer_limit')) {
                $table->integer('per_customer_limit')->nullable()->after('max_uses');
            }
            if (! Schema::hasColumn('coupons', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('per_customer_limit');
            }
            if (! Schema::hasColumn('coupons', 'multi_event')) {
                $table->boolean('multi_event')->default(false)->after('expires_at');
            }
            // ---- persisted now, enforced as a fast-follow ----
            if (! Schema::hasColumn('coupons', 'eligibility')) {
                $table->string('eligibility')->default('all')->after('multi_event'); // 'all' | 'phones'
            }
            if (! Schema::hasColumn('coupons', 'phone_numbers')) {
                $table->json('phone_numbers')->nullable()->after('eligibility');
            }
            if (! Schema::hasColumn('coupons', 'restrict_dates')) {
                $table->boolean('restrict_dates')->default(false)->after('phone_numbers');
            }
            if (! Schema::hasColumn('coupons', 'valid_dates')) {
                $table->json('valid_dates')->nullable()->after('restrict_dates');
            }
            if (! Schema::hasColumn('coupons', 'restrict_times')) {
                $table->boolean('restrict_times')->default(false)->after('valid_dates');
            }
            if (! Schema::hasColumn('coupons', 'valid_times')) {
                $table->json('valid_times')->nullable()->after('restrict_times');
            }
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table): void {
            foreach ([
                'type', 'max_discount', 'min_order', 'per_customer_limit', 'expires_at', 'multi_event',
                'eligibility', 'phone_numbers', 'restrict_dates', 'valid_dates', 'restrict_times', 'valid_times',
            ] as $col) {
                if (Schema::hasColumn('coupons', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
