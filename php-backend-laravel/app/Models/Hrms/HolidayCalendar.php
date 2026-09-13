<?php

declare(strict_types=1);

namespace App\Models\Hrms;

use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class HolidayCalendar extends Model
{
    use HasFactory;

    protected $table = 'holiday_calendars';

    protected $fillable = [
        'title',
        'date',
        'is_optional',
        'applicable_venue_id',
        'description',
    ];

    protected $casts = [
        'date' => 'date',
        'is_optional' => 'boolean',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class, 'applicable_venue_id');
    }
}
