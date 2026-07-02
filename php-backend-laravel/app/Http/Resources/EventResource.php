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
            'venue'          => $this->venue,
            'date'           => $this->date,
            'time'           => $this->time,
            'price'          => $this->price,
            'totalSlots'     => $this->total_slots,
            'availableSlots' => $this->available_slots,
            'images'         => $this->images,
            'status'         => $this->status,
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
                ->map(fn ($r) => [
                    'name'     => trim((string) ($r['name'] ?? '')),
                    'subtitle' => trim((string) ($r['subtitle'] ?? '')),
                    'image'    => trim((string) (is_array($r['image'] ?? null) ? ($r['image'][0] ?? '') : ($r['image'] ?? ''))),
                ])
                ->values(),
            'ticketTypes'    => $this->whenLoaded('ticketTypes', fn () => $this->ticketTypes->map(fn ($t) => [
                'id'        => $t->id,
                'name'      => $t->name,
                'kind'      => $t->kind,
                'price'     => $t->price,
                'admits'    => $t->admits,
                'minPrice'  => $t->min_price,
                'capacity'  => $t->capacity,
                'sold'      => $t->sold,
                'remaining' => $t->remaining(),
                'onSale'    => $t->isOnSale(),
            ])->values()),
        ];
    }
}
