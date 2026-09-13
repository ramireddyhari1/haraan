<?php

declare(strict_types=1);

namespace App\Models\Hrms;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EmployeeLeaveRequest extends Model
{
    use HasFactory;

    protected $table = 'employee_leave_requests';

    protected $fillable = [
        'employee_profile_id',
        'employee_leave_type_id',
        'start_date',
        'end_date',
        'total_days',
        'reason',
        'approval_tier',
        'status',
        'approver_id',
        'approver_notes',
        'approved_at',
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
        'start_date' => 'date',
        'end_date' => 'date',
        'total_days' => 'decimal:1',
        'approved_at' => 'datetime',
        'tier1_approved_at' => 'datetime',
        'tier2_approved_at' => 'datetime',
        'escalated_at' => 'datetime',
        'approval_tier' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_profile_id');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(EmployeeLeaveType::class, 'employee_leave_type_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
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
