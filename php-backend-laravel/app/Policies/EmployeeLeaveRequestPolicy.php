<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Hrms\EmployeeDelegation;
use App\Models\Hrms\EmployeeLeaveRequest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Carbon;

final class EmployeeLeaveRequestPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, EmployeeLeaveRequest $leave): bool
    {
        if ($user->hasRole('super_admin') || in_array(strtoupper((string) $user->role), ['ADMIN', 'COADMIN', 'OPS'], true)) {
            return true;
        }

        $employee = $leave->employee;
        if (! $employee) {
            return false;
        }

        if ($employee->user_id === $user->id) {
            return true;
        }

        if ($employee->reporting_manager_id === $user->id) {
            return true;
        }

        if ($employee->reporting_manager_id !== null) {
            $isDelegate = EmployeeDelegation::where('delegator_id', $employee->reporting_manager_id)
                ->where('delegatee_id', $user->id)
                ->where('is_active', true)
                ->whereDate('start_date', '<=', Carbon::today())
                ->whereDate('end_date', '>=', Carbon::today())
                ->exists();

            if ($isDelegate) {
                return true;
            }
        }

        if ($employee->venue_id !== null && $user->employeeProfile?->venue_id === $employee->venue_id && $user->employeeProfile?->isManager()) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->employeeProfile !== null && $user->employeeProfile->isActive();
    }

    public function approve(User $user, EmployeeLeaveRequest $leave, int $tier = 1): bool
    {
        if ($user->hasRole('super_admin') || in_array(strtoupper((string) $user->role), ['ADMIN', 'COADMIN', 'OPS'], true)) {
            return true;
        }

        $employee = $leave->employee;
        if (! $employee) {
            return false;
        }

        // Prevent self approval
        if ($employee->user_id === $user->id) {
            return false;
        }

        if ($tier === 1 && $employee->reporting_manager_id === $user->id) {
            return true;
        }

        if ($employee->reporting_manager_id !== null) {
            $delegationPermits = EmployeeDelegation::where('delegator_id', $employee->reporting_manager_id)
                ->where('delegatee_id', $user->id)
                ->where('is_active', true)
                ->whereDate('start_date', '<=', Carbon::today())
                ->whereDate('end_date', '>=', Carbon::today())
                ->where('max_approval_tier', '>=', $tier)
                ->exists();

            if ($delegationPermits) {
                return true;
            }
        }

        if ($tier === 2 && $employee->venue_id !== null) {
            return $user->employeeProfile?->venue_id === $employee->venue_id && $user->employeeProfile?->isManager();
        }

        return false;
    }

    public function reject(User $user, EmployeeLeaveRequest $leave): bool
    {
        return $this->approve($user, $leave, 1) || $this->approve($user, $leave, 2);
    }
}
