<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('employee_attendance_regularisations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_profile_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->foreignId('attendance_id')->nullable()->constrained('employee_attendances')->nullOnDelete();
            $table->date('date');
            $table->dateTime('requested_clock_in_at');
            $table->dateTime('requested_clock_out_at');
            $table->string('reason_category')->default('missed_punch'); // missed_punch, gps_drift, outdoor_duty, biometric_error, other
            $table->text('reason');
            $table->string('status')->default('pending'); // pending, approved, rejected, cancelled
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reviewer_notes')->nullable();
            $table->timestamps();

            $table->index(['employee_profile_id', 'date']);
            $table->index(['status', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_attendance_regularisations');
    }
};
