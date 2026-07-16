<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\ContactPrefill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for the User model.
 */
final class UserResource extends JsonResource
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
            'name'        => $this->name,
            'email'       => $this->email,
            'phone'       => $this->phone,
            'avatar'      => $this->avatar,
            'role'        => $this->role,
            'playerId'    => $this->player_id,
            'district'    => $this->district,
            'state'       => $this->state,
            'partnerType' => $this->partner_type,
            'eventHostId' => $this->event_host_id,
            'createdAt'   => $this->created_at,
            // What checkout should prefill, already filtered: `email` above may be a
            // WhatsApp-signup placeholder (<phone>@whatsapp.local) that must never be
            // offered as the buyer's address. One rule, server-side, so the app and
            // the website can't drift. Blank = we don't know it; ask for it.
            'contact'     => ContactPrefill::for($this->resource),
        ];
    }
}
