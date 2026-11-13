<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A player asking to book (or to be told about) a venue that is listed on Haraan but
 * not bookable yet. The count per venue is what the team takes to the owner.
 */
class VenueInterest extends Model
{
    public const KIND_BOOK = 'book';
    public const KIND_NOTIFY = 'notify';

    protected $fillable = ['venue_id', 'user_id', 'kind', 'name', 'phone', 'sport', 'preferred_time'];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
