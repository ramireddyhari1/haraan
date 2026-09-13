<?php

declare(strict_types=1);

namespace App\Models\Hrms;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EmployeeAttendanceRegularisation extends Model
{
    use HasFactory;

    protected $table = 'employee_attendance_regularisations';

    protected $fillable = [
        'employee_profile_id',
        'attendance_id',
        'date',
        'requested_clock_in_at',
        'requested_clock_out_at',
        'reason_category',
        'reason',
        'approval_tier',
        'status',
        'reviewed_by',
        'reviewed_at',
        'reviewer_notes',
        'tier1_approved_by',
        'tier1_approved_at',
        'tier1_notes',
        'tier2_approved_by',
        'tier2_approved_at',
        'tier2_notes',
        'escalated_at',
        'escalation_reason',
    ];

    protected $casts = [
        'date' => 'date',
        'requested_clock_in_at' => 'datetime',
        'requested_clock_out_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'tier1_approved_at' => 'datetime',
        'tier2_approved_at' => 'datetime',
        'escalated_at' => 'datetime',
        'approval_tier' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_profile_id');
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(EmployeeAttendance::class, 'attendance_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function tier1ApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tier1_approved_by');
    }

    public function tier2ApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tier2_approved_by');
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'tier1_pending', 'tier2_pending'], true);
    }
}
