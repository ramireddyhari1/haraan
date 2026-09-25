<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Venue;
use App\Services\Membership\MemberBookingPerks;
use Illuminate\Support\Carbon;

/**
 * How far ahead a customer may book a venue: the venue's own window (or the platform
 * default), plus any priority days the customer's plan adds. Partner desk bookings are not
 * held to it — the owner can always put a booking in their own calendar.
 */
final class VenueBookingWindow
{
    public function __construct(private readonly MemberBookingPerks $perks) {}

    /** The last date [user] may book at [venue], inclusive. */
    public function lastBookableDate(Venue $venue, ?User $user): Carbon
    {
        return now()->startOfDay()->addDays($venue->bookingWindowDays() + $this->perks->priorityBookingDays($user));
    }

    public function allows(Venue $venue, ?User $user, Carbon $date): bool
    {
        return $date->copy()->startOfDay()->lte($this->lastBookableDate($venue, $user));
    }

    /**
     * The window as the app shows it.
     *
     * @return array{days: int, priority_days: int, last_date: string}
     */
    public function describe(Venue $venue, ?User $user): array
    {
        return [
            'days' => $venue->bookingWindowDays(),
            'priority_days' => $this->perks->priorityBookingDays($user),
            'last_date' => $this->lastBookableDate($venue, $user)->toDateString(),
        ];
    }

    /** What a customer is told when a date is past their window. */
    public function refusal(Venue $venue, ?User $user): string
    {
        $days = $venue->bookingWindowDays() + $this->perks->priorityBookingDays($user);

        return "Bookings at {$venue->name} open {$days} days ahead. Pick a date on or before "
            .$this->lastBookableDate($venue, $user)->format('j M').'.';
    }
}
