<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Employee;

use App\Models\Hrms\EmployeeProfile;
use App\Models\Hrms\EmployeeTask;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

class TaskBoardWidget extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.employee.task-board-widget';

    protected int | string | array $columnSpan = ['default' => 12, 'lg' => 8, 'xl' => 8];

    public string $filterStatus = 'pending'; // 'pending', 'completed', 'all'

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

        if ($this->filterStatus === 'pending') {
            $query->whereIn('status', ['todo', 'in_progress']);
        } elseif ($this->filterStatus === 'completed') {
            $query->where('status', 'completed');
        }

        return $query->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
            ->orderBy('due_date')
            ->limit(6)
            ->get()
            ->all();
    }

    public function toggleTaskCompletion(int $taskId): void
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

        if ($task->status === 'completed') {
            $task->status = 'todo';
            $task->progress_percent = 0;
            $task->completed_at = null;
        } else {
            $task->status = 'completed';
            $task->progress_percent = 100;
            $task->completed_at = Carbon::now();

            Notification::make()
                ->title('Task Completed')
                ->body("Great job finishing '{$task->title}'!")
                ->success()
                ->send();
        }

        $task->save();
    }
}
