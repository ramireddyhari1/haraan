<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coaching academy — recurring batches a venue runs alongside casual bookings.
 *
 *  - `venue_batches`      the class itself: coach, which weekdays, what time, fee
 *  - `batch_enrollments`  a student on that batch, paid up to a date
 *  - `batch_attendance`   one row per student per day they actually showed up
 *
 * Attendance is a ledger of rows rather than a counter for the same reason as
 * package redemptions: "how many classes has my child attended" must be
 * answerable and disputable, not just a number someone incremented.
 *
 * Students are keyed on PHONE, matching the partner CRM and packages — a parent
 * enrolling a child has no account, and the desk knows people by their number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->string('name', 120);
            $table->string('coach_name', 120)->nullable();
            $table->string('sport', 60)->nullable();
            // 3-letter weekday names, same shape as venue_courts.peak_days.
            $table->json('days')->nullable();
            $table->string('start_time', 5)->nullable();
            $table->string('end_time', 5)->nullable();
            $table->unsignedInteger('monthly_fee')->default(0);
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['partner_id', 'is_active']);
        });

        Schema::create('batch_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_batch_id')->constrained('venue_batches')->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained('users')->cascadeOnDelete();
            $table->string('student_name', 120);
            $table->string('student_phone', 15);
            $table->unsignedInteger('amount_paid')->default(0);
            // Paid up to this date; null = no expiry recorded.
            $table->date('paid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // One student sits in a batch once — re-enrolling extends paid_until
            // rather than creating a second seat that double-counts the roster.
            $table->unique(['venue_batch_id', 'student_phone']);
            $table->index(['partner_id', 'student_phone']);
        });

        Schema::create('batch_attendance', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_enrollment_id')->constrained('batch_enrollments')->cascadeOnDelete();
            $table->date('date');
            $table->timestamps();

            // Marking twice is the same fact, not two attendances.
            $table->unique(['batch_enrollment_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_attendance');
        Schema::dropIfExists('batch_enrollments');
        Schema::dropIfExists('venue_batches');
    }
};
