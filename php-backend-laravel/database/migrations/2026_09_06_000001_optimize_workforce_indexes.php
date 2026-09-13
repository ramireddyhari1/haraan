<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('workforce_audit_ledger', function (Blueprint $table): void {
            $table->index(['tenant_id', 'id'], 'idx_wal_tenant_id');
        });

        Schema::table('employee_payrolls', function (Blueprint $table): void {
            $table->index(['payroll_month', 'status'], 'idx_ep_month_status');
        });

        Schema::table('employee_attendance_regularisations', function (Blueprint $table): void {
            $table->index(['status', 'approval_tier'], 'idx_ear_status_tier');
        });

        Schema::table('employee_tasks', function (Blueprint $table): void {
            $table->index(['venue_id', 'status'], 'idx_et_venue_status');
        });
    }

    public function down(): void
    {
        Schema::table('workforce_audit_ledger', function (Blueprint $table): void {
            $table->dropIndex('idx_wal_tenant_id');
        });

        Schema::table('employee_payrolls', function (Blueprint $table): void {
            $table->dropIndex('idx_ep_month_status');
        });

        Schema::table('employee_attendance_regularisations', function (Blueprint $table): void {
            $table->dropIndex('idx_ear_status_tier');
        });

        Schema::table('employee_tasks', function (Blueprint $table): void {
            $table->dropIndex('idx_et_venue_status');
        });
    }
};
