<?php

declare(strict_types=1);

namespace App\Models\Hrms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EmployeePayroll extends Model
{
    use HasFactory;

    protected $table = 'employee_payrolls';

    protected $fillable = [
        'employee_profile_id',
        'payroll_month',
        'payment_date',
        'working_days',
        'present_days',
        'paid_leave_days',
        'unpaid_leave_days',
        'absent_days',
        'basic_salary',
        'hra',
        'special_allowance',
        'overtime_amount',
        'performance_bonus',
        'gross_earnings',
        'pf_deduction',
        'esi_deduction',
        'professional_tax',
        'tds_deduction',
        'other_deductions',
        'total_deductions',
        'net_salary',
        'status',
        'payment_method',
        'transaction_reference',
        'payslip_number',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'working_days' => 'integer',
        'present_days' => 'decimal:1',
        'paid_leave_days' => 'decimal:1',
        'unpaid_leave_days' => 'decimal:1',
        'absent_days' => 'decimal:1',
        'basic_salary' => 'decimal:2',
        'hra' => 'decimal:2',
        'special_allowance' => 'decimal:2',
        'overtime_amount' => 'decimal:2',
        'performance_bonus' => 'decimal:2',
        'gross_earnings' => 'decimal:2',
        'pf_deduction' => 'decimal:2',
        'esi_deduction' => 'decimal:2',
        'professional_tax' => 'decimal:2',
        'tds_deduction' => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_profile_id');
    }

    /** Month in human readable format e.g. "August 2026" */
    public function getFormattedMonthAttribute(): string
    {
        return date('F Y', strtotime($this->payroll_month . '-01'));
    }
}
