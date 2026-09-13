<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('employee_shifts', function (Blueprint $table): void {
            $table->id();
            $table->string('name'); // e.g. Morning Shift, Evening Shift, Night Rotational
            $table->string('code')->unique();
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('grace_period_minutes')->default(15);
            $table->unsignedSmallInteger('half_day_threshold_minutes')->default(240);
            $table->boolean('is_night_shift')->default(false);
            $table->boolean('is_rotational')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('partner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('employee_shift_rosters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_profile_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->foreignId('employee_shift_id')->constrained('employee_shifts')->cascadeOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->date('roster_date');
            $table->string('status')->default('scheduled'); // scheduled, completed, swapped, dropped, cancelled
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_profile_id', 'roster_date']);
            $table->index(['venue_id', 'roster_date']);
        });

        Schema::create('employee_shift_swaps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requestor_roster_id')->constrained('employee_shift_rosters')->cascadeOnDelete();
            $table->foreignId('target_employee_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->foreignId('target_roster_id')->nullable()->constrained('employee_shift_rosters')->nullOnDelete();
            $table->string('status')->default('pending'); // pending, approved, rejected, cancelled
            $table->text('reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_attendances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_profile_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('employee_shift_id')->nullable()->constrained('employee_shifts')->nullOnDelete();
            
            // Punch In details
            $table->dateTime('clock_in_at')->nullable();
            $table->decimal('clock_in_latitude', 10, 7)->nullable();
            $table->decimal('clock_in_longitude', 10, 7)->nullable();
            $table->float('clock_in_distance_meters')->nullable();
            $table->string('clock_in_geofence_status')->default('exempt'); // inside, outside, exempt
            $table->string('clock_in_method')->default('gps_web'); // gps_web, qr_scan, biometric_face, manual_admin
            $table->string('clock_in_photo_path')->nullable();

            // Punch Out details
            $table->dateTime('clock_out_at')->nullable();
            $table->decimal('clock_out_latitude', 10, 7)->nullable();
            $table->decimal('clock_out_longitude', 10, 7)->nullable();
            $table->float('clock_out_distance_meters')->nullable();
            $table->string('clock_out_geofence_status')->default('exempt'); // inside, outside, exempt
            $table->string('clock_out_method')->default('gps_web');
            $table->string('clock_out_photo_path')->nullable();

            // Computed metrics
            $table->unsignedInteger('total_work_minutes')->default(0);
            $table->unsignedInteger('total_break_minutes')->default(0);
            $table->string('status')->default('absent'); // present, half_day, late, absent, on_leave, holiday, week_off
            $table->json('break_logs')->nullable(); // [{type, start, end, duration_minutes}]
            $table->text('admin_notes')->nullable();
            $table->boolean('is_verified')->default(false);

            $table->timestamps();

            $table->unique(['employee_profile_id', 'date']);
            $table->index(['date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_attendances');
        Schema::dropIfExists('employee_shift_swaps');
        Schema::dropIfExists('employee_shift_rosters');
        Schema::dropIfExists('employee_shifts');
    }
};
