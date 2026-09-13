<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Employee Delegations (Guardrailed Delegation of Authority)
        Schema::create('employee_delegations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('delegator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('delegatee_id')->constrained('users')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('scope', 64)->default('all'); // all, regularisations, leaves, shifts, tasks
            $table->unsignedSmallInteger('max_approval_tier')->default(1);
            $table->boolean('is_active')->default(true);
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['delegator_id', 'is_active', 'start_date', 'end_date'], 'emp_deleg_delegator_idx');
            $table->index(['delegatee_id', 'is_active', 'start_date', 'end_date'], 'emp_deleg_delegatee_idx');
        });

        // 2. Enhance Attendance Regularisations for Multi-Tier Approval
        Schema::table('employee_attendance_regularisations', function (Blueprint $table): void {
            $table->unsignedSmallInteger('approval_tier')->default(1)->after('reason');
            $table->foreignId('tier1_approved_by')->nullable()->constrained('users')->nullOnDelete()->after('status');
            $table->timestamp('tier1_approved_at')->nullable()->after('tier1_approved_by');
            $table->text('tier1_notes')->nullable()->after('tier1_approved_at');
            $table->foreignId('tier2_approved_by')->nullable()->constrained('users')->nullOnDelete()->after('tier1_notes');
            $table->timestamp('tier2_approved_at')->nullable()->after('tier2_approved_by');
            $table->text('tier2_notes')->nullable()->after('tier2_approved_at');
            $table->timestamp('escalated_at')->nullable()->after('tier2_notes');
            $table->string('escalation_reason')->nullable()->after('escalated_at');
        });

        // 3. Enhance Leave Requests for Multi-Tier Approval
        Schema::table('employee_leave_requests', function (Blueprint $table): void {
            $table->unsignedSmallInteger('approval_tier')->default(1)->after('reason');
            $table->foreignId('tier1_approved_by')->nullable()->constrained('users')->nullOnDelete()->after('status');
            $table->timestamp('tier1_approved_at')->nullable()->after('tier1_approved_by');
            $table->text('tier1_notes')->nullable()->after('tier1_approved_at');
            $table->foreignId('tier2_approved_by')->nullable()->constrained('users')->nullOnDelete()->after('tier1_notes');
            $table->timestamp('tier2_approved_at')->nullable()->after('tier2_approved_by');
            $table->text('tier2_notes')->nullable()->after('tier2_approved_at');
            $table->timestamp('escalated_at')->nullable()->after('tier2_notes');
            $table->string('escalation_reason')->nullable()->after('escalated_at');
        });
    }

    public function down(): void
    {
        Schema::table('employee_leave_requests', function (Blueprint $table): void {
            $table->dropForeign(['tier1_approved_by']);
            $table->dropForeign(['tier2_approved_by']);
            $table->dropColumn([
                'approval_tier',
                'tier1_approved_by',
                'tier1_approved_at',
                'tier1_notes',
                'tier2_approved_by',
                'tier2_approved_at',
                'tier2_notes',
                'escalated_at',
                'escalation_reason',
            ]);
        });

        Schema::table('employee_attendance_regularisations', function (Blueprint $table): void {
            $table->dropForeign(['tier1_approved_by']);
            $table->dropForeign(['tier2_approved_by']);
            $table->dropColumn([
                'approval_tier',
                'tier1_approved_by',
                'tier1_approved_at',
                'tier1_notes',
                'tier2_approved_by',
                'tier2_approved_at',
                'tier2_notes',
                'escalated_at',
                'escalation_reason',
            ]);
        });

        Schema::dropIfExists('employee_delegations');
    }
};
