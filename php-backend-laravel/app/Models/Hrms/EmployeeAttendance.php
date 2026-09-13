<?php

declare(strict_types=1);

namespace App\Models\Hrms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EmployeeAttendance extends Model
{
    use HasFactory;

    protected $table = 'employee_attendances';

    protected $fillable = [
        'employee_profile_id',
        'date',
        'employee_shift_id',
        'clock_in_at',
        'clock_in_latitude',
        'clock_in_longitude',
        'clock_in_distance_meters',
        'clock_in_geofence_status',
        'clock_in_method',
        'clock_in_photo_path',
        'clock_out_at',
        'clock_out_latitude',
        'clock_out_longitude',
        'clock_out_distance_meters',
        'clock_out_geofence_status',
        'clock_out_method',
        'clock_out_photo_path',
        'total_work_minutes',
        'total_break_minutes',
        'status',
        'break_logs',
        'admin_notes',
        'is_verified',
    ];

    protected $casts = [
        'date' => 'date',
        'clock_in_at' => 'datetime',
        'clock_out_at' => 'datetime',
        'clock_in_latitude' => 'float',
        'clock_in_longitude' => 'float',
        'clock_in_distance_meters' => 'float',
        'clock_out_latitude' => 'float',
        'clock_out_longitude' => 'float',
        'clock_out_distance_meters' => 'float',
        'total_work_minutes' => 'integer',
        'total_break_minutes' => 'integer',
        'break_logs' => 'array',
        'is_verified' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_profile_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(EmployeeShift::class, 'employee_shift_id');
    }

    /** Helper: is currently on active duty */
    public function isCurrentlyClockedIn(): bool
    {
        return $this->clock_in_at !== null && $this->clock_out_at === null;
    }

    /** Helper: is currently on break */
    public function isCurrentlyOnBreak(): bool
    {
        if (! $this->isCurrentlyClockedIn() || empty($this->break_logs)) {
            return false;
        }

        $logs = $this->break_logs;
        $lastBreak = ! empty($logs) ? end($logs) : null;

        return is_array($lastBreak) && isset($lastBreak['start']) && empty($lastBreak['end']);
    }

    /** Formatted working duration (e.g. "7h 45m") */
    public function getFormattedWorkDurationAttribute(): string
    {
        $hours = floor($this->total_work_minutes / 60);
        $minutes = $this->total_work_minutes % 60;

        return sprintf('%dh %02dm', $hours, $minutes);
    }
}
