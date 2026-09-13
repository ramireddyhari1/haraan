<?php

declare(strict_types=1);

namespace App\Filament\Pages\Employee;

use App\Models\Hrms\EmployeeAttendance;
use App\Models\Hrms\EmployeeAttendanceRegularisation;
use App\Models\Hrms\EmployeeLeaveRequest;
use App\Models\Hrms\EmployeeProfile;
use App\Models\Hrms\EmployeeShiftRoster;
use App\Models\Hrms\EmployeeShiftSwap;
use App\Models\Hrms\EmployeeTask;
use App\Models\Hrms\WorkforceAuditLedger;
use App\Services\Hrms\AttendanceService;
use App\Services\Hrms\LeaveService;
use App\Services\Hrms\RosterService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Throwable;

class MyTeamPage extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'My Team (MSS)';

    protected static ?string $title = 'Manager Self-Service (MSS) — Team Operations';

    protected static ?string $slug = 'my-team';

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.pages.employee.my-team-page';

    public string $activeTab = 'overview'; // overview, leaves, regularisations, swaps, tasks

    // Delegate Task modal
    public bool $showTaskModal = false;
    public ?int $selectedEmployeeId = null;
    public string $taskTitle = '';
    public string $taskPriority = 'medium';
    public string $taskDueDate = '';
    public string $taskDescription = '';

    public static function canAccess(): bool
    {
        if (Filament::getCurrentPanel()?->getId() !== 'employee') {
            return false;
        }

        $userId = auth()->id();
        if (! $userId) {
            return false;
        }

        return EmployeeProfile::where('reporting_manager_id', $userId)->exists();
    }

    public function mount(): void
    {
        $this->taskDueDate = Carbon::today()->addDay()->toDateString();
    }

    /** All direct reportees */
    public function getReporteesProperty(): array
    {
        $userId = auth()->id();
        if (! $userId) {
            return [];
        }

        return EmployeeProfile::with(['user', 'department', 'designation', 'venue'])
            ->where('reporting_manager_id', $userId)
            ->whereIn('employment_status', ['active', 'ACTIVE'])
            ->get()
            ->all();
    }

    /** IDs of direct reportees */
    public function getReporteeIdsProperty(): array
    {
        return array_column($this->reportees, 'id');
    }

    /** Team's live attendance status for today */
    public function getTeamStatusProperty(): array
    {
        $today = Carbon::today()->toDateString();
        $reporteeIds = $this->reporteeIds;

        if (empty($reporteeIds)) {
            return [];
        }

        $attendances = EmployeeAttendance::whereIn('employee_profile_id', $reporteeIds)
            ->whereDate('date', $today)
            ->get()
            ->keyBy('employee_profile_id');

        $rosters = EmployeeShiftRoster::with('shift')
            ->whereIn('employee_profile_id', $reporteeIds)
            ->whereDate('roster_date', $today)
            ->get()
            ->keyBy('employee_profile_id');

        $result = [];
        foreach ($this->reportees as $emp) {
            $att = $attendances->get($emp->id);
            $rost = $rosters->get($emp->id);

            $status = 'unscheduled';
            $details = 'No shift scheduled';

            if ($att !== null) {
                if ($att->isCurrentlyOnBreak()) {
                    $status = 'on_break';
                    $details = 'On Break (' . ($att->shift?->name ?? 'Standard') . ')';
                } elseif ($att->isCurrentlyClockedIn()) {
                    $status = 'active';
                    $details = 'Clocked In @ ' . $att->clock_in_at->format('h:i A') . ' (' . $att->formatted_work_duration . ')';
                } elseif ($att->clock_out_at !== null) {
                    $status = 'concluded';
                    $details = 'Shift Concluded (' . $att->formatted_work_duration . ')';
                } elseif ($att->status === 'on_leave') {
                    $status = 'on_leave';
                    $details = 'Approved Leave';
                } else {
                    $status = 'absent';
                    $details = 'Absent / Missing Punch';
                }
            } elseif ($rost !== null) {
                $status = 'scheduled';
                $details = 'Scheduled: ' . ($rost->shift?->name ?? 'Shift') . ' (' . ($rost->shift?->formatted_timing ?? 'Today') . ')';
            }

            $result[] = [
                'employee' => $emp,
                'status' => $status,
                'details' => $details,
                'attendance' => $att,
                'roster' => $rost,
            ];
        }

        return $result;
    }

    /** Pending leave requests from direct reportees */
    public function getPendingLeavesProperty(): array
    {
        $reporteeIds = $this->reporteeIds;
        if (empty($reporteeIds)) {
            return [];
        }

        return EmployeeLeaveRequest::with(['employee.user', 'leaveType'])
            ->whereIn('employee_profile_id', $reporteeIds)
            ->whereIn('status', ['pending', 'tier1_pending', 'tier2_pending', 'escalated'])
            ->orderBy('created_at')
            ->get()
            ->all();
    }

    /** Pending regularisations from direct reportees */
    public function getPendingRegularisationsProperty(): array
    {
        $reporteeIds = $this->reporteeIds;
        if (empty($reporteeIds)) {
            return [];
        }

        return EmployeeAttendanceRegularisation::with(['employee.user'])
            ->whereIn('employee_profile_id', $reporteeIds)
            ->whereIn('status', ['pending', 'tier1_pending', 'tier2_pending', 'escalated'])
            ->orderBy('date')
            ->get()
            ->all();
    }

    /** Pending shift swaps from direct reportees */
    public function getPendingSwapsProperty(): array
    {
        $reporteeIds = $this->reporteeIds;
        if (empty($reporteeIds)) {
            return [];
        }

        return EmployeeShiftSwap::with(['requestorRoster.employee.user', 'requestorRoster.shift', 'targetEmployee.user', 'targetRoster.shift'])
            ->whereHas('requestorRoster', function ($q) use ($reporteeIds): void {
                $q->whereIn('employee_profile_id', $reporteeIds);
            })
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get()
            ->all();
    }

    /** Live operational timeline events for the team and venue */
    public function getActivityEventsProperty(): array
    {
        $reporteeUserIds = collect($this->reportees)->pluck('user_id')->filter()->all();
        if (empty($reporteeUserIds)) {
            return [];
        }

        $relevantUserIds = array_unique(array_merge($reporteeUserIds, [auth()->id()]));

        return WorkforceAuditLedger::with(['actor', 'venue'])
            ->whereIn('actor_id', $relevantUserIds)
            ->orderByDesc('occurred_at')
            ->limit(35)
            ->get()
            ->all();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    // Leave Actions
    public function approveLeave(int $requestId, ?string $notes = null): void
    {
        try {
            $leave = EmployeeLeaveRequest::whereIn('employee_profile_id', $this->reporteeIds)
                ->where('id', $requestId)
                ->firstOrFail();

            $service = app(LeaveService::class);
            $service->approve($leave, auth()->user(), $notes ?? 'Approved by Team Manager');

            Notification::make()->title('Leave Request Approved')->success()->send();
        } catch (Throwable $e) {
            Notification::make()->title('Approval Failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function rejectLeave(int $requestId, ?string $notes = null): void
    {
        try {
            $leave = EmployeeLeaveRequest::whereIn('employee_profile_id', $this->reporteeIds)
                ->where('id', $requestId)
                ->firstOrFail();

            $service = app(LeaveService::class);
            $service->reject($leave, auth()->user(), $notes ?? 'Rejected by Team Manager');

            Notification::make()->title('Leave Request Rejected')->info()->send();
        } catch (Throwable $e) {
            Notification::make()->title('Rejection Failed')->body($e->getMessage())->danger()->send();
        }
    }

    // Regularisation Actions
    public function approveRegularisation(int $regId, ?string $notes = null): void
    {
        try {
            $reg = EmployeeAttendanceRegularisation::whereIn('employee_profile_id', $this->reporteeIds)
                ->where('id', $regId)
                ->firstOrFail();

            $service = app(AttendanceService::class);
            $service->approveRegularisation($reg, auth()->user(), $notes ?? 'Approved by Manager');

            Notification::make()->title('Attendance Regularisation Approved')->success()->send();
        } catch (Throwable $e) {
            Notification::make()->title('Approval Failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function rejectRegularisation(int $regId, ?string $notes = null): void
    {
        try {
            $reg = EmployeeAttendanceRegularisation::whereIn('employee_profile_id', $this->reporteeIds)
                ->where('id', $regId)
                ->firstOrFail();

            $service = app(AttendanceService::class);
            $service->rejectRegularisation($reg, auth()->user(), $notes ?? 'Rejected by Manager');

            Notification::make()->title('Attendance Regularisation Rejected')->info()->send();
        } catch (Throwable $e) {
            Notification::make()->title('Rejection Failed')->body($e->getMessage())->danger()->send();
        }
    }

    // Shift Swap Actions
    public function approveSwap(int $swapId): void
    {
        try {
            $swap = EmployeeShiftSwap::whereHas('requestorRoster', function ($q): void {
                $q->whereIn('employee_profile_id', $this->reporteeIds);
            })->where('id', $swapId)->firstOrFail();

            $service = app(RosterService::class);
            $service->approveSwap($swap, auth()->user());

            Notification::make()->title('Shift Swap Approved & Roster Updated')->success()->send();
        } catch (Throwable $e) {
            Notification::make()->title('Swap Approval Failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function rejectSwap(int $swapId, ?string $notes = null): void
    {
        try {
            $swap = EmployeeShiftSwap::whereHas('requestorRoster', function ($q): void {
                $q->whereIn('employee_profile_id', $this->reporteeIds);
            })->where('id', $swapId)->firstOrFail();

            $service = app(RosterService::class);
            $service->rejectSwap($swap, auth()->user(), $notes ?? 'Rejected by Team Manager');

            Notification::make()->title('Shift Swap Rejected')->info()->send();
        } catch (Throwable $e) {
            Notification::make()->title('Rejection Failed')->body($e->getMessage())->danger()->send();
        }
    }

    // Task Delegation Modal
    public function openTaskModal(?int $employeeId = null): void
    {
        $this->selectedEmployeeId = $employeeId ?? ($this->reportees[0]?->id ?? null);
        $this->taskTitle = '';
        $this->taskDescription = '';
        $this->taskPriority = 'medium';
        $this->showTaskModal = true;
    }

    public function closeTaskModal(): void
    {
        $this->showTaskModal = false;
    }

    public function submitTask(): void
    {
        if (! $this->selectedEmployeeId || trim($this->taskTitle) === '') {
            Notification::make()->title('Task Title & Employee Required')->danger()->send();
            return;
        }

        try {
            $emp = EmployeeProfile::whereIn('id', $this->reporteeIds)
                ->where('id', $this->selectedEmployeeId)
                ->firstOrFail();

            EmployeeTask::create([
                'title' => $this->taskTitle,
                'description' => $this->taskDescription,
                'employee_profile_id' => $emp->id,
                'assigned_by' => auth()->id(),
                'venue_id' => $emp->venue_id,
                'priority' => $this->taskPriority,
                'status' => 'todo',
                'due_date' => $this->taskDueDate ?: Carbon::tomorrow()->toDateString(),
            ]);

            $this->closeTaskModal();
            Notification::make()->title('Task Assigned Successfully')->success()->send();
        } catch (Throwable $e) {
            Notification::make()->title('Failed to Assign Task')->body($e->getMessage())->danger()->send();
        }
    }
}
