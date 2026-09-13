<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('standing_contracts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_court_id')->constrained('venue_courts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('customer_name', 120);
            $table->string('customer_phone', 30);
            $table->string('sport', 50)->nullable();

            $table->string('day_of_week', 20); // monday, tuesday, etc.
            $table->string('start_time', 10);  // HH:mm
            $table->string('end_time', 10);    // HH:mm
            $table->integer('duration_minutes')->default(60);

            $table->decimal('price_per_session', 10, 2);
            $table->decimal('monthly_package_price', 10, 2)->nullable();
            $table->decimal('security_deposit', 10, 2)->default(0.00);
            $table->decimal('advance_paid', 10, 2)->default(0.00);
            $table->decimal('balance_due', 10, 2)->default(0.00);

            $table->date('active_from');
            $table->date('active_until')->nullable();

            $table->string('status', 20)->default('active'); // active, paused, expired, at_risk, terminated, completed
            $table->boolean('auto_renew')->default(true);
            $table->integer('max_members')->default(10);
            $table->text('notes')->nullable();

            // Churn and Attendance Metrics
            $table->integer('consecutive_missed_sessions')->default(0);
            $table->integer('total_sessions_count')->default(0);
            $table->integer('attended_sessions_count')->default(0);
            $table->decimal('attendance_rate', 5, 2)->default(0.00);
            $table->boolean('is_at_risk')->default(false);
            $table->string('risk_reason', 255)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['venue_id', 'status']);
            $table->index(['day_of_week', 'start_time']);
            $table->index(['customer_phone']);
        });

        Schema::create('standing_contract_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('standing_contract_id')->constrained('standing_contracts')->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();

            $table->date('session_date');
            $table->string('start_time', 10);
            $table->string('end_time', 10);
            $table->foreignId('venue_court_id')->constrained('venue_courts')->cascadeOnDelete();

            $table->decimal('price', 10, 2);
            $table->string('attendance_status', 30)->default('scheduled');
            // scheduled, present, absent, cancelled_customer, cancelled_venue, cancelled_rain, skipped_holiday, skipped_manual

            $table->timestamp('check_in_time')->nullable();
            $table->string('payment_status', 20)->default('unpaid'); // unpaid, paid, deposit_locked, waived
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['standing_contract_id', 'session_date']);
            $table->index(['session_date', 'attendance_status']);
        });

        Schema::create('standing_contract_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('standing_contract_id')->constrained('standing_contracts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('action', 50); // created, paused, resumed, session_skipped, court_transferred, price_changed, deposit_adjusted, terminated, renewed
            $table->text('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['standing_contract_id', 'created_at']);
        });

        Schema::create('standing_contract_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('standing_contract_id')->constrained('standing_contracts')->cascadeOnDelete();
            $table->foreignId('booking_payment_id')->nullable()->constrained('booking_payments')->nullOnDelete();

            $table->decimal('amount', 10, 2);
            $table->string('payment_type', 30)->default('monthly_fee'); // deposit, session_advance, monthly_fee, refund, penalty
            $table->string('method', 30)->default('cash'); // cash, upi, card, online, wallet
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['standing_contract_id', 'created_at']);
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('standing_contract_id')->nullable()->after('recurring_group')
                ->constrained('standing_contracts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('standing_contract_id');
        });

        Schema::dropIfExists('standing_contract_payments');
        Schema::dropIfExists('standing_contract_logs');
        Schema::dropIfExists('standing_contract_sessions');
        Schema::dropIfExists('standing_contracts');
    }
};
