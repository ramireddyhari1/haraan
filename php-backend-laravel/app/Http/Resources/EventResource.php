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
        ];
    }
}
