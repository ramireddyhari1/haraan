<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Resources\Hrms\EmployeeLeaveResource;
use App\Models\Hrms\EmployeeAttendance;
use App\Models\Hrms\EmployeeAttendanceRegularisation;
use App\Models\Hrms\EmployeeLeaveRequest;
use App\Models\Hrms\EmployeePayroll;
use App\Models\Hrms\EmployeeProfile;
use App\Models\Hrms\EmployeeShiftSwap;
use App\Support\FormatsSummaries;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The front page of Workforce & HR: who is in today, who is off, and what is waiting on a
 * manager. There were fourteen HR lists and nowhere that answered those three questions.
 *
 * "Today" is the date attendance rows are written with (AttendanceService uses
 * Carbon::today()), so the counts line up with the attendance list.
 */
class WorkforceToday extends Page
{
    use FormatsSummaries;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sun';

    protected static string|\UnitEnum|null $navigationGroup = 'Workforce & HR';

    protected static ?string $title = 'Workforce today';

    protected static ?string $navigationLabel = 'Today';

    protected static ?int $navigationSort = -10;

    protected string $view = 'filament.pages.workforce-today';

    /** Same gate as the HR resources. */
    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'control'
            && (auth()->user()?->isSuperAdmin() || auth()->user()?->hasRoleEither(['OPS', 'FINANCE']));
    }

    private const PENDING_LEAVE = ['pending', 'tier1_pending', 'tier2_pending'];

    /** @return list<array<string, mixed>> */
    public function getPanels(): array
    {
        return [$this->today(), $this->decisions(), $this->payroll()];
    }

    private function working()
    {
        // employment_status casing is mixed in older rows; on_leave is still on the books.
        return EmployeeProfile::query()->whereIn(DB::raw('lower(employment_status)'), ['active', 'probation', 'on_leave']);
    }

    private function today(): array
    {
        $today = Carbon::today();
        $staff = $this->working()->with('user:id,name')->get();

        $in = EmployeeAttendance::query()->whereDate('date', $today)->whereNotNull('clock_in_at')
            ->whereIn('employee_profile_id', $staff->pluck('id'))->get()->keyBy('employee_profile_id');
        $offIds = EmployeeLeaveRequest::query()->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)
            ->pluck('employee_profile_id')->unique();

        $stillOut = $in->whereNull('clock_out_at')->count();
        $notIn = $staff->reject(fn (EmployeeProfile $e): bool => $in->has($e->id) || $offIds->contains($e->id));

        return [
            'title' => 'Today',
            'window' => $today->format('l, j M'),
            'stats' => [
                ['label' => 'On the books', 'value' => number_format($staff->count())],
                ['label' => 'Clocked in', 'value' => number_format($in->count()), 'sub' => $stillOut . ' still on shift', 'tone' => $in->isNotEmpty() ? 'good' : null],
                ['label' => 'On leave', 'value' => number_format($offIds->count()), 'sub' => 'approved for today'],
                ['label' => 'Not in yet', 'value' => number_format($notIn->count()), 'sub' => 'no clock-in, not on leave', 'tone' => $notIn->isNotEmpty() ? 'warn' : null],
            ],
            'list' => [
                'title' => 'Not in yet',
                'rows' => $notIn->take(8)->map(fn (EmployeeProfile $e): array => [
                    'primary' => $e->full_name,
                    'secondary' => $e->employee_code,
                    'trailing' => '',
                ])->values()->all(),
                'empty' => 'Everyone on the books is in or on approved leave.',
                'illo' => 'whistle',
            ],
        ];
    }

    private function decisions(): array
    {
        $leave = EmployeeLeaveRequest::query()->whereIn('status', self::PENDING_LEAVE)
            ->with(['employee.user:id,name', 'leaveType'])->orderBy('start_date')->get();
        $swaps = EmployeeShiftSwap::query()->where('status', 'pending')->count();
        $fixes = EmployeeAttendanceRegularisation::query()->whereIn('status', self::PENDING_LEAVE)->count();

        return [
            'title' => 'Waiting on a manager',
            'stats' => [
                ['label' => 'Leave requests', 'value' => number_format($leave->count()), 'tone' => $leave->isNotEmpty() ? 'warn' : null],
                ['label' => 'Shift swaps', 'value' => number_format($swaps), 'tone' => $swaps > 0 ? 'warn' : null],
                ['label' => 'Attendance fixes', 'value' => number_format($fixes), 'sub' => 'missed or wrong clock-ins', 'tone' => $fixes > 0 ? 'warn' : null],
            ],
            'list' => [
                'title' => 'Leave to decide, soonest first',
                'rows' => $leave->take(6)->map(fn (EmployeeLeaveRequest $r): array => [
                    'primary' => $r->employee?->full_name ?? 'Employee',
                    'secondary' => collect([
                        $r->leaveType?->name,
                        $r->start_date?->format('j M') . ($r->end_date && ! $r->end_date->isSameDay($r->start_date) ? ' – ' . $r->end_date->format('j M') : ''),
                    ])->filter()->join(' · '),
                    'trailing' => rtrim(rtrim(number_format((float) $r->total_days, 1), '0'), '.') . ' ' . str('day')->plural((float) $r->total_days),
                    'url' => EmployeeLeaveResource::getUrl('index'),
                ])->all(),
                'empty' => 'Nothing is waiting for a decision.',
                'illo' => 'clipboard',
            ],
        ];
    }

    private function payroll(): array
    {
        $month = Carbon::today()->format('Y-m');
        $rows = EmployeePayroll::query()->where('payroll_month', $month)->get();
        $paid = $rows->filter(fn ($r): bool => strtolower((string) $r->status) === 'paid');
        $staff = $this->working()->count();

        return [
            'title' => 'Payroll · ' . Carbon::today()->format('F'),
            'stats' => [
                ['label' => 'Payslips made', 'value' => number_format($rows->count()) . ' of ' . number_format($staff), 'tone' => $rows->count() < $staff ? 'warn' : null, 'sub' => $rows->count() < $staff ? ($staff - $rows->count()) . ' still to run' : 'everyone covered'],
                ['label' => 'Net to pay', 'value' => self::inr((float) $rows->sum('net_salary'))],
                ['label' => 'Paid', 'value' => self::inr((float) $paid->sum('net_salary')), 'sub' => $paid->count() . ' payslips', 'tone' => $paid->isNotEmpty() ? 'good' : null],
                ['label' => 'Still to pay', 'value' => self::inr((float) $rows->sum('net_salary') - (float) $paid->sum('net_salary'))],
            ],
            'split' => [
                'label' => 'Payslips by status',
                'parts' => self::parts($rows->countBy(fn ($r): string => ucfirst(strtolower((string) $r->status)))->all(), money: false),
            ],
        ];
    }
}
