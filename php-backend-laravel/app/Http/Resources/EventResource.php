<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for the Event model.
 */
final class EventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'title'          => $this->title,
            'description'    => $this->description,
            'category'       => $this->category,
            'bookingFormat'  => $this->booking_format,
            'visibility'     => $this->visibility,
            'location'       => $this->location,
            'mapLink'        => $this->map_link,
            'city'           => $this->city,
            'venue'          => $this->venue,
            'date'           => $this->date,
            'time'           => $this->time,
            'price'          => $this->price,
            // Host-set order fees (Convenience fee, Gateway fee, …) — the app previews
            // them, the server charges them. Each is { label, type, value }.
            'fees' => collect((array) ($this->fees ?? []))->map(fn (array $f): array => [
                'label' => ($f['label'] ?? 'Fee') ?: 'Fee',
                'type'  => $f['type'] ?? 'flat',
                'value' => (float) ($f['value'] ?? 0),
            ])->values(),
            // Legacy single-fee shape for older app builds that predate `fees`; mirrors
            // the first fee row so they still show one line. Newer builds read `fees`.
            'convenienceFee' => [
                'type'  => $this->fees[0]['type'] ?? 'none',
                'value' => (float) ($this->fees[0]['value'] ?? 0),
                'label' => $this->fees[0]['label'] ?? 'Convenience fee',
            ],
            'totalSlots'     => $this->total_slots,
            'availableSlots' => $this->available_slots,
            'images'         => \App\Support\MediaUrl::resolveMany($this->images),
            'gallery'        => $this->galleryUrls(),
            'status'         => $this->status,
            // Curated app rails this event appears in (e.g. ["for_you","trending"]).
            'placements'     => array_values(array_filter(
                (array) ($this->placements ?? []),
                static fn ($p): bool => is_string($p) && trim($p) !== '',
            )),
            // Aggregate rating; null when unrated so the app shows nothing (no fake star).
            'rating'         => $this->rating !== null ? (float) $this->rating : null,
            'ratingsCount'   => (int) ($this->ratings_count ?? 0),
            'partnerId'      => $this->partner_id,
            // The organiser's public page, when they have a live one (Phase 2).
            'host'           => $this->hostPayload($request),
            'createdAt'      => $this->created_at,
            'infoNotes'      => array_values(array_filter(
                (array) ($this->info_notes ?? []),
                static fn ($n): bool => is_string($n) && trim($n) !== '',
            )),
            'goodToKnow'     => $this->resource->goodToKnowRows(),
            'schedule'       => $this->resource->scheduleRows(),
            'lineup'         => $this->resource->lineupRows(),
            'ticketTypes'    => $this->whenLoaded('ticketTypes', fn () => $this->ticketTypes->map(fn ($t) => [
                'id'        => $t->id,
                'name'      => $t->name,
                'kind'      => $t->kind,
                // `price` is the live price a buyer pays now (current phase, else flat).
                'price'     => $t->effectivePrice(),
                'basePrice' => $t->price,
                'admits'    => $t->admits,
                'minPrice'  => $t->min_price,
                'capacity'  => $t->capacity,
                'sold'      => $t->sold,
                'remaining' => $t->remaining(),
                'onSale'    => $t->isOnSale(),
                // Empty for flat-price tiers; drives the app's "Pricing Schedule" widget.
                'phases'    => $t->phaseSchedule(),
            ])->values()),
        ];
    }

    /**
     * The event organiser's public profile, or null when they don't have a live one.
     *
     * @return array<string, mixed>|null
     */
    private function hostPayload(Request $request): ?array
    {
        $profile = $this->resource->partner?->hostProfile;

        if ($profile === null || ! $profile->isLive()) {
            return null;
        }

        return [
            'name'        => $profile->display_name,
            'slug'        => $profile->slug,
            'logo'        => $profile->logoUrl(),
            'verified'    => $profile->isVerified(),
            'url'         => url('/host/' . $profile->slug),
            'followers'   => $profile->followersCount(),
            'isFollowing' => $profile->isFollowedBy($request->user()),
        ];
    }
}
