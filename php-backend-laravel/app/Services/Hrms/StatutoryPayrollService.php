<?php

declare(strict_types=1);

namespace App\Services\Hrms;

use App\Models\Hrms\EmployeeAttendance;
use App\Models\Hrms\EmployeePayroll;
use App\Models\Hrms\EmployeeProfile;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Carbon;
use RuntimeException;

final class StatutoryPayrollService
{
    public function __construct(
        private readonly WorkforceAuditService $auditService
    ) {}

    /**
     * Compute statutory payroll for a single employee adhering to Indian labor laws.
     *
     * @param  string  $month  Format: "YYYY-MM" (e.g. "2026-09")
     */
    public function calculateForEmployee(EmployeeProfile $employee, string $month): EmployeePayroll
    {
        $existing = EmployeePayroll::where('employee_profile_id', $employee->id)
            ->where('payroll_month', $month)
            ->first();

        if ($existing && $existing->status === 'locked') {
            throw new RuntimeException("Payroll for {$employee->employee_code} in {$month} is cryptographically locked and immutable.");
        }

        $startDate = Carbon::parse("{$month}-01")->startOfMonth();
        $endDate = (clone $startDate)->endOfMonth();
        $workingDays = (int) $startDate->daysInMonth;

        $attendances = EmployeeAttendance::where('employee_profile_id', $employee->id)
            ->whereBetween('date', [$startDate->startOfDay()->toDateTimeString(), $endDate->endOfDay()->toDateTimeString()])
            ->get();

        $presentDays = 0.0;
        $paidLeaveDays = 0.0;
        $unpaidLeaveDays = 0.0;
        $overtimeMinutes = 0;

        foreach ($attendances as $att) {
            if (in_array($att->status, ['present', 'late'], true)) {
                $presentDays += 1.0;
            } elseif ($att->status === 'half_day') {
                $presentDays += 0.5;
            } elseif ($att->status === 'on_leave') {
                $paidLeaveDays += 1.0;
            }

            // Calculate overtime: work minutes in excess of 480 minutes (8 hours) per day
            $workMinutes = (int) ($att->total_work_minutes ?? 0);
            if ($workMinutes > 480) {
                $overtimeMinutes += ($workMinutes - 480);
            }
        }

        $effectivePayableDays = min($workingDays, $presentDays + $paidLeaveDays);
        $absentDays = max(0.0, $workingDays - $effectivePayableDays);

        $baseSalary = (float) $employee->base_salary;
        if ($baseSalary <= 0) {
            $baseSalary = 30000.0;
        }

        // Standard Indian Wage Structure: 50% Basic, 30% HRA, 20% Special Allowance
        $fullBasic = round($baseSalary * 0.50, 2);
        $fullHra = round($baseSalary * 0.30, 2);
        $fullSpecial = round($baseSalary * 0.20, 2);

        $ratio = $workingDays > 0 ? ($effectivePayableDays / $workingDays) : 1.0;

        $basicSalary = round($fullBasic * $ratio, 2);
        $hra = round($fullHra * $ratio, 2);
        $specialAllowance = round($fullSpecial * $ratio, 2);

        // Statutory Overtime Pay: configured overtime multiplier (default 2x) under Factories / Shops Act
        $otMultiplier = (float) config('workforce.statutory.overtime_rate_multiplier', 2.0);
        $hourlyRate = $workingDays > 0 ? ($baseSalary / ($workingDays * 8.0)) : ($baseSalary / 208.0);
        $overtimeHours = round($overtimeMinutes / 60.0, 2);
        $overtimeAmount = round($overtimeHours * ($hourlyRate * $otMultiplier), 2);

        $performanceBonus = 0.0;
        $grossEarnings = round($basicSalary + $hra + $specialAllowance + $overtimeAmount + $performanceBonus, 2);

        // Statutory Deductions:
        // 1. Employee Provident Fund (PF): 12% of earned basic salary
        $pfRate = (float) config('workforce.statutory.pf_employee_rate', 0.12);
        $pfDeduction = round($basicSalary * $pfRate, 2);

        // 2. Employee State Insurance (ESI): 0.75% of gross earnings (if gross <= 21,000)
        $esiCeiling = (float) config('workforce.statutory.esi_wage_ceiling', 21000.0);
        $esiRate = (float) config('workforce.statutory.esi_employee_rate', 0.0075);
        $esiDeduction = ($grossEarnings <= $esiCeiling) ? round($grossEarnings * $esiRate, 2) : 0.0;

        // 3. Professional Tax (PT): ₹200 for gross > ₹15,000
        $ptThreshold = (float) config('workforce.statutory.professional_tax_threshold', 15000.0);
        $ptAmount = (float) config('workforce.statutory.professional_tax_amount', 200.0);
        $professionalTax = ($grossEarnings > $ptThreshold) ? $ptAmount : 0.0;

        // 4. Tax Deducted at Source (TDS): 5% for gross > ₹40,000
        $tdsThreshold = (float) config('workforce.statutory.tds_threshold', 40000.0);
        $tdsRate = (float) config('workforce.statutory.tds_rate', 0.05);
        $tdsDeduction = ($grossEarnings > $tdsThreshold) ? round($grossEarnings * $tdsRate, 2) : 0.0;

        $otherDeductions = 0.0;
        $totalDeductions = round($pfDeduction + $esiDeduction + $professionalTax + $tdsDeduction + $otherDeductions, 2);
        $netSalary = max(0.0, round($grossEarnings - $totalDeductions, 2));

        $payslipNumber = 'PAY-' . str_replace('-', '', $month) . '-' . str_pad((string) $employee->id, 4, '0', STR_PAD_LEFT);

        return EmployeePayroll::updateOrCreate(
            [
                'employee_profile_id' => $employee->id,
                'payroll_month' => $month,
            ],
            [
                'payment_date' => Carbon::now()->toDateString(),
                'working_days' => $workingDays,
                'present_days' => $presentDays,
                'paid_leave_days' => $paidLeaveDays,
                'unpaid_leave_days' => $unpaidLeaveDays,
                'absent_days' => $absentDays,
                'basic_salary' => $basicSalary,
                'hra' => $hra,
                'special_allowance' => $specialAllowance,
                'overtime_amount' => $overtimeAmount,
                'performance_bonus' => $performanceBonus,
                'gross_earnings' => $grossEarnings,
                'pf_deduction' => $pfDeduction,
                'esi_deduction' => $esiDeduction,
                'professional_tax' => $professionalTax,
                'tds_deduction' => $tdsDeduction,
                'other_deductions' => $otherDeductions,
                'total_deductions' => $totalDeductions,
                'net_salary' => $netSalary,
                'status' => 'approved',
                'payment_method' => 'bank_transfer',
                'payslip_number' => $payslipNumber,
            ]
        );
    }

