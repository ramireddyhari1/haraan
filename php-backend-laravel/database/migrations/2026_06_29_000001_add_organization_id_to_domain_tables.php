<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1b groundwork — give district/venue-owned domain records a canonical
 * link to organization_units. Columns are nullable (null = platform-wide /
 * unassigned). Tenant query scoping is NOT enabled here; this only lays the
 * schema + FKs so scoping can be switched on later once RBAC is verified.
 *
 * SQLite can't ALTER-TABLE add a foreign key, so the constraint is applied only
 * on real RDBMS drivers; the relationship is enforced in Eloquent regardless.
 */
return new class extends Migration {
    /** Tables that gain an owning organization unit. */
    private const TABLES = ['users', 'venues', 'events', 'bookings', 'live_matches'];

    public function up(): void
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'organization_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($isSqlite) {
                $t->unsignedBigInteger('organization_id')->nullable()->after('id')->index();

                if (! $isSqlite) {
                    $t->foreign('organization_id')
                        ->references('id')->on('organization_units')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'organization_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table, $isSqlite) {
                if (! $isSqlite) {
                    $t->dropForeign($table.'_organization_id_foreign');
                }
                $t->dropColumn('organization_id');
            });
        }
    }
};
