<?php

declare(strict_types=1);

namespace App\Services;

use App\Filament\Resources\Venues\Schemas\VenueForm;
use App\Models\PartnerApplication;
use App\Models\Venue;
use App\Models\VenueCourt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Turns an approved application into a DRAFT venue, filled the way the /control venue
 * form would fill it (CreateVenue): basics, location, hours → slots, price, photos,
 * amenities, rules, plus one court per row the owner listed.
 *
 * The venue starts as a draft — not visible, not bookable — so an admin still checks it
 * and puts it live from the venue page, where readiness is enforced.
 */
final class PartnerApplicationVenueBuilder
{
    public function build(PartnerApplication $application): Venue
    {
        if ($application->venue_id !== null) {
            throw new \RuntimeException('A venue was already created from this application.');
        }

        if (! $application->hasPlace()) {
            throw new \RuntimeException('Event hosts have no venue to create — they add events from their console.');
        }

        if ($application->partner_id === null) {
            throw new \RuntimeException('Create the partner account first, so the venue has an owner.');
        }

        return DB::transaction(function () use ($application): Venue {
            $sports = array_values(array_filter((array) $application->sports, 'is_string'));
            $courts = $application->courtList();
            $isCafe = $application->business_type === 'cafe';

            $venue = Venue::create([
                'name' => $application->business_name,
                // The main sport is the first one the owner picked; the card icon reads it.
                'category' => $sports[0] ?? array_key_first(VenueForm::SPORTS),
                'kind' => $isCafe ? 'cafe' : 'sports',
                'sports' => $sports ?: null,
                'tagline' => $application->tagline,
                'about' => $application->about,
                'location' => $application->area ?: $application->city,
                'city' => $application->city,
                'address' => $application->address,
                'map_link' => $application->map_link,
                'latitude' => $application->latitude,
                'longitude' => $application->longitude,
                'price' => (int) ($application->base_price ?? 0),
                'hours_json' => $application->hours_json ?: null,
                'slot_minutes' => $application->slot_minutes ?: 60,
                'cancel_free_hours' => $application->cancel_free_hours,
                'cancel_refund_percent' => $application->cancel_refund_percent,
                'images' => $this->copyPhotos($application),
                'amenities' => $application->amenities ?: null,
                'rules' => $application->rules ?: null,
                'partner_id' => $application->partner_id,
                'status' => 'draft',
                'is_active' => false,
                'is_bookable' => false,
            ]);

            foreach ($courts as $i => $court) {
                VenueCourt::create([
                    'venue_id' => $venue->id,
                    'name' => (string) ($court['name'] ?? ('Court ' . ($i + 1))),
                    'kind' => $isCafe ? 'table' : 'court',
                    'sports' => array_values((array) ($court['sports'] ?? [])) ?: ($sports ?: null),
                    'price' => isset($court['price']) && (int) $court['price'] > 0 ? (int) $court['price'] : null,
                    'sort_order' => $i,
                    'is_active' => true,
                ]);
            }

            // What CreateVenue::afterCreate does: the display string and the bookable slots.
            $venue->update(['hours' => $venue->displayHours()]);
            $venue->regenerateSlotsFromHours();

            $application->forceFill(['venue_id' => $venue->id])->save();

            return $venue;
        });
    }

    /**
     * Copy (not move) the application's photos into the venues folder, so the venue owns
     * its files and the application keeps its own record of what was sent.
     *
     * @return array<int, string>|null
     */
    private function copyPhotos(PartnerApplication $application): ?array
    {
        $disk = Storage::disk('public');
        $paths = [];

        foreach ((array) $application->images as $i => $path) {
            if (! is_string($path) || ! $disk->exists($path)) {
                continue;
            }

            $target = 'venues/app-' . $application->id . '-' . ($i + 1) . '-' . basename($path);
            $disk->copy($path, $target);
            $paths[] = $target;
        }

        return $paths ?: null;
    }
}
