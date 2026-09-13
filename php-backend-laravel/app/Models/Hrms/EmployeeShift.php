<?php

declare(strict_types=1);

namespace App\Models\Hrms;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EmployeeShift extends Model
{
    use HasFactory;

    protected $table = 'employee_shifts';

    protected $fillable = [
        'name',
        'code',
        'start_time',
        'end_time',
        'grace_period_minutes',
        'half_day_threshold_minutes',
        'is_night_shift',
        'is_rotational',
        'is_active',
        'partner_id',
    ];

    protected $casts = [
        'grace_period_minutes' => 'integer',
        'half_day_threshold_minutes' => 'integer',
        'is_night_shift' => 'boolean',
        'is_rotational' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function rosters(): HasMany
    {
        return $this->hasMany(EmployeeShiftRoster::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(EmployeeAttendance::class);
    }

    /** Formatted timing string e.g. "08:00 AM - 04:30 PM" */
    public function getFormattedTimingAttribute(): string
    {
        $start = date('h:i A', strtotime($this->start_time));
        $end = date('h:i A', strtotime($this->end_time));

        return "{$start} – {$end}";
    }
}
