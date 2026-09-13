<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Hrms\EmployeeAttendanceRegularisation;
use App\Models\Hrms\EmployeeDelegation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Carbon;

final class EmployeeAttendanceRegularisationPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, EmployeeAttendanceRegularisation $reg): bool
    {
        // 1. Super Admin / Global HQ
        if ($user->hasRole('super_admin') || in_array(strtoupper((string) $user->role), ['ADMIN', 'COADMIN', 'OPS'], true)) {
            return true;
        }

        $employee = $reg->employee;
        if (! $employee) {
            return false;
        }

        // 2. Self (Employee who submitted)
        if ($employee->user_id === $user->id) {
            return true;
        }

        // 3. Direct Reporting Manager
        if ($employee->reporting_manager_id === $user->id) {
            return true;
        }

        // 4. Active Delegate for Manager
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

        // 5. Venue GM
        if ($employee->venue_id !== null && $user->employeeProfile?->venue_id === $employee->venue_id && $user->employeeProfile?->isManager()) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->employeeProfile !== null && $user->employeeProfile->isActive();
    }

    public function approve(User $user, EmployeeAttendanceRegularisation $reg, int $tier = 1): bool
    {
        if ($user->hasRole('super_admin') || in_array(strtoupper((string) $user->role), ['ADMIN', 'COADMIN', 'OPS'], true)) {
            return true;
        }

        $employee = $reg->employee;
        if (! $employee) {
            return false;
        }

        // Disallow self-approval under all circumstances
        if ($employee->user_id === $user->id) {
            return false;
        }

        // Direct manager approval for Tier 1
        if ($tier === 1 && $employee->reporting_manager_id === $user->id) {
            return true;
        }

        // Delegated authority for Tier 1
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

        // Venue GM approval for Tier 2
        if ($tier === 2 && $employee->venue_id !== null) {
            return $user->employeeProfile?->venue_id === $employee->venue_id && $user->employeeProfile?->isManager();
        }

        return false;
    }

    public function reject(User $user, EmployeeAttendanceRegularisation $reg): bool
    {
        return $this->approve($user, $reg, 1) || $this->approve($user, $reg, 2);
    }
}
