<?php

declare(strict_types=1);

namespace App\Jobs\Hrms;

use App\Models\Hrms\EmployeeProfile;
use App\Services\Hrms\AttendanceService;
use App\Services\Hrms\WorkforceAuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class ProcessPunchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $employeeProfileId,
        public readonly string $punchType, // 'in' or 'out'
        public readonly string $timestamp,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly string $method = 'gps_web',
        public readonly ?string $idempotencyKey = null,
        public readonly ?string $photoPath = null
    ) {}

    public function handle(AttendanceService $attendanceService, WorkforceAuditService $auditService): void
    {
        $employee = EmployeeProfile::find($this->employeeProfileId);
        if ($employee === null) {
            return;
        }

        $punchTime = Carbon::parse($this->timestamp);

        if ($this->punchType === 'in') {
            $attendance = $attendanceService->clockIn(
                $employee,
                $this->latitude,
                $this->longitude,
                $this->method,
                $this->photoPath
            );

            $auditService->recordEvent(
                'PUNCH_IN_INGESTED',
                $attendance,
                null,
                $attendance->toArray(),
                $employee->user,
                $employee->venue_id,
                [
                    'method' => $this->method,
                    'idempotency_key' => $this->idempotencyKey,
                    'geofence_status' => $attendance->clock_in_geofence_status,
                ]
            );
        } elseif ($this->punchType === 'out') {
            $attendance = $attendanceService->clockOut(
                $employee,
                $this->latitude,
                $this->longitude,
                $this->method,
                $this->photoPath
            );

            $auditService->recordEvent(
                'PUNCH_OUT_INGESTED',
                $attendance,
                null,
                $attendance->toArray(),
                $employee->user,
                $employee->venue_id,
                [
                    'method' => $this->method,
                    'idempotency_key' => $this->idempotencyKey,
                    'geofence_status' => $attendance->clock_out_geofence_status,
                    'total_work_minutes' => $attendance->total_work_minutes,
                ]
            );
        }
    }
}
