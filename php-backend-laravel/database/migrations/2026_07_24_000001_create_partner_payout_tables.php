<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payout MVP: where a partner's money goes, and the record of it going there.
 *
 *  - partner_payout_accounts: one settlement destination per partner (the owner
 *    user id, i.e. User::effectivePartnerId()). Bank or UPI; the partner enters
 *    their own details, we mask them in the UI.
 *  - payout_batches: a single settlement to a partner covering many bookings —
 *    the RazorpayX / Stripe "payout" object. Created/marked paid by admin in
 *    /control for now; the partner only ever reads them.
 *
 * The per-booking `payouts` table stays as the reconciliation ledger; batches
 * are the partner-facing rollup.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('partner_payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id')->unique();
            $table->string('method')->default('upi');        // 'bank' | 'upi'
            $table->string('account_holder')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('ifsc_code')->nullable();
            $table->string('upi_vpa')->nullable();
            $table->timestamp('verified_at')->nullable();     // admin-verified destination
            $table->timestamps();

            $table->foreign('partner_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('payout_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('status')->default('processing');  // processing | paid | failed
            $table->string('method')->nullable();             // snapshot of how it was paid
            $table->string('reference')->nullable();          // UTR / transfer id
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('partner_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['partner_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_batches');
        Schema::dropIfExists('partner_payout_accounts');
    }
};
