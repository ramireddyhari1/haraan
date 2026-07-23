<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // A desk person is a sub-user of a partner owner. Null = the user is
            // a top-level owner (or a normal user). No FK constraint — SQLite can't
            // add one via ALTER, and the column is scoped in code anyway.
            $table->unsignedBigInteger('parent_partner_id')->nullable()->after('partner_type');
            // Which capabilities this desk person has, e.g. ["bookings","checkin"].
            // Owners ignore this (they have everything).
            $table->json('staff_permissions')->nullable()->after('parent_partner_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['parent_partner_id', 'staff_permissions']);
        });
    }
};
