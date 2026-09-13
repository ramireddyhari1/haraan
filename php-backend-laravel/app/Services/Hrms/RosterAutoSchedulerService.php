<?php

declare(strict_types=1);

namespace App\Services\Hrms;

use App\Models\Booking;
use App\Models\Hrms\EmployeeLeaveRequest;
use App\Models\Hrms\EmployeeProfile;
use App\Models\Hrms\EmployeeShift;
use App\Models\Hrms\EmployeeShiftRoster;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Carbon;
use Throwable;

final class RosterAutoSchedulerService
{
    public function __construct(
        private readonly RosterService $rosterService,
        private readonly WorkforceAuditService $auditService
    ) {}

    /**
     * Auto-schedule shift rosters for a venue correlating with upcoming bookings,
     * statutory rest intervals, leave exclusions, and 6-day consecutive work limits.
     *
     * @return array{
     *     success: bool,
     *     venue_id: int,
     *     start_date: string,
     *     end_date: string,
     *     total_rosters_created: int,
     *     total_bookings_evaluated: int,
     *     rosters: array,
     *     demand_summary: array
     * }
     */
    public function autoSchedule(
        Venue $venue,
        Carbon $startDate,
        Carbon $endDate,
        int $minStaffPerShift = 1,
        ?User $actor = null
    ): array {
        $shifts = EmployeeShift::where('is_active', true)->orderBy('start_time')->get();
        if ($shifts->isEmpty()) {
            $shifts = collect([
                EmployeeShift::firstOrCreate(
                    ['code' => 'GEN-STD'],
                    [
                        'name' => 'General Day Shift',
                        'start_time' => '09:00:00',
                        'end_time' => '17:00:00',
                        'grace_period_minutes' => 15,
                        'full_day_threshold_hours' => 8.0,
                        'is_active' => true,
                    ]
                ),
            ]);
        }

        $allStaff = EmployeeProfile::with('user')
            ->where('venue_id', $venue->id)
            ->whereIn('employment_status', ['active', 'ACTIVE'])
            ->get();

        $createdRosters = [];
        $totalBookingsEvaluated = 0;
        $demandSummary = [];

        $currDate = $startDate->copy()->startOfDay();
        $targetEndDate = $endDate->copy()->startOfDay();

        while ($currDate->lte($targetEndDate)) {
            $dateString = $currDate->toDateString();

            // 1. Evaluate booking demand for the day
            $bookingCount = Booking::where('venue_id', $venue->id)
                ->whereDate('slot_date', $dateString)
                ->whereNotIn('status', ['cancelled', 'rejected'])
                ->count();

            $totalBookingsEvaluated += $bookingCount;

            // Demand-driven staff requirement: baseline + additional staff for peak court bookings
            $surgeAddition = intdiv($bookingCount, 4); // +1 staff for every 4 active court bookings
            $requiredPerShift = max($minStaffPerShift, $minStaffPerShift + $surgeAddition);

            $demandSummary[$dateString] = [
                'bookings' => $bookingCount,
                'target_staff_per_shift' => $requiredPerShift,
                'scheduled' => 0,
            ];

            foreach ($shifts as $shift) {
                $scheduledForThisShift = 0;

                // Sort staff by total rostered shifts this month to maintain equitable distribution
                $sortedStaff = $allStaff->sortBy(function ($employee) use ($currDate) {
                    return EmployeeShiftRoster::where('employee_profile_id', $employee->id)
                        ->whereMonth('roster_date', $currDate->month)
                        ->whereYear('roster_date', $currDate->year)
                        ->count();
                });

                foreach ($sortedStaff as $employee) {
                    if ($scheduledForThisShift >= $requiredPerShift) {
                        break;
                    }

                    // A. Check if already rostered on this date
                    $alreadyScheduled = EmployeeShiftRoster::where('employee_profile_id', $employee->id)
                        ->whereDate('roster_date', $dateString)
                        ->exists();

                    if ($alreadyScheduled) {
                        continue;
                    }

                    // B. Check Approved Leaves
                    $onLeave = EmployeeLeaveRequest::where('employee_profile_id', $employee->id)
                        ->whereIn('status', ['approved', 'tier1_approved', 'tier2_approved'])
                        ->whereDate('start_date', '<=', $dateString)
                        ->whereDate('end_date', '>=', $dateString)
                        ->exists();

                    if ($onLeave) {
                        continue;
                    }

                    // C. Statutory Rest Day: Cap at configured consecutive working days (default 6)
                    $maxConsecutive = (int) config('workforce.statutory.max_consecutive_work_days', 6);
                    if ($this->hasWorkedConsecutiveDays($employee, $currDate, $maxConsecutive)) {
                        continue;
                    }

                    // D. Inter-Shift Rest Interval Check via RosterService
                    try {
                        $roster = $this->rosterService->assignShift(
                            $employee,
                            $shift,
                            $currDate,
                            $venue,
                            "Auto-scheduled by WOS (Demand: {$bookingCount} bookings)"
                        );

                        $createdRosters[] = [
                            'id' => $roster->id,
                            'employee_code' => $employee->employee_code,
                            'employee_name' => $employee->user?->name ?? 'Staff',
                            'shift_name' => $shift->name,
                            'date' => $dateString,
                        ];

                        $scheduledForThisShift++;
                        $demandSummary[$dateString]['scheduled']++;
                    } catch (Throwable) {
                        // Skip if rest interval or validation fails
                        continue;
                    }
                }
            }

            $currDate->addDay();
        }

        // Cryptographic Audit Ledger Entry
        $this->auditService->recordEvent(
            'ROSTER_AUTO_SCHEDULED',
            $venue,
            null,
            [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'total_rosters_created' => count($createdRosters),
                'total_bookings_evaluated' => $totalBookingsEvaluated,
            ],
            $actor ?? auth()->user(),
            $venue->id,
            [
                'min_staff_per_shift' => $minStaffPerShift,
                'demand_summary' => $demandSummary,
            ]
        );

        return [
            'success' => true,
            'venue_id' => $venue->id,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'total_rosters_created' => count($createdRosters),
            'total_bookings_evaluated' => $totalBookingsEvaluated,
            'rosters' => $createdRosters,
            'demand_summary' => $demandSummary,
        ];
    }

    /**
     * Check if an employee has worked consecutive days prior to the target date.
     */
    private function hasWorkedConsecutiveDays(EmployeeProfile $employee, Carbon $targetDate, int $consecutiveDays): bool
    {
        for ($i = 1; $i <= $consecutiveDays; $i++) {
            $checkDate = $targetDate->copy()->subDays($i)->toDateString();
            $exists = EmployeeShiftRoster::where('employee_profile_id', $employee->id)
                ->whereDate('roster_date', $checkDate)
                ->whereNotIn('status', ['cancelled'])
                ->exists();

            if (! $exists) {
                return false;
            }
        }

        return true;
    }
}
