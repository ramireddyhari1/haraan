<?php

declare(strict_types=1);

namespace App\Models\Hrms;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EmployeeShiftSwap extends Model
{
    use HasFactory;

    protected $table = 'employee_shift_swaps';

    protected $fillable = [
        'requestor_roster_id',
        'target_employee_id',
        'target_roster_id',
        'status',
        'reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function requestorRoster(): BelongsTo
    {
        return $this->belongsTo(EmployeeShiftRoster::class, 'requestor_roster_id');
    }

    public function targetEmployee(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class, 'target_employee_id');
    }

    public function targetRoster(): BelongsTo
    {
        return $this->belongsTo(EmployeeShiftRoster::class, 'target_roster_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
