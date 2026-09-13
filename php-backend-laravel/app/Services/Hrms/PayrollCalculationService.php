<?php

declare(strict_types=1);

namespace App\Services\Hrms;

use App\Models\Hrms\EmployeeAttendance;
use App\Models\Hrms\EmployeePayroll;
use App\Models\Hrms\EmployeeProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PayrollCalculationService
{
    /**
     * Compute and create or update monthly payroll for an employee.
     *
     * @param  string  $month  Format: "YYYY-MM" (e.g. "2026-08")
     */
    public function calculate(EmployeeProfile $employee, string $month): EmployeePayroll
    {
        $startDate = Carbon::parse("{$month}-01")->startOfMonth();
        $endDate = (clone $startDate)->endOfMonth();
        $workingDays = (int) $startDate->daysInMonth;

        // Fetch attendances for this month
        $attendances = EmployeeAttendance::where('employee_profile_id', $employee->id)
            ->whereBetween('date', [$startDate->startOfDay()->toDateTimeString(), $endDate->endOfDay()->toDateTimeString()])
            ->get();

        $presentDays = 0.0;
        $paidLeaveDays = 0.0;
        $unpaidLeaveDays = 0.0;

        foreach ($attendances as $att) {
            if (in_array($att->status, ['present', 'late'], true)) {
                $presentDays += 1.0;
            } elseif ($att->status === 'half_day') {
                $presentDays += 0.5;
            } elseif ($att->status === 'on_leave') {
                $paidLeaveDays += 1.0;
            }
        }

        $effectivePayableDays = min($workingDays, $presentDays + $paidLeaveDays);
        $absentDays = max(0.0, $workingDays - $effectivePayableDays);

        // If employee has a base salary, calculate earnings
        $baseSalary = (float) $employee->base_salary;
        if ($baseSalary <= 0) {
            $baseSalary = 30000.0; // Standard fallback for demo/default
        }

        // Standard structure: 50% Basic, 30% HRA, 20% Special Allowance
        $fullBasic = round($baseSalary * 0.50, 2);
        $fullHra = round($baseSalary * 0.30, 2);
        $fullSpecial = round($baseSalary * 0.20, 2);

        // Pro-rate against payable days
        $ratio = $workingDays > 0 ? ($effectivePayableDays / $workingDays) : 1.0;

        $basicSalary = round($fullBasic * $ratio, 2);
        $hra = round($fullHra * $ratio, 2);
        $specialAllowance = round($fullSpecial * $ratio, 2);
        $overtimeAmount = 0.0;
        $performanceBonus = 0.0;

        $grossEarnings = round($basicSalary + $hra + $specialAllowance + $overtimeAmount + $performanceBonus, 2);

        // Statutory Deductions
        // 1. PF: 12% of earned basic
        $pfDeduction = round($basicSalary * 0.12, 2);

        // 2. ESI: 0.75% of gross earnings (if gross <= 21,000)
        $esiDeduction = ($grossEarnings <= 21000.0) ? round($grossEarnings * 0.0075, 2) : 0.0;

        // 3. Professional Tax: standard ₹200
        $professionalTax = ($grossEarnings > 15000.0) ? 200.0 : 0.0;

        // 4. TDS: standard 5% if monthly > 40,000
        $tdsDeduction = ($grossEarnings > 40000.0) ? round($grossEarnings * 0.05, 2) : 0.0;

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
     * Render a clean, standalone, printable HTML payslip.
     */
    public function generatePayslipHtml(EmployeePayroll $payroll): string
    {
        $employee = $payroll->employee;
        $user = $employee?->user;
        $userName = $user?->name ?? 'Employee';
        $empCode = $employee?->employee_code ?? 'EMP';
        $dept = $employee?->department?->name ?? 'General';
        $desig = $employee?->designation?->name ?? 'Staff';
        $bankName = $employee?->bank_name ?? 'N/A';
        $accountLast4 = $employee?->bank_account_no ? substr((string) $employee->bank_account_no, -4) : 'N/A';
        $monthName = date('F Y', strtotime($payroll->payroll_month . '-01'));
        $payslipNumber = $payroll->payslip_number;
        $workingDays = (string) $payroll->working_days;
        $presentDays = (string) $payroll->present_days;
        $basicSalary = (string) $payroll->basic_salary;
        $hra = (string) $payroll->hra;
        $specialAllowance = (string) $payroll->special_allowance;
        $performanceBonus = (string) $payroll->performance_bonus;
        $grossEarnings = (string) $payroll->gross_earnings;
        $pfDeduction = (string) $payroll->pf_deduction;
        $esiDeduction = (string) $payroll->esi_deduction;
        $professionalTax = (string) $payroll->professional_tax;
        $tdsDeduction = (string) $payroll->tds_deduction;
        $totalDeductions = (string) $payroll->total_deductions;
        $netSalary = (string) $payroll->net_salary;

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payslip - {$payslipNumber}</title>
<style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: #1e293b; background: #f8fafc; padding: 30px; margin: 0; }
    .payslip { max-width: 780px; margin: 0 auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 36px; box-shadow: 0 4px 6px -1px rgba(0,0,0,.05); }
    .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #059669; padding-bottom: 20px; margin-bottom: 24px; }
    .brand { font-size: 24px; font-weight: 800; color: #059669; letter-spacing: -0.02em; }
    .brand-sub { font-size: 13px; color: #64748b; margin-top: 2px; }
    .doc-title { text-align: right; }
    .doc-title h1 { margin: 0; font-size: 18px; font-weight: 700; color: #0f172a; text-transform: uppercase; }
    .doc-title .month { font-size: 14px; color: #64748b; font-weight: 600; }
    .meta-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; background: #f8fafc; padding: 16px; border-radius: 8px; font-size: 13px; margin-bottom: 24px; }
    .meta-row { display: flex; justify-content: space-between; padding: 4px 0; }
    .meta-label { color: #64748b; }
    .meta-val { font-weight: 600; color: #0f172a; }
    .breakdown-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
    .table-box { border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
    .table-box h3 { margin: 0; padding: 10px 14px; background: #f1f5f9; font-size: 13px; text-transform: uppercase; letter-spacing: .05em; color: #334155; }
    table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
    td { padding: 10px 14px; border-bottom: 1px solid #f1f5f9; }
    td.amount { text-align: right; font-weight: 600; }
    .total-row { background: #f8fafc; font-weight: 700; }
    .net-box { display: flex; justify-content: space-between; align-items: center; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 18px 24px; margin-bottom: 24px; }
    .net-label { font-size: 15px; font-weight: 700; color: #065f46; text-transform: uppercase; letter-spacing: .03em; }
    .net-val { font-size: 26px; font-weight: 800; color: #047857; }
    .footer { text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px dashed #e2e8f0; padding-top: 18px; }
    @media print {
        body { background: #fff; padding: 0; }
        .payslip { border: none; box-shadow: none; padding: 0; }
        .no-print { display: none; }
    }
</style>
</head>
<body>
<div class="payslip">
    <div class="header">
        <div>
            <div class="brand">HARAAN ENTERPRISES</div>
            <div class="brand-sub">Employee Payslip Statement</div>
        </div>
        <div class="doc-title">
            <h1>Payslip</h1>
            <div class="month">{$monthName}</div>
            <div style="font-size: 11px; color: #94a3b8; margin-top: 4px;">Ref: {$payslipNumber}</div>
        </div>
    </div>

    <div class="meta-grid">
        <div>
            <div class="meta-row"><span class="meta-label">Employee Name:</span><span class="meta-val">{$userName}</span></div>
            <div class="meta-row"><span class="meta-label">Employee Code:</span><span class="meta-val">{$empCode}</span></div>
            <div class="meta-row"><span class="meta-label">Department:</span><span class="meta-val">{$dept}</span></div>
            <div class="meta-row"><span class="meta-label">Designation:</span><span class="meta-val">{$desig}</span></div>
        </div>
        <div>
            <div class="meta-row"><span class="meta-label">Bank Name:</span><span class="meta-val">{$bankName}</span></div>
            <div class="meta-row"><span class="meta-label">Account No:</span><span class="meta-val">•••• {$accountLast4}</span></div>
            <div class="meta-row"><span class="meta-label">Working Days:</span><span class="meta-val">{$workingDays}</span></div>
            <div class="meta-row"><span class="meta-label">Present Days:</span><span class="meta-val">{$presentDays}</span></div>
        </div>
    </div>

    <div class="breakdown-grid">
        <div class="table-box">
            <h3>Earnings (INR)</h3>
            <table>
                <tr><td>Basic Salary</td><td class="amount">₹{$basicSalary}</td></tr>
                <tr><td>House Rent Allowance (HRA)</td><td class="amount">₹{$hra}</td></tr>
                <tr><td>Special Allowance</td><td class="amount">₹{$specialAllowance}</td></tr>
                <tr><td>Performance Bonus</td><td class="amount">₹{$performanceBonus}</td></tr>
                <tr class="total-row"><td>Gross Earnings</td><td class="amount">₹{$grossEarnings}</td></tr>
            </table>
        </div>
        <div class="table-box">
            <h3>Deductions (INR)</h3>
            <table>
                <tr><td>Provident Fund (PF)</td><td class="amount">₹{$pfDeduction}</td></tr>
                <tr><td>Employee State Insurance (ESI)</td><td class="amount">₹{$esiDeduction}</td></tr>
                <tr><td>Professional Tax (PT)</td><td class="amount">₹{$professionalTax}</td></tr>
                <tr><td>TDS / Income Tax</td><td class="amount">₹{$tdsDeduction}</td></tr>
                <tr class="total-row"><td>Total Deductions</td><td class="amount">₹{$totalDeductions}</td></tr>
            </table>
        </div>
    </div>

    <div class="net-box">
        <div class="net-label">Net Take-Home Pay</div>
        <div class="net-val">₹{$netSalary}</div>
    </div>

    <div class="footer">
        This is a system-generated payslip issued by HARAAN Human Resource Management System. No physical signature is required.
    </div>
</div>
</body>
</html>
HTML;
    }
}
