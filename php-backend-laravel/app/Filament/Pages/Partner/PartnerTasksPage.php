<?php

declare(strict_types=1);

namespace App\Filament\Pages\Partner;

use App\Models\Hrms\EmployeeProfile;
use App\Models\Hrms\EmployeeTask;
use App\Models\Venue;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class PartnerTasksPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $title = 'Floor Tasks & Assignments';

    protected static ?string $navigationLabel = 'Floor Tasks';

    protected static string|\UnitEnum|null $navigationGroup = 'Team & HRMS';

    protected static ?int $navigationSort = 9;

    protected string $view = 'filament.pages.partner.tasks';

    public bool $showCreateModal = false;
    public string $taskTitle = '';
    public string $taskDesc = '';
    public ?int $assigneeId = null;
    public string $taskPriority = 'medium';
    public string $taskDueDate = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return Filament::getCurrentPanel()?->getId() === 'partner'
            && $user !== null
            && ! $user->isDeskStaff();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->taskDueDate = Carbon::today()->toDateString();
    }

    public function getStaffProperty(): array
    {
        $partnerId = auth()->user()?->effectivePartnerId();

        return EmployeeProfile::with('user')
            ->where('partner_id', $partnerId)
            ->where('employment_status', 'active')
            ->get()
            ->all();
    }

    public function getTasksProperty(): array
    {
        $partnerId = auth()->user()?->effectivePartnerId();

        return EmployeeTask::with(['employee.user', 'assigner'])
            ->whereHas('employee', fn ($q) => $q->where('partner_id', $partnerId))
            ->orderByDesc('created_at')
            ->get()
            ->all();
    }

    public function openCreateModal(): void
    {
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
    }

    public function createTask(): void
    {
        if (empty($this->taskTitle) || $this->assigneeId === null) {
            Notification::make()->title('Please enter title and select staff member')->danger()->send();
            return;
        }

        EmployeeTask::create([
            'title' => $this->taskTitle,
            'description' => $this->taskDesc,
            'employee_profile_id' => $this->assigneeId,
            'assigned_by' => auth()->id(),
            'priority' => $this->taskPriority,
            'status' => 'todo',
            'due_date' => $this->taskDueDate,
        ]);

        Notification::make()->title('Task Assigned')->success()->send();
        $this->showCreateModal = false;
        $this->taskTitle = '';
        $this->taskDesc = '';
        $this->assigneeId = null;
    }
}