    /**
     * Generate monthly payroll batch for an entire venue.
     */
    public function generateBatchForVenue(Venue $venue, string $month): array
    {
        $employees = EmployeeProfile::where('venue_id', $venue->id)
            ->whereIn('employment_status', ['active', 'ACTIVE'])
            ->get();

        $processed = [];
        $totalGross = 0.0;
        $totalNet = 0.0;
        $totalOvertime = 0.0;

        foreach ($employees as $employee) {
            $payroll = $this->calculateForEmployee($employee, $month);
            $processed[] = $payroll;
            $totalGross += (float) $payroll->gross_earnings;
            $totalNet += (float) $payroll->net_salary;
            $totalOvertime += (float) $payroll->overtime_amount;
        }

        return [
            'venue_id' => $venue->id,
            'month' => $month,
            'processed_count' => count($processed),
            'total_gross' => round($totalGross, 2),
            'total_net' => round($totalNet, 2),
            'total_overtime' => round($totalOvertime, 2),
            'payrolls' => $processed,
        ];
    }

    /**
     * Cryptographically lock the payroll batch for the month, preventing any post-finalization alteration.
     */
    public function lockBatch(Venue $venue, string $month, User $authorizer): array
    {
        $payrolls = EmployeePayroll::whereHas('employee', function ($q) use ($venue): void {
            $q->where('venue_id', $venue->id);
        })->where('payroll_month', $month)->get();

        if ($payrolls->isEmpty()) {
            throw new RuntimeException("No payroll records found for venue #{$venue->id} in month {$month}.");
        }

        $payrollIds = [];
        $totalNet = 0.0;
        $totalPf = 0.0;
        $totalEsi = 0.0;

        foreach ($payrolls as $payroll) {
            $payroll->status = 'locked';
            $payroll->save();

            $payrollIds[] = $payroll->id;
            $totalNet += (float) $payroll->net_salary;
            $totalPf += (float) $payroll->pf_deduction;
            $totalEsi += (float) $payroll->esi_deduction;
        }

        // Record immutable event in cryptographic audit ledger
        $this->auditService->recordEvent(
            'PAYROLL_BATCH_LOCKED',
            $venue,
            ['status' => 'approved'],
            [
                'status' => 'locked',
                'month' => $month,
                'records_locked' => count($payrollIds),
                'total_net_disbursement' => round($totalNet, 2),
                'total_pf_liability' => round($totalPf, 2),
                'total_esi_liability' => round($totalEsi, 2),
            ],
            $authorizer,
            $venue->id,
            ['payroll_ids' => $payrollIds]
        );

        return [
            'success' => true,
            'venue_id' => $venue->id,
            'month' => $month,
            'locked_records_count' => count($payrollIds),
            'total_net_disbursement' => round($totalNet, 2),
            'status' => 'locked',
        ];
    }

