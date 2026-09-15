<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Hrms\EmployeeProfile;
use App\Models\Hrms\EmployeeTask;
use App\Models\User;
use App\Models\Venue;

/**
 * Who may touch whose workforce data.
 *
 * The workforce API used to trust any valid JWT, which let any member punch in as
 * another employee, complete their tasks, or generate and lock a venue's payroll. Three
 * relationships grant access here, and nothing else does:
 *
 *  - platform administrators (ADMIN / COADMIN) — support and finance operations;
 *  - the venue's business: the partner owner, or desk staff assigned to that branch
 *    holding the `reports` capability;
 *  - line management: someone with direct reports employed at that venue.
 *
 * An employee may always act for themselves (their own punch, their own task).
 */
final class WorkforceAccess
{
    public static function canManageVenue(?User $actor, Venue $venue): bool
    {
        if ($actor === null) {
            return false;
        }
        if ($actor->isSuperAdmin()) {
            return true;
        }

        if ($actor->hasRoleEither(['PARTNER'])
            && $actor->hasPartnerPermission('reports')
            && $actor->branches()->whereKey($venue->id)->exists()) {
            return true;
        }

        return EmployeeProfile::query()
            ->where('venue_id', $venue->id)
            ->where('reporting_manager_id', $actor->id)
            ->exists();
    }

    public static function canActForEmployee(?User $actor, EmployeeProfile $employee): bool
    {
        if ($actor === null) {
            return false;
        }
        if ((int) $employee->user_id === (int) $actor->id) {
            return true;
        }
        if ((int) $employee->reporting_manager_id === (int) $actor->id) {
            return true;
        }

        $venue = $employee->venue_id ? Venue::query()->find($employee->venue_id) : null;
        if ($venue !== null) {
            return self::canManageVenue($actor, $venue);
        }

        return $actor->isSuperAdmin();
    }

    public static function canCompleteTask(?User $actor, EmployeeTask $task): bool
    {
        if ($actor === null) {
            return false;
        }

        $employee = $task->employee;
        if ($employee !== null && self::canActForEmployee($actor, $employee)) {
            return true;
        }

        $venue = $task->venue_id ? Venue::query()->find($task->venue_id) : null;

        return $venue !== null ? self::canManageVenue($actor, $venue) : $actor->isSuperAdmin();
    }
}
