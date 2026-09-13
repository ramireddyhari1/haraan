<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Employee;

use App\Models\Hrms\EmployeeLeaveBalance;
use App\Models\Hrms\EmployeeLeaveType;
use App\Models\Hrms\EmployeeProfile;
use App\Services\Hrms\LeaveService;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Throwable;

class LeaveBalanceWidget extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.employee.leave-balance-widget';

    protected int | string | array $columnSpan = ['default' => 12, 'lg' => 4, 'xl' => 4];

    public bool $showApplyModal = false;
    public ?int $selectedLeaveTypeId = null;
    public string $startDate = '';
    public string $endDate = '';
    public string $leaveReason = '';

    public function getEmployeeProperty(): ?EmployeeProfile
    {
        return auth()->user()?->employeeProfile;
    }

    public function getBalancesProperty(): array
    {
        $employee = $this->employee;
        if ($employee === null) {
            return [];
        }

        $year = (int) Carbon::now()->year;

        // Ensure leave balances exist for active types
        $types = EmployeeLeaveType::where('is_active', true)->get();
        foreach ($types as $type) {
            EmployeeLeaveBalance::firstOrCreate(
                [
                    'employee_profile_id' => $employee->id,
                    'employee_leave_type_id' => $type->id,
                    'year' => $year,
                ],
                [
                    'allocated_days' => $type->annual_quota,
                    'used_days' => 0,
                    'pending_days' => 0,
                    'remaining_days' => $type->annual_quota,
                ]
            );
        }

        return EmployeeLeaveBalance::with('leaveType')
            ->where('employee_profile_id', $employee->id)
            ->where('year', $year)
            ->get()
            ->all();
    }

    public function getLeaveTypesProperty(): array
    {
        return EmployeeLeaveType::where('is_active', true)->get()->all();
    }

    public function openApplyModal(): void
    {
        $this->startDate = Carbon::today()->toDateString();
        $this->endDate = Carbon::today()->toDateString();
        $this->showApplyModal = true;
    }

    public function closeApplyModal(): void
    {
        $this->showApplyModal = false;
    }

    public function submitLeaveApplication(): void
    {
        $employee = $this->employee;
        if ($employee === null || $this->selectedLeaveTypeId === null) {
            Notification::make()->title('Please select a leave category')->danger()->send();
            return;
        }

        $type = EmployeeLeaveType::find($this->selectedLeaveTypeId);
        if ($type === null) {
            return;
        }

        try {
            $service = app(LeaveService::class);
            $service->apply(
                $employee,
                $type,
                Carbon::parse($this->startDate),
                Carbon::parse($this->endDate),
                $this->leaveReason
            );

            Notification::make()
                ->title('Leave Request Submitted')
                ->body('Your application is pending supervisor review.')
                ->success()
                ->send();

            $this->showApplyModal = false;
            $this->leaveReason = '';
            $this->selectedLeaveTypeId = null;
        } catch (Throwable $e) {
            Notification::make()->title('Application Failed')->body($e->getMessage())->danger()->send();
        }
    }
}
