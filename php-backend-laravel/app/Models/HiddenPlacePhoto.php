<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\PlacePhotos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * A venue whose Google listing photos we've been asked not to show.
 *
 * Presence is the whole signal — a row means hidden, no row means shown. Keyed
 * by Google place_id so one switch covers every event at that venue, now and in
 * future. See PlacePhotos::blocked().
 */
final class HiddenPlacePhoto extends Model
{
    protected $fillable = ['place_id', 'hidden_by', 'reason'];

    /**
     * The lookup is cached for a day, so both directions of the switch have to
     * drop that key or an admin would flip it and see no change until tomorrow.
     */
    protected static function booted(): void
    {
        $flush = static function (self $row): void {
            Cache::forget(PlacePhotos::blockCacheKey((string) $row->place_id));
        };

        static::saved($flush);
        static::deleted($flush);
    }

    public function hiddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hidden_by');
    }
}
