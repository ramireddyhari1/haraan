<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('employee_leave_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name'); // Casual Leave, Sick Leave, Earned Leave, Comp Off, LOP
            $table->string('code')->unique(); // CL, SL, EL, COMP, LOP
            $table->unsignedSmallInteger('annual_quota')->default(12);
            $table->boolean('is_paid')->default(true);
            $table->unsignedSmallInteger('carry_forward_max')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_leave_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_profile_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->foreignId('employee_leave_type_id')->constrained('employee_leave_types')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('allocated_days', 5, 1)->default(0);
            $table->decimal('used_days', 5, 1)->default(0);
            $table->decimal('pending_days', 5, 1)->default(0);
            $table->decimal('remaining_days', 5, 1)->default(0);
            $table->timestamps();

            $table->unique(['employee_profile_id', 'employee_leave_type_id', 'year'], 'emp_leave_bal_unique');
        });

        Schema::create('employee_leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_profile_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->foreignId('employee_leave_type_id')->constrained('employee_leave_types')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 4, 1)->default(1.0);
            $table->text('reason');
            $table->string('status')->default('pending'); // pending, approved, rejected, cancelled
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('approver_notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['employee_profile_id', 'status']);
        });

        Schema::create('employee_tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('employee_profile_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->string('priority')->default('medium'); // low, medium, high, urgent
            $table->string('status')->default('todo'); // todo, in_progress, completed, cancelled
            $table->unsignedSmallInteger('progress_percent')->default(0);
            $table->json('checklist_items')->nullable(); // [{id, text, done}]
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['employee_profile_id', 'status']);
        });

        Schema::create('employee_payrolls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_profile_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->string('payroll_month', 7); // e.g. 2026-08
            $table->date('payment_date')->nullable();
            
            // Days breakdown
            $table->unsignedSmallInteger('working_days')->default(30);
            $table->decimal('present_days', 4, 1)->default(0);
            $table->decimal('paid_leave_days', 4, 1)->default(0);
            $table->decimal('unpaid_leave_days', 4, 1)->default(0);
            $table->decimal('absent_days', 4, 1)->default(0);

            // Earnings
            $table->decimal('basic_salary', 12, 2)->default(0);
            $table->decimal('hra', 12, 2)->default(0);
            $table->decimal('special_allowance', 12, 2)->default(0);
            $table->decimal('overtime_amount', 12, 2)->default(0);
            $table->decimal('performance_bonus', 12, 2)->default(0);
            $table->decimal('gross_earnings', 12, 2)->default(0);

            // Deductions
            $table->decimal('pf_deduction', 12, 2)->default(0);
            $table->decimal('esi_deduction', 12, 2)->default(0);
            $table->decimal('professional_tax', 12, 2)->default(0);
            $table->decimal('tds_deduction', 12, 2)->default(0);
            $table->decimal('other_deductions', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);

            $table->decimal('net_salary', 12, 2)->default(0);
            $table->string('status')->default('draft'); // draft, approved, paid, cancelled
            $table->string('payment_method')->nullable(); // bank_transfer, upi, cash, cheque
            $table->string('transaction_reference')->nullable();
            $table->string('payslip_number')->unique();
            $table->timestamps();

            $table->unique(['employee_profile_id', 'payroll_month']);
        });

        Schema::create('employee_kpis', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_profile_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->string('period', 7); // e.g. 2026-08
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('punctuality_rating', 3, 1)->default(5.0); // 1-5
            $table->decimal('task_completion_rating', 3, 1)->default(5.0);
            $table->decimal('customer_service_rating', 3, 1)->default(5.0);
            $table->decimal('teamwork_rating', 3, 1)->default(5.0);
            $table->decimal('overall_score', 3, 1)->default(5.0);
            $table->text('achievements')->nullable();
            $table->text('areas_for_improvement')->nullable();
            $table->text('manager_feedback')->nullable();
            $table->timestamps();

            $table->unique(['employee_profile_id', 'period']);
        });

        Schema::create('holiday_calendars', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->date('date');
            $table->boolean('is_optional')->default(false);
            $table->foreignId('applicable_venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('date');
        });

        Schema::create('hrms_announcements', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('priority')->default('normal'); // normal, high, urgent
            $table->string('audience')->default('all'); // all, admin_hq, partner_staff
            $table->foreignId('partner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->timestamp('published_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hrms_announcements');
        Schema::dropIfExists('holiday_calendars');
        Schema::dropIfExists('employee_kpis');
        Schema::dropIfExists('employee_payrolls');
        Schema::dropIfExists('employee_tasks');
        Schema::dropIfExists('employee_leave_requests');
        Schema::dropIfExists('employee_leave_balances');
        Schema::dropIfExists('employee_leave_types');
    }
};
