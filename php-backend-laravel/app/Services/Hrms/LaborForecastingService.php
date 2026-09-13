<?php

declare(strict_types=1);

namespace App\Services\Hrms;

use App\Models\Booking;
use App\Models\Hrms\EmployeeProfile;
use App\Models\Hrms\EmployeeShiftRoster;
use App\Models\Venue;
use Illuminate\Support\Carbon;

final class LaborForecastingService
{
    /**
     * Forecast labor expenses, booking revenues, labor efficiency percentage,
     * and statutory overtime/burnout alerts for a venue over a target month.
     *
     * @param  string  $month  Format: "YYYY-MM" (e.g. "2026-09")
     * @return array{
     *     venue_id: int,
     *     month: string,
     *     projected_revenue: float,
     *     projected_labor_cost: float,
     *     labor_cost_percentage: float,
     *     total_scheduled_hours: float,
     *     projected_overtime_hours: float,
     *     projected_overtime_cost: float,
     *     alerts: array,
     *     status: string,
     *     recommendation: string
     * }
     */
    public function forecastMonthly(Venue $venue, string $month): array
    {
        $startDate = Carbon::parse("{$month}-01")->startOfMonth();
        $endDate = (clone $startDate)->endOfMonth();

        // 1. Projected Booking Revenue from confirmed/paid court bookings
        $projectedRevenue = (float) Booking::where('venue_id', $venue->id)
            ->whereBetween('slot_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->sum('total_amount');

        // 2. Fetch all rostered shifts for the month
        $rosters = EmployeeShiftRoster::with(['shift', 'employee.user'])
            ->where('venue_id', $venue->id)
            ->whereBetween('roster_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereNotIn('status', ['cancelled'])
            ->get();

        $activeEmployees = EmployeeProfile::where('venue_id', $venue->id)
            ->whereIn('employment_status', ['active', 'ACTIVE'])
            ->get()
            ->keyBy('id');

        $totalScheduledHours = 0.0;
        $projectedOvertimeHours = 0.0;
        $projectedOvertimeCost = 0.0;
        $baseLaborCost = 0.0;

        // Group rosters by employee to analyze weekly caps & overtime
        $employeeRosters = $rosters->groupBy('employee_profile_id');
        $alerts = [];

        foreach ($activeEmployees as $empId => $employee) {
            $empSalary = (float) $employee->base_salary;
            if ($empSalary <= 0) {
                $empSalary = 30000.0; // Standard baseline
            }
            $baseLaborCost += $empSalary;

            $empRosters = $employeeRosters->get($empId, collect());
            $hourlyWage = $empSalary / (26 * 8); // 26 working days * 8h = ~208h standard month

            $totalEmpHours = 0.0;

            // Group by calendar week to evaluate statutory 48h weekly limit
            $weeklyHours = [];
            $consecutiveDays = 0;
            $prevDate = null;

            $sortedRosters = $empRosters->sortBy('roster_date');

            foreach ($sortedRosters as $roster) {
                $shift = $roster->shift;
                $shiftDuration = 8.0;
                if ($shift && $shift->start_time && $shift->end_time) {
                    $start = Carbon::parse("2026-01-01 {$shift->start_time}");
                    $end = Carbon::parse("2026-01-01 {$shift->end_time}");
                    if ($end->lt($start)) {
                        $end->addDay();
                    }
                    $shiftDuration = max(1.0, round($start->diffInMinutes($end) / 60, 2));
                }

                $totalEmpHours += $shiftDuration;
                $totalScheduledHours += $shiftDuration;

                $rosterDate = Carbon::parse($roster->roster_date);
                $weekKey = $rosterDate->format('o-W'); // Year-Week
                $weeklyHours[$weekKey] = ($weeklyHours[$weekKey] ?? 0.0) + $shiftDuration;

                // Track consecutive working days
                if ($prevDate !== null && $rosterDate->diffInDays($prevDate) === 1) {
                    $consecutiveDays++;
                } else {
                    $consecutiveDays = 1;
                }
                $maxConsecutive = (int) config('workforce.statutory.max_consecutive_work_days', 6);
                if ($consecutiveDays > $maxConsecutive) {
                    $alerts[] = [
                        'type' => 'MANDATORY_REST_DAY_VIOLATION',
                        'severity' => 'high',
                        'employee_id' => $empId,
                        'employee_code' => $employee->employee_code,
                        'employee_name' => $employee->user?->name ?? 'Staff',
                        'date' => $roster->roster_date,
                        'message' => "Worker scheduled for {$consecutiveDays} consecutive days without a mandatory 24-hour statutory rest period.",
                    ];
                }
            }

            // Check weekly statutory threshold (default 48 hours)
            $weeklyMax = (float) config('workforce.statutory.weekly_max_hours', 48.0);
            $otMultiplier = (float) config('workforce.statutory.overtime_rate_multiplier', 2.0);

            foreach ($weeklyHours as $week => $hours) {
                if ($hours > $weeklyMax) {
                    $overtime = $hours - $weeklyMax;
                    $projectedOvertimeHours += $overtime;
                    $otCost = $overtime * ($hourlyWage * $otMultiplier); // Statutory double rate for overtime
                    $projectedOvertimeCost += $otCost;

                    $alerts[] = [
                        'type' => 'EXCESSIVE_WEEKLY_HOURS',
                        'severity' => 'critical',
                        'employee_id' => $empId,
                        'employee_code' => $employee->employee_code,
                        'employee_name' => $employee->user?->name ?? 'Staff',
                        'week' => $week,
                        'hours_scheduled' => $hours,
                        'overtime_hours' => $overtime,
                        'projected_overtime_cost' => round($otCost, 2),
                        'message' => "Weekly hours ({$hours}h) exceed Indian statutory {$weeklyMax}-hour cap by {$overtime} hours. Double-rate overtime mandatory.",
                    ];
                }
            }
        }

        $projectedLaborCost = round($baseLaborCost + $projectedOvertimeCost, 2);

        // Labor Cost Percentage (LCP)
        $laborCostPercentage = $projectedRevenue > 0
            ? round(($projectedLaborCost / $projectedRevenue) * 100, 2)
            : ($projectedLaborCost > 0 ? 100.0 : 0.0);

        // Operational Diagnostics & Recommendation
        $hasCriticalAlerts = collect($alerts)->contains('severity', 'critical');
        $hasHighAlerts = collect($alerts)->contains('severity', 'high');

        if ($hasCriticalAlerts) {
            $status = 'OVERTIME_STATUTORY_RISK';
            $recommendation = 'Critical: Several employees exceed statutory weekly 48-hour caps. Rebalance shifts to avoid double-rate overtime and labor inspection penalties.';
        } elseif ($hasHighAlerts) {
            $status = 'REST_DAY_NON_COMPLIANCE';
            $recommendation = 'Warning: Consecutive work days exceed statutory limit. Schedule mandatory rest days to prevent crew burnout.';
        } elseif ($laborCostPercentage > 35.0) {
            $status = 'HIGH_LABOR_RATIO';
            $recommendation = "Labor cost ratio ({$laborCostPercentage}%) exceeds 35% target. Review roster allocations against booking demand.";
        } else {
            $status = 'HEALTHY_LABOR_EFFICIENCY';
            $recommendation = "Optimal operational efficiency: Labor cost ratio is at a healthy {$laborCostPercentage}%, with zero statutory overtime breaches.";
        }

        return [
            'venue_id' => $venue->id,
            'month' => $month,
            'projected_revenue' => round($projectedRevenue, 2),
            'projected_labor_cost' => $projectedLaborCost,
            'labor_cost_percentage' => $laborCostPercentage,
            'total_scheduled_hours' => round($totalScheduledHours, 1),
            'projected_overtime_hours' => round($projectedOvertimeHours, 1),
            'projected_overtime_cost' => round($projectedOvertimeCost, 2),
            'alerts' => $alerts,
            'status' => $status,
            'recommendation' => $recommendation,
        ];
    }
}
