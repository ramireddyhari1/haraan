<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('designations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->unsignedSmallInteger('level')->default(1);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['department_id', 'code']);
        });

        Schema::create('employee_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('employee_code')->unique();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained('designations')->nullOnDelete();
            $table->foreignId('reporting_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->date('joining_date')->nullable();
            $table->string('employment_type')->default('full_time'); // full_time, part_time, contract, intern
            $table->string('employment_status')->default('active'); // active, on_leave, probation, terminated, resigned
            
            // Personal Bio & Emergency
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('emergency_contact_relation')->nullable();
            $table->date('dob')->nullable();
            $table->string('gender')->nullable(); // male, female, other
            $table->string('blood_group')->nullable(); // A+, B+, O+, AB+, etc.
            $table->string('marital_status')->nullable();
            $table->text('residential_address')->nullable();

            // Banking Details
            $table->string('bank_name')->nullable();
            $table->string('bank_account_no')->nullable();
            $table->string('bank_ifsc')->nullable();
            $table->string('bank_upi_id')->nullable();

            // KYC Documents
            $table->string('pan_number')->nullable();
            $table->string('aadhaar_number')->nullable();
            $table->json('kyc_documents')->nullable(); // array of document file paths

            // Compensation & Geofencing defaults
            $table->decimal('base_salary', 12, 2)->default(0);
            $table->decimal('hourly_rate', 10, 2)->default(0);
            $table->unsignedInteger('geofence_radius_meters')->default(200);

            $table->timestamps();

            $table->index('partner_id');
            $table->index('venue_id');
            $table->index('employment_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_profiles');
        Schema::dropIfExists('designations');
        Schema::dropIfExists('departments');
    }
};
