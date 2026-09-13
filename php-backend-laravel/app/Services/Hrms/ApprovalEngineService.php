<?php

declare(strict_types=1);

namespace App\Services\Hrms;

use App\Models\Hrms\EmployeeAttendance;
use App\Models\Hrms\EmployeeAttendanceRegularisation;
use App\Models\Hrms\EmployeeDelegation;
use App\Models\Hrms\EmployeeLeaveBalance;
use App\Models\Hrms\EmployeeLeaveRequest;
use App\Models\Hrms\EmployeeShiftSwap;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use RuntimeException;

final class ApprovalEngineService
{
    public function __construct(
        private readonly WorkforceAuditService $auditService
    ) {}

    /**
     * Determine whether a request requires 1-tier or 2-tier approval.
     */
    public function determineRequiredTier(Model $request): int
    {
        if ($request instanceof EmployeeAttendanceRegularisation) {
            $in = Carbon::parse($request->requested_clock_in_at);
            $out = Carbon::parse($request->requested_clock_out_at);
            $diffMinutes = abs($out->diffInMinutes($in));
            $threshold = (int) config('workforce.approvals.tier2_regularisation_threshold_minutes', 120);

            // If regularisation adjustment > configured threshold (default 120 min) or high-dispute categories
            if ($diffMinutes > $threshold || in_array($request->reason_category, ['outdoor_duty', 'gps_drift', 'other'], true)) {
                return 2;
            }

            return 1;
        }

        if ($request instanceof EmployeeLeaveRequest) {
            $leaveThreshold = (float) config('workforce.approvals.tier2_leave_threshold_days', 2.0);
            if ((float) $request->total_days > $leaveThreshold) {
                return 2;
            }

            return 1;
        }

        if ($request instanceof EmployeeShiftSwap) {
            return 1;
        }

        return 1;
    }

