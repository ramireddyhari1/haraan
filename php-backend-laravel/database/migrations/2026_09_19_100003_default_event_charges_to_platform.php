<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * New events inherit the platform's fee, gateway-fee and tax defaults from /control →
 * Platform rules unless an admin sets the event's own. Only the column DEFAULT changes:
 * existing events keep exactly what they have, and the platform defaults themselves start
 * at "none", so nothing is charged differently until an admin chooses a value.
 *
 * Needed because partner-created events never see these (admin-only) fields, so they got the
 * column default "none" and a platform fee set in /control could never reach them.
 */
return new class extends Migration
{
    private const COLUMNS = ['tax_type', 'gateway_fee_type', 'platform_fee_type'];

    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            foreach (self::COLUMNS as $column) {
                $table->string($column)->default('inherit')->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            foreach (self::COLUMNS as $column) {
                $table->string($column)->default('none')->change();
            }
        });
    }
};
