<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who last changed a settlement destination and who verified it. Since 2026-10-09
 * the partner's Haraan manager and admins can edit it too, so the partner app has to
 * be able to say "changed by Priya (your Haraan manager)", never just "changed".
 *
 * updated_by_kind: partner | manager | haraan.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('partner_payout_accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('partner_payout_accounts', 'updated_by_id')) {
                $table->unsignedBigInteger('updated_by_id')->nullable();
                $table->string('updated_by_kind', 16)->nullable();
                $table->unsignedBigInteger('verified_by_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('partner_payout_accounts', function (Blueprint $table): void {
            $table->dropColumn(['updated_by_id', 'updated_by_kind', 'verified_by_id']);
        });
    }
};
