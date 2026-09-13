<?php

declare(strict_types=1);

namespace App\Filament\Pages\Employee;

use App\Models\Hrms\EmployeeLeaveBalance;
use App\Models\Hrms\EmployeeLeaveRequest;
use App\Models\Hrms\EmployeeLeaveType;
use App\Models\Hrms\EmployeeProfile;
use App\Services\Hrms\LeaveService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Throwable;

class MyLeavesPage extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrow-right-start-on-rectangle';

    protected static ?string $navigationLabel = 'Leave Center';

    protected static ?string $title = 'Leave Management & Quota Ledger';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.employee.my-leaves-page';

    public bool $showApplyModal = false;
    public ?int $selectedLeaveTypeId = null;
    public string $startDate = '';
    public string $endDate = '';
    public string $leaveReason = '';

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'employee';
    }

    public function mount(): void
    {
        $this->startDate = Carbon::today()->toDateString();
        $this->endDate = Carbon::today()->toDateString();
    }

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

        return EmployeeLeaveBalance::with('leaveType')
            ->where('employee_profile_id', $employee->id)
            ->where('year', $year)
            ->get()
            ->all();
    }

    public function getRequestsProperty(): array
    {
        $employee = $this->employee;
        if ($employee === null) {
            return [];
        }

        return EmployeeLeaveRequest::with(['leaveType', 'approver'])
            ->where('employee_profile_id', $employee->id)
            ->orderByDesc('created_at')
            ->get()
            ->all();
    }

    public function getLeaveTypesProperty(): array
    {
        return EmployeeLeaveType::where('is_active', true)->get()->all();
    }

    public function openApplyModal(): void
    {
        $this->showApplyModal = true;
    }

    public function closeApplyModal(): void
    {
        $this->showApplyModal = false;
    }

    public function submitApplication(): void
    {
        $employee = $this->employee;
        if ($employee === null || $this->selectedLeaveTypeId === null) {
            Notification::make()->title('Please select category')->danger()->send();
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

            Notification::make()->title('Leave Request Submitted')->success()->send();
            $this->showApplyModal = false;
            $this->leaveReason = '';
            $this->selectedLeaveTypeId = null;
        } catch (Throwable $e) {
            Notification::make()->title('Application Failed')->body($e->getMessage())->danger()->send();
        }
    }
}
