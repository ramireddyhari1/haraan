<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Employee;

use App\Models\Hrms\EmployeePayroll;
use App\Models\Hrms\EmployeeProfile;
use App\Services\Hrms\PayrollCalculationService;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

class PayrollSummaryWidget extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.employee.payroll-summary-widget';

    protected int | string | array $columnSpan = ['default' => 12, 'lg' => 6, 'xl' => 6];

    public bool $showPayslipModal = false;

    public function getEmployeeProperty(): ?EmployeeProfile
    {
        return auth()->user()?->employeeProfile;
    }

    public function getLatestPayrollProperty(): ?EmployeePayroll
    {
        $employee = $this->employee;
        if ($employee === null) {
            return null;
        }

        $latest = EmployeePayroll::where('employee_profile_id', $employee->id)
            ->orderByDesc('payroll_month')
            ->first();

        // If none exists, calculate for previous/current month as preview
        if ($latest === null) {
            $prevMonth = Carbon::now()->subMonth()->format('Y-m');
            $service = app(PayrollCalculationService::class);
            $latest = $service->calculate($employee, $prevMonth);
        }

        return $latest;
    }

    public function openPayslipModal(): void
    {
        $this->showPayslipModal = true;
    }

    public function closePayslipModal(): void
    {
        $this->showPayslipModal = false;
    }
}
