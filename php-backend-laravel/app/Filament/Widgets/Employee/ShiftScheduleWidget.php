<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Employee;

use App\Models\Hrms\EmployeeProfile;
use App\Models\Hrms\EmployeeShiftRoster;
use App\Services\Hrms\RosterService;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Throwable;

class ShiftScheduleWidget extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.employee.shift-schedule-widget';

    protected int | string | array $columnSpan = ['default' => 12, 'lg' => 4, 'xl' => 4];

    public bool $showSwapModal = false;
    public ?int $selectedColleagueId = null;
    public string $swapReason = '';

    public function getEmployeeProperty(): ?EmployeeProfile
    {
        return auth()->user()?->employeeProfile;
    }

    public function getTodayRosterProperty(): ?EmployeeShiftRoster
    {
        $employee = $this->employee;
        if ($employee === null) {
            return null;
        }

        return EmployeeShiftRoster::with(['shift', 'venue'])
            ->where('employee_profile_id', $employee->id)
            ->whereDate('roster_date', Carbon::today())
            ->first();
    }

    public function getUpcomingRostersProperty(): array
    {
        $employee = $this->employee;
        if ($employee === null) {
            return [];
        }

        return EmployeeShiftRoster::with(['shift', 'venue'])
            ->where('employee_profile_id', $employee->id)
            ->whereDate('roster_date', '>', Carbon::today())
            ->orderBy('roster_date')
            ->limit(4)
            ->get()
            ->all();
    }

    public function getColleaguesProperty(): array
    {
        $employee = $this->employee;
        if ($employee === null) {
            return [];
        }

        return EmployeeProfile::with('user')
            ->where('id', '!=', $employee->id)
            ->where('employment_status', 'active')
            ->get()
            ->all();
    }

    public function openSwapModal(): void
    {
        $this->showSwapModal = true;
    }

    public function closeSwapModal(): void
    {
        $this->showSwapModal = false;
    }

    public function submitSwapRequest(): void
    {
        $roster = $this->todayRoster;
        if ($roster === null || $this->selectedColleagueId === null) {
            Notification::make()->title('Please select a colleague')->danger()->send();
            return;
        }

        $targetEmp = EmployeeProfile::find($this->selectedColleagueId);
        if ($targetEmp === null) {
            return;
        }

        try {
            $service = app(RosterService::class);
            $service->requestSwap($roster, $targetEmp, $this->swapReason);

            Notification::make()
                ->title('Swap Request Submitted')
                ->body('Your request has been routed to your supervisor for approval.')
                ->success()
                ->send();

            $this->showSwapModal = false;
            $this->swapReason = '';
            $this->selectedColleagueId = null;
        } catch (Throwable $e) {
            Notification::make()->title('Failed to Submit Request')->body($e->getMessage())->danger()->send();
        }
    }
}
