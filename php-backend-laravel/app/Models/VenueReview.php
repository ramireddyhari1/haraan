<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer review — of a venue, or (since the post-event journey started asking)
 * of an event.
 *
 * Still called VenueReview because the table is still `venue_reviews`; renaming
 * both would touch the partner page, the admin resource and the seeders for no
 * behavioural gain. Exactly one of `venue_id` / `event_id` is set.
 */
final class VenueReview extends Model
{
    protected $fillable = [
        'venue_id', 'event_id', 'booking_id', 'name', 'rating', 'text', 'avatar', 'ago', 'is_active',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_active' => 'boolean',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
