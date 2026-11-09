<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A partner's requested change to a verified settlement destination waits here for
 * Haraan finance instead of overwriting it (Platform rules → Settlement accounts).
 * Until it is approved, money keeps going to the verified account.
 *
 * pending_payload is stored encrypted (APP_KEY) — it holds a full account number.
 * pending_note keeps the reason the last request was turned down, for the partner.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('partner_payout_accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('partner_payout_accounts', 'pending_payload')) {
                $table->text('pending_payload')->nullable();
                $table->timestamp('pending_at')->nullable();
                $table->unsignedBigInteger('pending_by_id')->nullable();
                $table->string('pending_note', 240)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('partner_payout_accounts', function (Blueprint $table): void {
            $table->dropColumn(['pending_payload', 'pending_at', 'pending_by_id', 'pending_note']);
        });
    }
};
