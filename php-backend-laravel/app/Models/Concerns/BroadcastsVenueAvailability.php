<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Events\VenueAvailabilityUpdated;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Nudges open venue pages to refetch slot availability whenever a row that decides it
 * changes — a booking (made, held, paid, cancelled, expired), a court block, a closed date,
 * a slot template. Best-effort like {@see BroadcastsContentChanges}: a realtime outage is
 * logged, never allowed to fail the write (the app's on-resume/poll refresh covers it).
 *
 * Models opt out per row by overriding {@see affectsVenueAvailability()}.
 */
trait BroadcastsVenueAvailability
{
    public static function bootBroadcastsVenueAvailability(): void
    {
        static::saved(fn ($model) => $model->broadcastVenueAvailability());
        static::deleted(fn ($model) => $model->broadcastVenueAvailability());
    }

    protected function broadcastVenueAvailability(): void
    {
        if (! $this->affectsVenueAvailability()) {
            return;
        }

        // A row moved between venues changes both of them.
        $ids = array_unique(array_filter([
            (int) $this->getAttribute('venue_id'),
            (int) $this->getOriginal('venue_id'),
        ]));

        foreach ($ids as $venueId) {
            try {
                VenueAvailabilityUpdated::dispatch($venueId);
            } catch (Throwable $e) {
                Log::warning('VenueAvailabilityUpdated broadcast failed', [
                    'venue_id' => $venueId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function affectsVenueAvailability(): bool
    {
        return true;
    }
}
