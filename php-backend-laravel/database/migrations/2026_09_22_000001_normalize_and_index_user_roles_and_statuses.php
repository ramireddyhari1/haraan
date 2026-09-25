<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Normalise role and status values at rest to uppercase.
        DB::table('users')
            ->whereNotNull('role')
            ->where('role', '!=', '')
            ->update([
                'role' => DB::raw("UPPER(TRIM(role))"),
            ]);

        DB::table('users')
            ->whereNull('role')
            ->orWhere('role', '')
            ->update([
                'role' => 'USER',
            ]);

        DB::table('users')
            ->whereNotNull('status')
            ->where('status', '!=', '')
            ->update([
                'status' => DB::raw("UPPER(TRIM(status))"),
            ]);

        DB::table('users')
            ->whereNull('status')
            ->orWhere('status', '')
            ->update([
                'status' => 'ACTIVE',
            ]);

        // 2. Add performance indexes on frequently filtered / sorted user columns.
        Schema::table('users', function (Blueprint $table): void {
            $table->index('role', 'users_role_index');
            $table->index('status', 'users_status_index');
            $table->index('created_at', 'users_created_at_index');
            $table->index('district', 'users_district_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_role_index');
            $table->dropIndex('users_status_index');
            $table->dropIndex('users_created_at_index');
            $table->dropIndex('users_district_index');
        });
    }
};
