<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A date on which a venue takes no bookings (holiday / maintenance).
 *
 * @property int         $id
 * @property int         $venue_id
 * @property \Carbon\Carbon $date
 * @property string|null $reason
 */
final class VenueBlockedDate extends Model
{
    protected $fillable = [
        'venue_id', 'date', 'reason',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
