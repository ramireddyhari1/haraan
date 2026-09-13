<?php

declare(strict_types=1);

namespace App\Filament\Pages\Employee;

use App\Models\Hrms\EmployeeAttendance;
use App\Models\Hrms\EmployeeAttendanceRegularisation;
use App\Models\Hrms\EmployeeProfile;
use App\Services\Hrms\AttendanceService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Throwable;

class MyAttendancePage extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationLabel = 'Attendance Log';

    protected static ?string $title = 'Attendance History & Punch Logs';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.employee.my-attendance-page';

    public string $selectedMonth = '';

    // Regularisation Modal
    public bool $showRegularisationModal = false;
    public string $regDate = '';
    public string $regClockIn = '09:00';
    public string $regClockOut = '18:00';
    public string $regCategory = 'missed_punch';
    public string $regReason = '';

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'employee';
    }

    public function mount(): void
    {
        $this->selectedMonth = Carbon::now()->format('Y-m');
        $this->regDate = Carbon::yesterday()->toDateString();
    }

    public function getEmployeeProperty(): ?EmployeeProfile
    {
        return auth()->user()?->employeeProfile;
    }

    public function getAttendancesProperty(): array
    {
        $employee = $this->employee;
        if ($employee === null) {
            return [];
        }

        $startDate = Carbon::parse("{$this->selectedMonth}-01")->startOfMonth();
        $endDate = (clone $startDate)->endOfMonth();

        return EmployeeAttendance::with('shift')
            ->where('employee_profile_id', $employee->id)
            ->whereDate('date', '>=', $startDate->toDateString())
            ->whereDate('date', '<=', $endDate->toDateString())
            ->orderByDesc('date')
            ->get()
            ->all();
    }

    public function getRegularisationsByDateProperty(): array
    {
        $employee = $this->employee;
        if ($employee === null) {
            return [];
        }

        $startDate = Carbon::parse("{$this->selectedMonth}-01")->startOfMonth();
        $endDate = (clone $startDate)->endOfMonth();

        return EmployeeAttendanceRegularisation::where('employee_profile_id', $employee->id)
            ->whereDate('date', '>=', $startDate->toDateString())
            ->whereDate('date', '<=', $endDate->toDateString())
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->date)->toDateString())
            ->all();
    }

    public function openRegularisationModal(?string $date = null): void
    {
        $this->regDate = $date ?? Carbon::yesterday()->toDateString();
        $this->regClockIn = '09:00';
        $this->regClockOut = '18:00';
        $this->regCategory = 'missed_punch';
        $this->regReason = '';
        $this->showRegularisationModal = true;
    }

    public function closeRegularisationModal(): void
    {
        $this->showRegularisationModal = false;
    }

    public function submitRegularisation(): void
    {
        $employee = $this->employee;
        if ($employee === null) {
            return;
        }

        if (trim($this->regReason) === '') {
            Notification::make()->title('Please provide an explanation/reason')->danger()->send();
            return;
        }

        try {
            $in = Carbon::parse("{$this->regDate} {$this->regClockIn}");
            $out = Carbon::parse("{$this->regDate} {$this->regClockOut}");

            $service = app(AttendanceService::class);
            $service->requestRegularisation(
                $employee,
                Carbon::parse($this->regDate),
                $in,
                $out,
                $this->regCategory,
                $this->regReason
            );

            $this->closeRegularisationModal();
            Notification::make()->title('Regularisation Request Submitted')->body('Your supervisor will review this request.')->success()->send();
        } catch (Throwable $e) {
            Notification::make()->title('Request Failed')->body($e->getMessage())->danger()->send();
        }
    }
}
