<?php

declare(strict_types=1);

namespace App\Filament\Pages\Employee;

use App\Models\Hrms\EmployeePayroll;
use App\Models\Hrms\EmployeeProfile;
use App\Services\Hrms\PayrollCalculationService;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\Response;

class MyPayslipsPage extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-currency-rupee';

    protected static ?string $navigationLabel = 'My Payslips';

    protected static ?string $title = 'Salary Statements & Digital Payslips';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.employee.my-payslips-page';

    public ?int $viewingPayrollId = null;

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'employee';
    }

    public function getEmployeeProperty(): ?EmployeeProfile
    {
        return auth()->user()?->employeeProfile;
    }

    public function getPayrollsProperty(): array
    {
        $employee = $this->employee;
        if ($employee === null) {
            return [];
        }

        return EmployeePayroll::where('employee_profile_id', $employee->id)
            ->orderByDesc('payroll_month')
            ->get()
            ->all();
    }

    public function viewPayslip(int $id): void
    {
        $this->viewingPayrollId = $id;
    }

    public function closePayslip(): void
    {
        $this->viewingPayrollId = null;
    }

    public function printPayslip(int $id): Response
    {
        $employee = $this->employee;
        abort_unless($employee !== null, 403, 'Employee profile not found.');

        $payroll = EmployeePayroll::where('id', $id)
            ->where('employee_profile_id', $employee->id)
            ->firstOrFail();

        $service = app(PayrollCalculationService::class);
        $html = $service->generatePayslipHtml($payroll);

        return response($html, 200, ['Content-Type' => 'text/html']);
    }
}