    /**
     * Generate statutory compliance filing manifest (PF ECR & ESI returns).
     */
    public function generateComplianceExport(Venue $venue, string $month): array
    {
        $payrolls = EmployeePayroll::with(['employee.user', 'employee.designation'])
            ->whereHas('employee', function ($q) use ($venue): void {
                $q->where('venue_id', $venue->id);
            })
            ->where('payroll_month', $month)
            ->get();

        $schedule = [];
        $totalGross = 0.0;
        $totalBasic = 0.0;
        $totalPfEmployee = 0.0;
        $totalPfEmployer = 0.0; // Employer matching 12% (3.67% EPF + 8.33% EPS)
        $totalEsiEmployee = 0.0;
        $totalEsiEmployer = 0.0; // Employer 3.25%
        $totalPt = 0.0;

        foreach ($payrolls as $p) {
            $gross = (float) $p->gross_earnings;
            $basic = (float) $p->basic_salary;
            $pfEmp = (float) $p->pf_deduction;
            $pfEmprRate = (float) config('workforce.statutory.pf_employer_rate', 0.12);
            $esiEmprRate = (float) config('workforce.statutory.esi_employer_rate', 0.0325);
            $esiCeiling = (float) config('workforce.statutory.esi_wage_ceiling', 21000.0);
            $pfEmpr = round($basic * $pfEmprRate, 2);
            $esiEmp = (float) $p->esi_deduction;
            $esiEmpr = ($gross <= $esiCeiling) ? round($gross * $esiEmprRate, 2) : 0.0;
            $pt = (float) $p->professional_tax;

            $totalGross += $gross;
            $totalBasic += $basic;
            $totalPfEmployee += $pfEmp;
            $totalPfEmployer += $pfEmpr;
            $totalEsiEmployee += $esiEmp;
            $totalEsiEmployer += $esiEmpr;
            $totalPt += $pt;

            $schedule[] = [
                'employee_code' => $p->employee?->employee_code,
                'employee_name' => $p->employee?->user?->name,
                'designation' => $p->employee?->designation?->name,
                'working_days' => $p->working_days,
                'present_days' => $p->present_days,
                'earned_basic' => $basic,
                'gross_wages' => $gross,
                'pf_employee_12pct' => $pfEmp,
                'pf_employer_12pct' => $pfEmpr,
                'esi_employee_0_75pct' => $esiEmp,
                'esi_employer_3_25pct' => $esiEmpr,
                'professional_tax' => $pt,
                'net_salary' => (float) $p->net_salary,
                'status' => $p->status,
            ];
        }

        return [
            'manifest_type' => 'STATUTORY_LABOR_COMPLIANCE_FILING',
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'city' => $venue->city,
            ],
            'payroll_month' => $month,
            'generated_at' => Carbon::now()->toIso8601String(),
            'summary' => [
                'total_workforce_count' => count($schedule),
                'total_gross_wages' => round($totalGross, 2),
                'total_basic_wages' => round($totalBasic, 2),
                'total_pf_liability' => round($totalPfEmployee + $totalPfEmployer, 2),
                'pf_employee_share' => round($totalPfEmployee, 2),
                'pf_employer_share' => round($totalPfEmployer, 2),
                'total_esi_liability' => round($totalEsiEmployee + $totalEsiEmployer, 2),
                'esi_employee_share' => round($totalEsiEmployee, 2),
                'esi_employer_share' => round($totalEsiEmployer, 2),
                'total_professional_tax' => round($totalPt, 2),
            ],
            'employee_schedule' => $schedule,
        ];
    }
}
