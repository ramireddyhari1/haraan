<?php

declare(strict_types=1);

namespace App\Services\Hrms;

use App\Models\Hrms\EmployeeProfile;
use App\Models\Hrms\EmployeeShift;
use App\Models\Hrms\EmployeeShiftRoster;
use App\Models\Hrms\EmployeeShiftSwap;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RosterService
{
    /**
     * Assign a shift to an employee on a specified date with conflict and rest checks.
     */
    public function assignShift(
        EmployeeProfile $employee,
        EmployeeShift $shift,
        Carbon $date,
        ?Venue $venue = null,
        ?string $notes = null
    ): EmployeeShiftRoster {
        $dateString = $date->toDateString();

        // 1. Conflict Check: already scheduled on this date
        $existing = EmployeeShiftRoster::where('employee_profile_id', $employee->id)
            ->whereDate('roster_date', $date)
            ->first();

        if ($existing !== null) {
            throw new RuntimeException("Employee is already scheduled for a shift on {$dateString}.");
        }

        // 2. Rest Interval Check (e.g. at least 8 hours from previous day's shift end)
        $prevDate = (clone $date)->subDay();
        $prevRoster = EmployeeShiftRoster::with('shift')
            ->where('employee_profile_id', $employee->id)
            ->whereDate('roster_date', $prevDate)
            ->first();

        if ($prevRoster?->shift !== null) {
            $prevEnd = Carbon::parse($prevDate->toDateString() . ' ' . $prevRoster->shift->end_time);
            $currStart = Carbon::parse($dateString . ' ' . $shift->start_time);

            // If prev shift was night shift, it ends on the current day
            if ($prevRoster->shift->is_night_shift) {
                $prevEnd->addDay();
            }

            $restHours = $prevEnd->diffInMinutes($currStart, false) / 60;
            if ($restHours < 8) {
                $formattedRest = round($restHours, 1);
                throw new RuntimeException("Insufficient rest interval: only {$formattedRest} hours rest between consecutive shifts (minimum 8 required).");
            }
        }

        return EmployeeShiftRoster::create([
            'employee_profile_id' => $employee->id,
            'employee_shift_id' => $shift->id,
            'venue_id' => $venue?->id ?? $employee->venue_id,
            'roster_date' => $dateString,
            'status' => 'scheduled',
            'notes' => $notes,
        ]);
    }

    /**
     * Request a shift swap with another employee.
     */
    public function requestSwap(
        EmployeeShiftRoster $requestorRoster,
        EmployeeProfile $targetEmployee,
        string $reason,
        ?EmployeeShiftRoster $targetRoster = null
    ): EmployeeShiftSwap {
        if ($requestorRoster->employee_profile_id === $targetEmployee->id) {
            throw new RuntimeException("Cannot request shift swap with yourself.");
        }

        return EmployeeShiftSwap::create([
            'requestor_roster_id' => $requestorRoster->id,
            'target_employee_id' => $targetEmployee->id,
            'target_roster_id' => $targetRoster?->id,
            'status' => 'pending',
            'reason' => $reason,
        ]);
    }

    /**
     * Approve a shift swap and exchange the roster assignments.
     */
    public function approveSwap(EmployeeShiftSwap $swap, User $reviewer): void
    {
        if ($swap->status !== 'pending') {
            throw new RuntimeException("Shift swap is not in pending status.");
        }

        DB::transaction(function () use ($swap, $reviewer): void {
            $requestorRoster = $swap->requestorRoster;
            $targetRoster = $swap->targetRoster;

            if ($targetRoster !== null) {
                // Bilateral swap: exchange the two roster employee IDs
                $reqEmpId = $requestorRoster->employee_profile_id;
                $tgtEmpId = $targetRoster->employee_profile_id;

                $requestorRoster->employee_profile_id = $tgtEmpId;
                $targetRoster->employee_profile_id = $reqEmpId;

                $requestorRoster->status = 'swapped';
                $targetRoster->status = 'swapped';

                $requestorRoster->save();
                $targetRoster->save();
            } else {
                // Transfer shift to target employee
                $requestorRoster->employee_profile_id = $swap->target_employee_id;
                $requestorRoster->status = 'swapped';
                $requestorRoster->save();
            }

            $swap->status = 'approved';
            $swap->reviewed_by = $reviewer->id;
            $swap->reviewed_at = Carbon::now();
            $swap->save();
        });
    }

    /**
     * Reject a shift swap.
     */
    public function rejectSwap(EmployeeShiftSwap $swap, User $reviewer, ?string $reason = null): void
    {
        if ($swap->status !== 'pending') {
            throw new RuntimeException("Shift swap is not in pending status.");
        }

        $swap->status = 'rejected';
        $swap->reviewed_by = $reviewer->id;
        $swap->reviewed_at = Carbon::now();
        if ($reason) {
            $swap->reason = ($swap->reason ? $swap->reason . " | Rejection Note: " : "Rejection Note: ") . $reason;
        }
        $swap->save();
    }
}
