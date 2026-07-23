<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for the Booking model.
 */
final class BookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'quantity'    => $this->quantity,
            'totalAmount' => $this->total_amount,
            'convenienceFee' => $this->convenience_fee,
            'status'      => $this->status,
            // Scannable entry-pass code — the app renders `haraan:ticket:<ticketCode>` as the QR.
            'ticketCode'  => $this->ticket_code,
            'seatNumbers' => $this->seat_numbers,
            'couponCode'  => $this->coupon_code,
            'discount'    => $this->discount,
            'userId'      => $this->user_id,
            'eventId'     => $this->event_id,
            'ticketTypeId'   => $this->ticket_type_id,
            'ticketTypeName' => $this->whenLoaded('ticketType', fn () => $this->ticketType?->name),
            'type'        => $this->booking_type ?? 'event',
            'event'       => $this->whenLoaded('event', fn () => $this->event ? [
                'title' => $this->event->title,
                'venue' => $this->event->venue,
                'date'  => $this->event->date,
                'image' => $this->event->images[0] ?? null,
                // Drives the pass's "Directions" button; null when the admin left it blank.
                'mapLink' => $this->event->map_link,
            ] : null),
            // Venue-slot booking fields (null for event bookings)
            'venueId'     => $this->venue_id,
            'slotDate'    => $this->slot_date?->toDateString(),
            'slotLabel'   => $this->slot_label,
            'venue'       => $this->whenLoaded('venue', fn () => $this->venue ? [
                'name'     => $this->venue->name,
                'location' => $this->venue->location,
                'image'    => $this->venue->images[0] ?? null,
                'mapLink'  => $this->venue->map_link,
            ] : null),
            'createdAt'   => $this->created_at,
        ];
    }
}
