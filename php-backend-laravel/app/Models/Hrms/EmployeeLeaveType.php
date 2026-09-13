<?php

declare(strict_types=1);

namespace App\Models\Hrms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EmployeeLeaveType extends Model
{
    use HasFactory;

    protected $table = 'employee_leave_types';

    protected $fillable = [
        'name',
        'code',
        'annual_quota',
        'is_paid',
        'carry_forward_max',
        'is_active',
    ];

    protected $casts = [
        'annual_quota' => 'integer',
        'is_paid' => 'boolean',
        'carry_forward_max' => 'integer',
        'is_active' => 'boolean',
    ];

    public function balances(): HasMany
    {
        return $this->hasMany(EmployeeLeaveBalance::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(EmployeeLeaveRequest::class);
    }
}
