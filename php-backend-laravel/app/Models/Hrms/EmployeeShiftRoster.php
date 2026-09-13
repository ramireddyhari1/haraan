<?php

declare(strict_types=1);

namespace App\Models\Hrms;

use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EmployeeShiftRoster extends Model
{
    use HasFactory;

    protected $table = 'employee_shift_rosters';

    protected $fillable = [
        'employee_profile_id',
        'employee_shift_id',
        'venue_id',
        'roster_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'roster_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_profile_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(EmployeeShift::class, 'employee_shift_id');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function swaps(): HasMany
    {
        return $this->hasMany(EmployeeShiftSwap::class, 'requestor_roster_id');
    }
}
