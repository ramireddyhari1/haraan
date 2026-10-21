<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where the applicant wants their money: bank account and/or UPI ID. The account
 * number and UPI ID are stored encrypted (model casts) — hence `text`. On "Create
 * partner" these become the partner's settlement account via PayoutAccountEditor,
 * unverified until finance checks them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('partner_applications', 'payout_method')) {
            return;
        }

        Schema::table('partner_applications', function (Blueprint $table): void {
            $table->string('payout_method', 10)->nullable();
            $table->string('bank_name', 80)->nullable();
            $table->string('account_holder', 100)->nullable();
            $table->text('account_number')->nullable();
            $table->string('ifsc_code', 11)->nullable();
            $table->text('upi_vpa')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('partner_applications', function (Blueprint $table): void {
            $table->dropColumn(['payout_method', 'bank_name', 'account_holder', 'account_number', 'ifsc_code', 'upi_vpa']);
        });
    }
};
