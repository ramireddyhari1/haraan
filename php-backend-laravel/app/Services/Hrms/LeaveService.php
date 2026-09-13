<?php

declare(strict_types=1);

namespace App\Services\Hrms;

use App\Models\Hrms\EmployeeAttendance;
use App\Models\Hrms\EmployeeLeaveBalance;
use App\Models\Hrms\EmployeeLeaveRequest;
use App\Models\Hrms\EmployeeLeaveType;
use App\Models\Hrms\EmployeeProfile;
use App\Models\Hrms\HolidayCalendar;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LeaveService
{
    /**
     * Compute working days between two dates, excluding declared holidays.
     */
    public function computeWorkingDays(Carbon $start, Carbon $end, ?int $venueId = null): float
    {
        $days = 0.0;
        $curr = clone $start;

        $holidays = HolidayCalendar::whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->where(function ($query) use ($venueId): void {
                $query->whereNull('applicable_venue_id');
                if ($venueId !== null) {
                    $query->orWhere('applicable_venue_id', $venueId);
                }
            })
            ->pluck('date')
            ->map(fn ($d): string => Carbon::parse($d)->toDateString())
            ->flip()
            ->all();

        while ($curr->lessThanOrEqualTo($end)) {
            $dateStr = $curr->toDateString();
            if (! isset($holidays[$dateStr])) {
                $days += 1.0;
            }
            $curr->addDay();
        }

        return $days;
    }

    /**
     * Apply for leave. Validates balance and reserves pending days.
     */
    public function apply(
        EmployeeProfile $employee,
        EmployeeLeaveType $leaveType,
        Carbon $startDate,
        Carbon $endDate,
        string $reason
    ): EmployeeLeaveRequest {
        if ($endDate->lessThan($startDate)) {
            throw new RuntimeException("End date cannot be earlier than start date.");
        }

        $totalDays = $this->computeWorkingDays($startDate, $endDate, $employee->venue_id);
        if ($totalDays <= 0) {
            throw new RuntimeException("Selected date range contains no working days (all dates are holidays).");
        }

        $year = (int) $startDate->year;

        return DB::transaction(function () use ($employee, $leaveType, $startDate, $endDate, $totalDays, $reason, $year): EmployeeLeaveRequest {
            // Find or create leave balance
            $balance = EmployeeLeaveBalance::firstOrCreate(
                [
                    'employee_profile_id' => $employee->id,
                    'employee_leave_type_id' => $leaveType->id,
                    'year' => $year,
                ],
                [
                    'allocated_days' => $leaveType->annual_quota,
                    'used_days' => 0,
                    'pending_days' => 0,
                    'remaining_days' => $leaveType->annual_quota,
                ]
            );

            // If paid leave, check remaining quota
            if ($leaveType->is_paid && ($balance->remaining_days < $totalDays)) {
                throw new RuntimeException("Insufficient leave balance. Available: {$balance->remaining_days} days, Requested: {$totalDays} days.");
            }

            // Reserve pending days
            $balance->pending_days += $totalDays;
            $balance->remaining_days = max(0, $balance->remaining_days - $totalDays);
            $balance->save();

            return EmployeeLeaveRequest::create([
                'employee_profile_id' => $employee->id,
                'employee_leave_type_id' => $leaveType->id,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'total_days' => $totalDays,
                'reason' => $reason,
                'status' => 'pending',
            ]);
        });
    }

    /**
     * Approve a leave request.
     */
    public function approve(EmployeeLeaveRequest $request, User $approver, ?string $notes = null): void
    {
        if ($request->status !== 'pending') {
            throw new RuntimeException("Leave request is not in pending status.");
        }

        DB::transaction(function () use ($request, $approver, $notes): void {
            $year = (int) Carbon::parse($request->start_date)->year;

            $balance = EmployeeLeaveBalance::where('employee_profile_id', $request->employee_profile_id)
                ->where('employee_leave_type_id', $request->employee_leave_type_id)
                ->where('year', $year)
                ->first();

            if ($balance !== null) {
                $balance->pending_days = max(0, $balance->pending_days - $request->total_days);
                $balance->used_days += $request->total_days;
                $balance->save();
            }

            $request->status = 'approved';
            $request->approver_id = $approver->id;
            $request->approver_notes = $notes;
            $request->approved_at = Carbon::now();
            $request->save();

            // Mark attendance records as on_leave
            $curr = Carbon::parse($request->start_date);
            $end = Carbon::parse($request->end_date);

            while ($curr->lessThanOrEqualTo($end)) {
                EmployeeAttendance::updateOrCreate(
                    [
                        'employee_profile_id' => $request->employee_profile_id,
                        'date' => $curr->toDateString(),
                    ],
                    [
                        'status' => 'on_leave',
                        'admin_notes' => 'Leave approved: ' . $request->leaveType?->name,
                    ]
                );
                $curr->addDay();
            }
        });
    }

    /**
     * Reject a leave request and return reserved days.
     */
    public function reject(EmployeeLeaveRequest $request, User $approver, ?string $notes = null): void
    {
        if ($request->status !== 'pending') {
            throw new RuntimeException("Leave request is not in pending status.");
        }

        DB::transaction(function () use ($request, $approver, $notes): void {
            $year = (int) Carbon::parse($request->start_date)->year;

            $balance = EmployeeLeaveBalance::where('employee_profile_id', $request->employee_profile_id)
                ->where('employee_leave_type_id', $request->employee_leave_type_id)
                ->where('year', $year)
                ->first();

            if ($balance !== null) {
                $balance->pending_days = max(0, $balance->pending_days - $request->total_days);
                $balance->remaining_days += $request->total_days;
                $balance->save();
            }

            $request->status = 'rejected';
            $request->approver_id = $approver->id;
            $request->approver_notes = $notes;
            $request->approved_at = Carbon::now();
            $request->save();
        });
    }
}
