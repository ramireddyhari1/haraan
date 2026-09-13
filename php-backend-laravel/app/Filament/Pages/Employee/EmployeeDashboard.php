<?php

declare(strict_types=1);

namespace App\Filament\Pages\Employee;

use App\Filament\Widgets\Employee\AnnouncementsWidget;
use App\Filament\Widgets\Employee\AttendanceClockWidget;
use App\Filament\Widgets\Employee\LeaveBalanceWidget;
use App\Filament\Widgets\Employee\PayrollSummaryWidget;
use App\Filament\Widgets\Employee\PerformanceScorecardWidget;
use App\Filament\Widgets\Employee\ShiftScheduleWidget;
use App\Filament\Widgets\Employee\TaskBoardWidget;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class EmployeeDashboard extends BaseDashboard
{
    protected static ?string $title = 'Employee Console';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Dashboard';

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'employee';
    }

    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        return view('filament.pages.employee.dashboard-header');
    }

    public function getHeading(): string | Htmlable
    {
        return '';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return null;
    }

    public function getWidgets(): array
    {
        return [
            AnnouncementsWidget::class,
            AttendanceClockWidget::class,
            ShiftScheduleWidget::class,
            TaskBoardWidget::class,
            LeaveBalanceWidget::class,
            PayrollSummaryWidget::class,
            PerformanceScorecardWidget::class,
        ];
    }

    public function getColumns(): int | array
    {
        return [
            'default' => 12,
            'sm' => 12,
            'md' => 12,
            'lg' => 12,
            'xl' => 12,
        ];
    }
}