    /**
     * Determine if a user has authority to approve a specific request at a given tier.
     * Supports RBAC, direct reporting hierarchy (ABAC), and active delegations.
     */
    public function canUserApprove(User $user, Model $request, int $targetTier): bool
    {
        // 1. Super Admin / Enterprise HR bypasses
        if ($user->hasRole('super_admin') || in_array(strtoupper((string) $user->role), ['ADMIN', 'COADMIN', 'OPS'], true)) {
            return true;
        }

        $employeeProfile = $request->employee;
        if ($employeeProfile === null) {
            return false;
        }

        $directManagerId = $employeeProfile->reporting_manager_id;

        // 2. Direct Manager check for Tier 1
        if ($targetTier === 1 && $directManagerId === $user->id) {
            return true;
        }

        // 3. Check Active Delegations
        if ($directManagerId !== null) {
            $hasActiveDelegation = EmployeeDelegation::where('delegator_id', $directManagerId)
                ->where('delegatee_id', $user->id)
                ->where('is_active', true)
                ->whereDate('start_date', '<=', Carbon::today())
                ->whereDate('end_date', '>=', Carbon::today())
                ->where('max_approval_tier', '>=', $targetTier)
                ->exists();

            if ($hasActiveDelegation) {
                return true;
            }
        }

        // 4. Venue Manager authority for Tier 2 or Venue Scoping
        if ($targetTier === 2) {
            $venueId = $employeeProfile->venue_id;
            if ($venueId !== null) {
                $isVenueLead = User::where('id', $user->id)
                    ->where(function ($q) use ($venueId) {
                        $q->whereHas('employeeProfile', fn ($ep) => $ep->where('venue_id', $venueId))
                          ->orWhere('role', 'PARTNER');
                    })
                    ->exists();

                if ($isVenueLead || in_array(strtoupper((string) $user->role), ['ADMIN', 'OPS', 'PARTNER'], true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Process an approval action for a given tier.
     */
    public function processApproval(Model $request, User $actor, int $tier, ?string $notes = null): Model
    {
        if (! $this->canUserApprove($actor, $request, $tier)) {
            throw new RuntimeException("Unauthorized: User {$actor->id} does not possess approval authority for Tier {$tier}.");
        }

        $before = $request->toArray();
        $requiredTier = (int) ($request->approval_tier ?? 1);

        if ($tier === 1) {
            $request->tier1_approved_by = $actor->id;
            $request->tier1_approved_at = Carbon::now();
            $request->tier1_notes = $notes;

            if ($requiredTier > 1) {
                $request->status = 'tier2_pending';
            } else {
                $request->status = 'approved';
                $this->assignFinalReviewerFields($request, $actor, $notes);
            }
        } elseif ($tier === 2) {
            if ($requiredTier < 2) {
                throw new InvalidArgumentException("Request #{$request->id} does not require Tier 2 approval.");
            }

            $request->tier2_approved_by = $actor->id;
            $request->tier2_approved_at = Carbon::now();
            $request->tier2_notes = $notes;
            $request->status = 'approved';
            $this->assignFinalReviewerFields($request, $actor, $notes);
        } else {
            throw new InvalidArgumentException("Unsupported tier level {$tier}.");
        }

        $request->save();

        // If finalized (approved), trigger downstream ledger synchronization
        if ($request->status === 'approved') {
            $this->finalizeApprovedRequest($request);
        }

        // Record audit ledger event
        $venueId = $request->employee?->venue_id;
        $this->auditService->recordEvent(
            "APPROVAL_TIER_{$tier}_CONFIRMED",
            $request,
            $before,
            $request->fresh()->toArray(),
            $actor,
            $venueId,
            ['tier' => $tier, 'notes' => $notes]
        );

        return $request;
    }

    /**
     * Process a rejection for a request.
     */
    public function processRejection(Model $request, User $actor, string $reason): Model
    {
        if (! $this->canUserApprove($actor, $request, 1) && ! $this->canUserApprove($actor, $request, 2)) {
            throw new RuntimeException("Unauthorized: User {$actor->id} cannot reject this request.");
        }

        $before = $request->toArray();
        $request->status = 'rejected';
        $this->assignFinalReviewerFields($request, $actor, $reason);
        $request->save();

        // Downstream cancellation/restoration
        if ($request instanceof EmployeeLeaveRequest) {
            $balance = EmployeeLeaveBalance::where('employee_profile_id', $request->employee_profile_id)
                ->where('employee_leave_type_id', $request->employee_leave_type_id)
                ->where('year', (int) Carbon::parse($request->start_date)->year)
                ->first();

            if ($balance) {
                $days = (float) $request->total_days;
                $balance->pending_days = max(0.0, (float) $balance->pending_days - $days);
                $balance->remaining_days = (float) $balance->remaining_days + $days;
                $balance->save();
            }
        }

        $venueId = $request->employee?->venue_id;
        $this->auditService->recordEvent(
            'REQUEST_REJECTED',
            $request,
            $before,
            $request->fresh()->toArray(),
            $actor,
            $venueId,
            ['rejection_reason' => $reason]
        );

        return $request;
    }

    /**
     * Map reviewer / approver fields based on model schema differences.
     */
    private function assignFinalReviewerFields(Model $request, User $actor, ?string $notes): void
    {
        if ($request instanceof EmployeeLeaveRequest) {
            $request->approver_id = $actor->id;
            $request->approved_at = Carbon::now();
            $request->approver_notes = $notes;
        } else {
            $request->reviewed_by = $actor->id;
            $request->reviewed_at = Carbon::now();
            $request->reviewer_notes = $notes;
        }
    }

    /**
     * Downstream commit when a request is fully approved.
     */
    private function finalizeApprovedRequest(Model $request): void
    {
        if ($request instanceof EmployeeAttendanceRegularisation) {
            $employee = $request->employee;
            if (! $employee) {
                return;
            }

            $date = Carbon::parse($request->date);
            $in = Carbon::parse($request->requested_clock_in_at);
            $out = Carbon::parse($request->requested_clock_out_at);
            $workMinutes = (int) abs($out->diffInMinutes($in));

            $attendance = EmployeeAttendance::firstOrNew([
                'employee_profile_id' => $employee->id,
                'date' => $date->toDateString(),
            ]);

            $attendance->clock_in_at = $in;
            $attendance->clock_out_at = $out;
            $attendance->total_work_minutes = $workMinutes;
            $attendance->status = ($workMinutes >= 240) ? 'present' : 'half_day';
            $attendance->admin_notes = "Regularised via Request #{$request->id}: {$request->reason}";
            $attendance->is_verified = true;
            $attendance->save();

            $request->attendance_id = $attendance->id;
            $request->save();
        } elseif ($request instanceof EmployeeLeaveRequest) {
            $balance = EmployeeLeaveBalance::where('employee_profile_id', $request->employee_profile_id)
                ->where('employee_leave_type_id', $request->employee_leave_type_id)
                ->where('year', (int) Carbon::parse($request->start_date)->year)
                ->first();

            if ($balance) {
                $days = (float) $request->total_days;
                $balance->pending_days = max(0.0, (float) $balance->pending_days - $days);
                $balance->used_days = (float) $balance->used_days + $days;
                $balance->save();
            }
        }
    }

    /**
     * Automatically escalate pending requests that exceeded the SLA threshold.
     */
    public function escalateStaleRequests(int $hoursThreshold = 24): int
    {
        $cutoff = Carbon::now()->subHours($hoursThreshold);
        $escalatedCount = 0;

        // 1. Attendance Regularisations
        $staleRegs = EmployeeAttendanceRegularisation::whereIn('status', ['pending', 'tier1_pending', 'tier2_pending'])
            ->where('created_at', '<=', $cutoff)
            ->get();

        foreach ($staleRegs as $reg) {
            $before = $reg->toArray();
            $reg->status = 'escalated';
            $reg->escalated_at = Carbon::now();
            $reg->escalation_reason = "SLA_BREACH_AUTOMATIC_ESCALATION: Pending over {$hoursThreshold} hours without supervisor adjudication.";
            $reg->save();

            $this->auditService->recordEvent(
                'APPROVAL_SLA_BREACH_ESCALATED',
                $reg,
                $before,
                $reg->fresh()->toArray(),
                null,
                $reg->employee?->venue_id,
                ['sla_hours' => $hoursThreshold]
            );

            $escalatedCount++;
        }

        // 2. Leave Requests
        $staleLeaves = EmployeeLeaveRequest::whereIn('status', ['pending', 'tier1_pending', 'tier2_pending'])
            ->where('created_at', '<=', $cutoff)
            ->get();

        foreach ($staleLeaves as $leave) {
            $before = $leave->toArray();
            $leave->status = 'escalated';
            $leave->escalated_at = Carbon::now();
            $leave->escalation_reason = "SLA_BREACH_AUTOMATIC_ESCALATION: Pending over {$hoursThreshold} hours without supervisor adjudication.";
            $leave->save();

            $this->auditService->recordEvent(
                'APPROVAL_SLA_BREACH_ESCALATED',
                $leave,
                $before,
                $leave->fresh()->toArray(),
                null,
                $leave->employee?->venue_id,
                ['sla_hours' => $hoursThreshold]
            );

            $escalatedCount++;
        }

        return $escalatedCount;
    }
}
