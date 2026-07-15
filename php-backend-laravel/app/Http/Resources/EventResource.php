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
            // Host-set convenience fee config — the app previews it, the server charges it.
            'convenienceFee' => [
                'type'  => $this->convenience_fee_type ?? 'none',
                'value' => (float) ($this->convenience_fee_value ?? 0),
            ],
            'totalSlots'     => $this->total_slots,
            'availableSlots' => $this->available_slots,
            'images'         => \App\Support\MediaUrl::resolveMany($this->images),
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
            'createdAt'      => $this->created_at,
            'infoNotes'      => array_values(array_filter(
                (array) ($this->info_notes ?? []),
                static fn ($n): bool => is_string($n) && trim($n) !== '',
            )),
            'goodToKnow'     => $this->resource->goodToKnowRows(),
            'schedule'       => collect((array) ($this->schedule ?? []))
                ->filter(fn ($r) => is_array($r) && trim((string) ($r['time'] ?? '')) !== '')
                ->map(fn ($r) => [
                    'time'  => trim((string) ($r['time'] ?? '')),
                    'title' => trim((string) ($r['title'] ?? '')),
                    'note'  => trim((string) ($r['note'] ?? '')),
                ])
                ->values(),
            'lineup'         => collect((array) ($this->lineup ?? []))
                ->filter(fn ($r) => is_array($r) && trim((string) ($r['name'] ?? '')) !== '')
                ->map(function ($r) {
                    // Uploaded photo wins; fall back to a pasted image URL.
                    $upload = is_array($r['image'] ?? null) ? ($r['image'][0] ?? '') : ($r['image'] ?? '');
                    $upload = trim((string) $upload);
                    $image  = $upload !== '' ? $upload : trim((string) ($r['image_url'] ?? ''));

                    return [
                        'name'     => trim((string) ($r['name'] ?? '')),
                        'subtitle' => trim((string) ($r['subtitle'] ?? '')),
                        'image'    => \App\Support\MediaUrl::resolve($image !== '' ? $image : null) ?? '',
                    ];
                })
                ->values(),
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
}
