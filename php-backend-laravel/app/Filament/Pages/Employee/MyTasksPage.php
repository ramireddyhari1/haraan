<?php

declare(strict_types=1);

namespace App\Filament\Pages\Employee;

use App\Models\Hrms\EmployeeProfile;
use App\Models\Hrms\EmployeeTask;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class MyTasksPage extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-check-circle';

    protected static ?string $navigationLabel = 'My Tasks';

    protected static ?string $title = 'Task Board & Checklists';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.employee.my-tasks-page';

    public string $currentFilter = 'all'; // all, todo, in_progress, completed

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'employee';
    }

    public function getEmployeeProperty(): ?EmployeeProfile
    {
        return auth()->user()?->employeeProfile;
    }

    public function getTasksProperty(): array
    {
        $employee = $this->employee;
        if ($employee === null) {
            return [];
        }

        $query = EmployeeTask::with('assigner')
            ->where('employee_profile_id', $employee->id);

        if ($this->currentFilter === 'todo') {
            $query->where('status', 'todo');
        } elseif ($this->currentFilter === 'in_progress') {
            $query->where('status', 'in_progress');
        } elseif ($this->currentFilter === 'completed') {
            $query->where('status', 'completed');
        }

        return $query->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
            ->orderBy('due_date')
            ->get()
            ->all();
    }

    public function updateStatus(int $taskId, string $status): void
    {
        $employee = $this->employee;
        if ($employee === null) {
            return;
        }

        $task = EmployeeTask::where('id', $taskId)
            ->where('employee_profile_id', $employee->id)
            ->first();

        if ($task === null) {
            return;
        }

        $task->status = $status;
        if ($status === 'completed') {
            $task->progress_percent = 100;
            $task->completed_at = Carbon::now();
            Notification::make()->title('Task Completed!')->success()->send();
        } elseif ($status === 'in_progress') {
            $task->progress_percent = max(25, $task->progress_percent);
            $task->completed_at = null;
        } else {
            $task->progress_percent = 0;
            $task->completed_at = null;
        }

        $task->save();
    }
}
