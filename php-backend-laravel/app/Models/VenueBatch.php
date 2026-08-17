<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A recurring coaching batch: coach, weekdays, time, monthly fee. */
final class VenueBatch extends Model
{
    protected $fillable = [
        'partner_id', 'venue_id', 'name', 'coach_name', 'sport',
        'days', 'start_time', 'end_time', 'monthly_fee', 'capacity', 'is_active',
    ];

    protected $casts = [
        'days' => 'array',
        'monthly_fee' => 'integer',
        'capacity' => 'integer',
        'is_active' => 'boolean',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(BatchEnrollment::class);
    }

    /** Weekdays as 3-letter names, same normalisation as court peak days. */
    public function daysList(): array
    {
        $list = is_array($this->days) ? $this->days : [];

        return array_values(array_filter(array_map(
            static fn ($d): string => ucfirst(strtolower(substr(trim((string) $d), 0, 3))),
            $list,
        )));
    }

    /** Does this batch run on the given date? Empty day list = every day. */
    public function runsOn(\Illuminate\Support\Carbon $date): bool
    {
        $days = $this->daysList();

        return $days === [] || in_array($date->format('D'), $days, true);
    }
}
