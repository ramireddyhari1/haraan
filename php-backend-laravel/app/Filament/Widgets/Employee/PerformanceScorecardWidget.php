<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Employee;

use App\Models\Hrms\EmployeeKpi;
use App\Models\Hrms\EmployeeProfile;
use Filament\Widgets\Widget;

class PerformanceScorecardWidget extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.employee.performance-scorecard-widget';

    protected int | string | array $columnSpan = ['default' => 12, 'lg' => 6, 'xl' => 6];

    public function getEmployeeProperty(): ?EmployeeProfile
    {
        return auth()->user()?->employeeProfile;
    }

    public function getLatestKpiProperty(): ?EmployeeKpi
    {
        $employee = $this->employee;
        if ($employee === null) {
            return null;
        }

        return EmployeeKpi::with('reviewer')
            ->where('employee_profile_id', $employee->id)
            ->orderByDesc('period')
            ->first();
    }
}
