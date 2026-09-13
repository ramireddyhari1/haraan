<?php

declare(strict_types=1);

namespace App\Models\Hrms;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

final class EmployeeProfile extends Model
{
    use HasFactory;

    protected $table = 'employee_profiles';

    protected $fillable = [
        'user_id',
        'employee_code',
        'department_id',
        'designation_id',
        'reporting_manager_id',
        'partner_id',
        'venue_id',
        'joining_date',
        'employment_type',
        'employment_status',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'dob',
        'gender',
        'blood_group',
        'marital_status',
        'residential_address',
        'bank_name',
        'bank_account_no',
        'bank_ifsc',
        'bank_upi_id',
        'pan_number',
        'aadhaar_number',
        'kyc_documents',
        'base_salary',
        'hourly_rate',
        'geofence_radius_meters',
    ];

    protected $casts = [
        'joining_date' => 'date',
        'dob' => 'date',
        'kyc_documents' => 'array',
        'base_salary' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'geofence_radius_meters' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function reportingManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporting_manager_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(EmployeeAttendance::class);
    }

    public function todayAttendance(): HasOne
    {
        return $this->hasOne(EmployeeAttendance::class)->whereDate('date', Carbon::today());
    }

    public function rosters(): HasMany
    {
        return $this->hasMany(EmployeeShiftRoster::class);
    }

    public function todayRoster(): HasOne
    {
        return $this->hasOne(EmployeeShiftRoster::class)->whereDate('roster_date', Carbon::today());
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(EmployeeLeaveBalance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(EmployeeLeaveRequest::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(EmployeeTask::class);
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(EmployeePayroll::class);
    }

    public function kpis(): HasMany
    {
        return $this->hasMany(EmployeeKpi::class);
    }

    public function regularisations(): HasMany
    {
        return $this->hasMany(EmployeeAttendanceRegularisation::class);
    }

    /** Direct reportees who report to this employee's user account */
    public function directReportees(): HasMany
    {
        return $this->hasMany(self::class, 'reporting_manager_id', 'user_id');
    }

    /** Does this employee manage direct reportees */
    public function isManager(): bool
    {
        return $this->directReportees()->exists();
    }

    /** Display name from user or employee code fallback */
    public function getFullNameAttribute(): string
    {
        return $this->user?->name ?? $this->employee_code;
    }

    /** Is this employee an active worker */
    public function isActive(): bool
    {
        return $this->employment_status === 'active';
    }
}
