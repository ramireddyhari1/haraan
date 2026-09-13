<?php

declare(strict_types=1);

namespace App\Filament\Resources\Hrms\Pages;

use App\Filament\Resources\Hrms\EmployeePayrollResource;
use App\Models\Hrms\EmployeePayroll;
use App\Models\Hrms\EmployeeProfile;
use App\Services\Hrms\PayrollCalculationService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListEmployeePayrolls extends ListRecords
{
    protected static string $resource = EmployeePayrollResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runPayroll')
                ->label('Run Monthly Payroll')
                ->icon('heroicon-m-calculator')
                ->color('primary')
                ->form([
                    TextInput::make('month')
                        ->label('Payroll Month (YYYY-MM)')
                        ->default(now()->format('Y-m'))
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $month = $data['month'];
                    $employees = EmployeeProfile::where('employment_status', 'active')->get();
                    $service = app(PayrollCalculationService::class);
                    $count = 0;

                    foreach ($employees as $emp) {
                        $service->calculate($emp, $month);
                        $count++;
                    }

                    Notification::make()
                        ->title("Payroll Run Completed")
                        ->body("Calculated salaries and generated payslips for {$count} active employees.")
                        ->success()
                        ->send();
                }),

            Action::make('exportCsv')
                ->label('Export Payroll CSV')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->exportPayrollCsv()),
        ];
    }

    private function exportPayrollCsv(): StreamedResponse
    {
        $headers = ['Month', 'Employee Code', 'Employee Name', 'Department', 'Working Days', 'Present Days', 'Gross Pay', 'PF', 'ESI', 'PT', 'TDS', 'Total Deductions', 'Net Salary', 'Payslip Ref', 'Status'];

        return response()->streamDownload(function () use ($headers): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);

            EmployeePayroll::with(['employee.user', 'employee.department'])
                ->orderByDesc('payroll_month')
                ->chunk(200, function ($rows) use ($out): void {
                    foreach ($rows as $p) {
                        fputcsv($out, [
                            $p->payroll_month,
                            $p->employee?->employee_code,
                            $p->employee?->full_name,
                            $p->employee?->department?->name ?? 'General',
                            $p->working_days,
                            $p->present_days,
                            $p->gross_earnings,
                            $p->pf_deduction,
                            $p->esi_deduction,
                            $p->professional_tax,
                            $p->tds_deduction,
                            $p->total_deductions,
                            $p->net_salary,
                            $p->payslip_number,
                            $p->status,
                        ]);
                    }
                });

            fclose($out);
        }, 'payroll-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }
}
