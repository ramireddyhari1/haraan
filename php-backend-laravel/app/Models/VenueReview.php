<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

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
    // Deleted from /control is recoverable ("see deleted" → restore); the rating
    // refresh below ignores trashed rows because the relation scope does.
    use SoftDeletes;

    public const SOURCE_APP = 'app';
    public const SOURCE_WEB = 'web';
    public const SOURCE_LINK = 'link';

    protected $fillable = [
        'venue_id', 'event_id', 'booking_id', 'user_id', 'source', 'hidden_reason',
        'name', 'rating', 'text', 'avatar', 'ago', 'is_active',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_active' => 'boolean',
    ];

    /** Keep the venue's rating / counts in step with its real reviews (Venue::refreshRating()). */
    protected static function booted(): void
    {
        $refresh = static function (VenueReview $review): void {
            $ids = array_filter([$review->venue_id, $review->getOriginal('venue_id')]);
            foreach (array_unique($ids) as $id) {
                Venue::query()->find($id)?->refreshRating();
            }
        };

        static::saved($refresh);
        static::deleted($refresh);
        static::restored($refresh);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** "Played here": the review is tied to a real booking at the venue. */
    public function isFromBooker(): bool
    {
        return $this->booking_id !== null;
    }

    /** "2 days ago" from the real timestamp; the legacy `ago` text only for seeded rows. */
    public function agoLabel(): string
    {
        return $this->created_at?->diffForHumans() ?? ($this->ago ?: 'recently');
    }

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
