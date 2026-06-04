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
            'status'      => $this->status,
            'seatNumbers' => $this->seat_numbers,
            'couponCode'  => $this->coupon_code,
            'discount'    => $this->discount,
            'userId'      => $this->user_id,
            'eventId'     => $this->event_id,
            'createdAt'   => $this->created_at,
        ];
    }
}
