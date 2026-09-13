<?php

declare(strict_types=1);

namespace Tests\Feature\Hrms;

use App\Models\Hrms\Department;
use App\Models\Hrms\Designation;
use App\Models\Hrms\EmployeeAttendance;
use App\Models\Hrms\EmployeePayroll;
use App\Models\Hrms\EmployeeProfile;
use App\Models\User;
use App\Services\Hrms\PayrollCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PayrollCalculationTest extends TestCase
{
    use RefreshDatabase;

    private PayrollCalculationService $service;
    private User $user;
    private EmployeeProfile $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PayrollCalculationService();

        $dept = Department::create(['name' => 'Finance', 'code' => 'FIN']);
        $desig = Designation::create(['name' => 'Accountant', 'code' => 'ACC', 'department_id' => $dept->id]);

        $this->user = User::factory()->create([
            'name' => 'Kavitha S',
            'email' => 'kavitha@haraan.com',
            'role' => 'EMPLOYEE',
        ]);

        $this->employee = EmployeeProfile::create([
            'user_id' => $this->user->id,
            'employee_code' => 'EMP-PAY-001',
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'joining_date' => '2025-01-01',
            'employment_type' => 'FULL_TIME',
            'employment_status' => 'ACTIVE',
            'base_salary' => 50000.00, // ₹50,000 monthly CTC
        ]);
    }

    public function test_salary_components_split_and_pro_rata_calculation(): void
    {
        // For August 2026 (31 days), create 31 present days
        $month = '2026-08';
        for ($d = 1; $d <= 31; $d++) {
            $dayStr = sprintf('2026-08-%02d', $d);
            EmployeeAttendance::create([
                'employee_profile_id' => $this->employee->id,
                'date' => $dayStr,
                'clock_in_at' => "{$dayStr} 09:00:00",
                'clock_out_at' => "{$dayStr} 18:00:00",
                'status' => 'present',
            ]);
        }

        $payroll = $this->service->calculate($this->employee, $month);

        $this->assertNotNull($payroll->id);
        $this->assertEquals('2026-08', $payroll->payroll_month);
        $this->assertEquals(31, $payroll->working_days);
        $this->assertEquals(31.0, $payroll->present_days);

        // Basic: 50% of ₹50,000 = ₹25,000
        $this->assertEquals(25000.00, (float) $payroll->basic_salary);

        // HRA: 30% of ₹50,000 = ₹15,000
        $this->assertEquals(15000.00, (float) $payroll->hra);

        // Special Allowance: 20% of ₹50,000 = ₹10,000
        $this->assertEquals(10000.00, (float) $payroll->special_allowance);

        // Gross: 25,000 + 15,000 + 10,000 = 50,000
        $this->assertEquals(50000.00, (float) $payroll->gross_earnings);

        // PF: 12% of basic (₹25,000 * 0.12) = ₹3,000
        $this->assertEquals(3000.00, (float) $payroll->pf_deduction);

        // Professional Tax: ₹200 (since gross > ₹15,000)
        $this->assertEquals(200.00, (float) $payroll->professional_tax);

        // TDS: 5% of gross (since gross > ₹40,000) = ₹2,500
        $this->assertEquals(2500.00, (float) $payroll->tds_deduction);

        // Total Deductions: 3000 + 200 + 2500 = 5700
        $this->assertEquals(5700.00, (float) $payroll->total_deductions);

        // Net Salary: 50000 - 5700 = 44300
        $this->assertEquals(44300.00, (float) $payroll->net_salary);

        // Payslip number format
        $expectedNumber = 'PAY-202608-' . str_pad((string) $this->employee->id, 4, '0', STR_PAD_LEFT);
        $this->assertEquals($expectedNumber, $payroll->payslip_number);
    }

    public function test_payslip_html_generates_valid_document(): void
    {
        $payroll = $this->service->calculate($this->employee, '2026-08');

        $html = $this->service->generatePayslipHtml($payroll);

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('HARAAN', $html);
        $this->assertStringContainsString($payroll->payslip_number, $html);
        $this->assertStringContainsString('Kavitha S', $html);
        $this->assertStringContainsString('EMP-PAY-001', $html);
        $this->assertStringContainsString('Net Take-Home Pay', $html);
    }
}
