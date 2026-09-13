<?php

declare(strict_types=1);

namespace App\Services\Hrms;

use App\Models\Hrms\EmployeeAttendance;
use App\Models\Hrms\EmployeeAttendanceRegularisation;
use App\Models\Hrms\EmployeeProfile;
use App\Models\Hrms\EmployeeShift;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class AttendanceService
{
    /** Earth radius in meters for Haversine formula */
    private const EARTH_RADIUS_METERS = 6371000.0;

    /**
     * Compute Haversine distance in meters between two lat/lon coordinates.
     */
    public function calculateDistanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $lat1Rad = deg2rad($lat1);
        $lon1Rad = deg2rad($lon1);
        $lat2Rad = deg2rad($lat2);
        $lon2Rad = deg2rad($lon2);

        $deltaLat = $lat2Rad - $lat1Rad;
        $deltaLon = $lon2Rad - $lon1Rad;

        $a = sin($deltaLat / 2) ** 2 +
             cos($lat1Rad) * cos($lat2Rad) * (sin($deltaLon / 2) ** 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round(self::EARTH_RADIUS_METERS * $c, 2);
    }

    /**
     * Clock in an employee.
     * Computes GPS distance from assigned venue / workplace, validates geofence radius,
     * matches shift punctuality, and creates or updates the daily attendance record.
     */
    public function clockIn(
        EmployeeProfile $employee,
        float $latitude,
        float $longitude,
        string $method = 'gps_web',
        ?string $photoPath = null
    ): EmployeeAttendance {
        $today = Carbon::today();
        $now = Carbon::now();

        // Target workplace: venue coordinates if assigned
        $venue = $employee->venue;
        $distance = null;
        $geofenceStatus = 'exempt';
        $radius = (int) ($employee->geofence_radius_meters ?: 200);

        if ($venue !== null && $venue->latitude !== null && $venue->longitude !== null) {
            $distance = $this->calculateDistanceMeters(
                $latitude,
                $longitude,
                (float) $venue->latitude,
                (float) $venue->longitude
            );

            $geofenceStatus = ($distance <= $radius) ? 'inside' : 'outside';
        }

        // Today's rostered shift or fallback
        $roster = $employee->todayRoster;
        $shift = $roster?->shift;

        // Punctuality check
        $status = 'present';
        if ($shift !== null) {
            $shiftStartTime = Carbon::parse($today->toDateString() . ' ' . $shift->start_time);
            $graceEndTime = (clone $shiftStartTime)->addMinutes($shift->grace_period_minutes);

            if ($now->greaterThan($graceEndTime)) {
                $status = 'late';
            }
        }

        return DB::transaction(function () use (
            $employee,
            $today,
            $shift,
            $now,
            $latitude,
            $longitude,
            $distance,
            $geofenceStatus,
            $method,
            $photoPath,
            $status
        ): EmployeeAttendance {
            $attendance = EmployeeAttendance::where('employee_profile_id', $employee->id)
                ->whereDate('date', $today)
                ->first();

            if ($attendance === null) {
                $attendance = new EmployeeAttendance([
                    'employee_profile_id' => $employee->id,
                    'date' => $today->toDateString(),
                ]);
            }

            if ($attendance->clock_in_at !== null) {
                throw new RuntimeException("Employee already clocked in today at " . $attendance->clock_in_at->format('H:i'));
            }

            $attendance->employee_shift_id = $shift?->id;
            $attendance->clock_in_at = $now;
            $attendance->clock_in_latitude = $latitude;
            $attendance->clock_in_longitude = $longitude;
            $attendance->clock_in_distance_meters = $distance;
            $attendance->clock_in_geofence_status = $geofenceStatus;
            $attendance->clock_in_method = $method;
            $attendance->clock_in_photo_path = $photoPath;
            $attendance->status = $status;
            $attendance->save();

            return $attendance;
        });
    }

    /**
     * Clock out an employee.
     * Computes total work minutes minus any break intervals.
     */
    public function clockOut(
        EmployeeProfile $employee,
        float $latitude,
        float $longitude,
        string $method = 'gps_web',
        ?string $photoPath = null
    ): EmployeeAttendance {
        $today = Carbon::today();
        $now = Carbon::now();

        $attendance = EmployeeAttendance::where('employee_profile_id', $employee->id)
            ->whereDate('date', $today)
            ->first();

        if ($attendance === null || $attendance->clock_in_at === null) {
            throw new RuntimeException("Cannot clock out: No clock-in record found for today.");
        }

        if ($attendance->clock_out_at !== null) {
            throw new RuntimeException("Employee has already clocked out today.");
        }

        // Close any active break
        if ($attendance->isCurrentlyOnBreak()) {
            $this->endBreak($employee);
            $attendance->refresh();
        }

        $venue = $employee->venue;
        $distance = null;
        $geofenceStatus = 'exempt';
        $radius = (int) ($employee->geofence_radius_meters ?: 200);

        if ($venue !== null && $venue->latitude !== null && $venue->longitude !== null) {
            $distance = $this->calculateDistanceMeters(
                $latitude,
                $longitude,
                (float) $venue->latitude,
                (float) $venue->longitude
            );

            $geofenceStatus = ($distance <= $radius) ? 'inside' : 'outside';
        }

        $totalMinutes = (int) $attendance->clock_in_at->diffInMinutes($now);
        $breakMinutes = (int) $attendance->total_break_minutes;
        $netWorkMinutes = max(0, $totalMinutes - $breakMinutes);

        // Half day threshold check
        $status = $attendance->status;
        $shift = $attendance->shift;
        $halfDayThreshold = $shift ? $shift->half_day_threshold_minutes : 240;

        if ($netWorkMinutes < $halfDayThreshold && $status !== 'late') {
            $status = 'half_day';
        }

        $attendance->clock_out_at = $now;
        $attendance->clock_out_latitude = $latitude;
        $attendance->clock_out_longitude = $longitude;
        $attendance->clock_out_distance_meters = $distance;
        $attendance->clock_out_geofence_status = $geofenceStatus;
        $attendance->clock_out_method = $method;
        $attendance->clock_out_photo_path = $photoPath;
        $attendance->total_work_minutes = $netWorkMinutes;
        $attendance->status = $status;
        $attendance->save();

        return $attendance;
    }

    /**
     * Start a break (lunch, tea, personal).
     */
    public function startBreak(EmployeeProfile $employee, string $type = 'lunch'): EmployeeAttendance
    {
        $attendance = EmployeeAttendance::where('employee_profile_id', $employee->id)
            ->whereDate('date', Carbon::today())
            ->first();

        if ($attendance === null || ! $attendance->isCurrentlyClockedIn()) {
            throw new RuntimeException("Cannot start break: Employee is not currently clocked in.");
        }

        if ($attendance->isCurrentlyOnBreak()) {
            throw new RuntimeException("Cannot start break: An ongoing break is already active.");
        }

        $breaks = $attendance->break_logs ?? [];
        $breaks[] = [
            'type' => $type,
            'start' => Carbon::now()->toIso8601String(),
            'end' => null,
            'duration_minutes' => 0,
        ];

        $attendance->break_logs = $breaks;
        $attendance->save();

        return $attendance;
    }

    /**
     * End an active break and update total break minutes.
     */
    public function endBreak(EmployeeProfile $employee): EmployeeAttendance
    {
        $attendance = EmployeeAttendance::where('employee_profile_id', $employee->id)
            ->whereDate('date', Carbon::today())
            ->first();

        if ($attendance === null || ! $attendance->isCurrentlyOnBreak()) {
            throw new RuntimeException("No active break found to conclude.");
        }

        $breaks = $attendance->break_logs ?? [];
        $lastIdx = count($breaks) - 1;

        $start = Carbon::parse($breaks[$lastIdx]['start']);
        $now = Carbon::now();
        $duration = max(1, (int) $start->diffInMinutes($now));

        $breaks[$lastIdx]['end'] = $now->toIso8601String();
        $breaks[$lastIdx]['duration_minutes'] = $duration;

        $totalBreakMinutes = array_sum(array_column($breaks, 'duration_minutes'));

        $attendance->break_logs = $breaks;
        $attendance->total_break_minutes = $totalBreakMinutes;
        $attendance->save();

        return $attendance;
    }

    /**
     * Generate dynamic rolling QR token for a venue with HMAC-SHA256 and TTL.
     */
    public function generateVenueQrToken(int $venueId, int $ttlSeconds = 60): string
    {
        $timestamp = Carbon::now()->timestamp;
        $secret = config('app.key');
        $payload = "{$venueId}:{$timestamp}";
        $signature = hash_hmac('sha256', $payload, $secret);

        return base64_encode("{$payload}:{$signature}");
    }

    /**
     * Verify dynamic QR token for a venue.
     */
    public function verifyVenueQrToken(int $venueId, string $token, int $ttlSeconds = 60): bool
    {
        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            return false;
        }

        $parts = explode(':', $decoded);
        if (count($parts) !== 3) {
            return false;
        }

        [$tokenVenueId, $timestamp, $signature] = $parts;

        if ((int) $tokenVenueId !== $venueId) {
            return false;
        }

        // TTL check
        $tokenTime = (int) $timestamp;
        $now = Carbon::now()->timestamp;
        if ($now < $tokenTime || ($now - $tokenTime) > $ttlSeconds) {
            return false;
        }

        // Signature check
        $secret = config('app.key');
        $expectedSignature = hash_hmac('sha256', "{$tokenVenueId}:{$timestamp}", $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Generate dynamic rolling QR token for an employee.
     */
    public function generateEmployeeQrToken(EmployeeProfile $employee, int $ttlSeconds = 60): string
    {
        $timestamp = Carbon::now()->timestamp;
        $secret = config('app.key');
        $payload = "emp:{$employee->id}:{$timestamp}";
        $signature = hash_hmac('sha256', $payload, $secret);

        return base64_encode("{$payload}:{$signature}");
    }

    /**
     * Verify employee rolling QR token.
     */
    public function verifyEmployeeQrToken(EmployeeProfile $employee, string $token, int $ttlSeconds = 60): bool
    {
        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            return false;
        }

        $parts = explode(':', $decoded);
        if (count($parts) !== 4 || $parts[0] !== 'emp') {
            return false;
        }

        [,$empId, $timestamp, $signature] = $parts;

        if ((int) $empId !== $employee->id) {
            return false;
        }

        $tokenTime = (int) $timestamp;
        $now = Carbon::now()->timestamp;
        if ($now < $tokenTime || ($now - $tokenTime) > $ttlSeconds) {
            return false;
        }

        $secret = config('app.key');
        $expectedSignature = hash_hmac('sha256', "emp:{$empId}:{$timestamp}", $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Submit an attendance regularisation request.
     */
    public function requestRegularisation(
        EmployeeProfile $employee,
        Carbon $date,
        Carbon $requestedClockIn,
        Carbon $requestedClockOut,
        string $reasonCategory,
        string $reason,
        ?EmployeeAttendance $attendance = null
    ): EmployeeAttendanceRegularisation {
        if ($requestedClockOut->lessThanOrEqualTo($requestedClockIn)) {
            throw new InvalidArgumentException("Clock-out time must be after clock-in time.");
        }

        $diffMinutes = abs($requestedClockOut->diffInMinutes($requestedClockIn));
        $tier = ($diffMinutes > 120 || in_array($reasonCategory, ['outdoor_duty', 'other'], true)) ? 2 : 1;

        $reg = EmployeeAttendanceRegularisation::create([
            'employee_profile_id' => $employee->id,
            'attendance_id' => $attendance?->id,
            'date' => $date->toDateString(),
            'requested_clock_in_at' => $requestedClockIn,
            'requested_clock_out_at' => $requestedClockOut,
            'reason_category' => $reasonCategory,
            'reason' => $reason,
            'approval_tier' => $tier,
            'status' => 'pending',
        ]);

        app(WorkforceAuditService::class)->recordEvent(
            'REGULARISATION_REQUESTED',
            $reg,
            null,
            $reg->toArray(),
            $employee->user,
            $employee->venue_id,
            ['reason_category' => $reasonCategory, 'approval_tier' => $tier]
        );

        return $reg;
    }

    /**
     * Approve an attendance regularisation request.
     * Updates or creates the daily attendance record, recomputes work minutes,
     * marks as verified, and updates regularisation status.
     */
    public function approveRegularisation(
        EmployeeAttendanceRegularisation $regularisation,
        User $reviewer,
        ?string $reviewerNotes = null
    ): EmployeeAttendance {
        if (! in_array($regularisation->status, ['pending', 'tier1_pending', 'tier2_pending'], true)) {
            throw new RuntimeException("Only pending regularisation requests can be approved.");
        }

        return DB::transaction(function () use ($regularisation, $reviewer, $reviewerNotes): EmployeeAttendance {
            $employee = $regularisation->employee;
            $date = Carbon::parse($regularisation->date);
            $in = Carbon::parse($regularisation->requested_clock_in_at);
            $out = Carbon::parse($regularisation->requested_clock_out_at);
            $workMinutes = (int) $in->diffInMinutes($out);

            // Find existing attendance or create new
            $attendance = EmployeeAttendance::firstOrNew([
                'employee_profile_id' => $employee->id,
                'date' => $date->toDateString(),
            ]);

            $attendance->clock_in_at = $in;
            $attendance->clock_out_at = $out;
            $attendance->total_work_minutes = $workMinutes;
            $attendance->status = $workMinutes >= 240 ? 'present' : 'half_day';
            $attendance->is_verified = true;
            $attendance->admin_notes = trim(($attendance->admin_notes ? $attendance->admin_notes . " | " : "") . "Regularised by {$reviewer->name}: " . ($reviewerNotes ?? 'Approved'));
            $attendance->save();

            $before = $regularisation->toArray();
            $regularisation->update([
                'status' => 'approved',
                'attendance_id' => $attendance->id,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => Carbon::now(),
                'reviewer_notes' => $reviewerNotes,
                'tier1_approved_by' => $reviewer->id,
                'tier1_approved_at' => Carbon::now(),
                'tier1_notes' => $reviewerNotes,
            ]);

            app(WorkforceAuditService::class)->recordEvent(
                'REGULARISATION_APPROVED',
                $regularisation,
                $before,
                $regularisation->fresh()->toArray(),
                $reviewer,
                $employee->venue_id,
                ['reviewer_notes' => $reviewerNotes]
            );

            return $attendance;
        });
    }

    /**
     * Reject an attendance regularisation request.
     */
    public function rejectRegularisation(
        EmployeeAttendanceRegularisation $regularisation,
        User $reviewer,
        ?string $reviewerNotes = null
    ): void {
        if (! in_array($regularisation->status, ['pending', 'tier1_pending', 'tier2_pending'], true)) {
            throw new RuntimeException("Only pending regularisation requests can be rejected.");
        }

        $before = $regularisation->toArray();
        $regularisation->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => Carbon::now(),
            'reviewer_notes' => $reviewerNotes,
        ]);

        app(WorkforceAuditService::class)->recordEvent(
            'REGULARISATION_REJECTED',
            $regularisation,
            $before,
            $regularisation->fresh()->toArray(),
            $reviewer,
            $regularisation->employee?->venue_id,
            ['rejection_reason' => $reviewerNotes]
        );
    }
}

