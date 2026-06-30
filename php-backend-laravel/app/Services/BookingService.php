<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueSlot;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Domain service for booking lifecycle operations.
 *
 * Encapsulates the transactional logic for creating and cancelling
 * bookings, including slot accounting on the related {@see Event}.
 */
final class BookingService
{
    /**
     * Create a new confirmed booking inside a DB transaction.
     *
     * Decrements the event's available slots and persists the booking
     * atomically. Callers are expected to have already validated the
     * input data before reaching this method.
     *
     * @param array{
     *     eventId: int,
     *     quantity: int,
     *     totalAmount?: float,
     *     seatNumbers?: list<string>,
     *     couponCode?: string|null,
     *     discount?: float
     * } $data
     *
     * @throws NotFoundHttpException  When the event does not exist.
     * @throws ConflictHttpException  When there are not enough available slots.
     */
    public function create(User $user, array $data): Booking
    {
        $eventId  = (int) $data['eventId'];
        $quantity = (int) $data['quantity'];

        /** @var Booking $booking */
        $booking = DB::transaction(static function () use ($user, $eventId, $quantity, $data): Booking {
            // Retrieve and lock the event row to prevent double-booking race conditions
            $event = Event::query()->lockForUpdate()->find($eventId);

            if ($event === null) {
                throw new NotFoundHttpException('Event not found');
            }

            if ($event->available_slots < $quantity) {
                throw new ConflictHttpException('Not enough seats available');
            }

            $event->available_slots -= $quantity;
            $event->save();

            return Booking::query()->create([
                'quantity'     => $quantity,
                'total_amount' => (float) ($data['totalAmount'] ?? $event->price * $quantity),
                'status'       => 'CONFIRMED',
                'seat_numbers' => $data['seatNumbers'] ?? [],
                'coupon_code'  => $data['couponCode'] ?? null,
                'discount'     => (float) ($data['discount'] ?? 0),
                'user_id'      => $user->id,
                'event_id'     => $event->id,
            ]);
        });

        return $booking;
    }


    /**
     * Create a confirmed venue-slot booking. Validates the venue is bookable, the slot
     * (if given) belongs to the venue and is available, and that the same slot+date isn't
     * already taken. Price is the venue's listed price.
     *
     * @throws NotFoundHttpException  When the venue or slot does not exist.
     * @throws ConflictHttpException  When the venue isn't bookable or the slot is taken.
     */
    public function createVenueBooking(User $user, int $venueId, ?int $slotId, string $date): Booking
    {
        /** @var Booking $booking */
        $booking = DB::transaction(static function () use ($user, $venueId, $slotId, $date): Booking {
            $venue = Venue::query()->lockForUpdate()->find($venueId);

            if ($venue === null || ! $venue->is_active) {
                throw new NotFoundHttpException('Venue not found');
            }

            if (! $venue->is_bookable) {
                throw new ConflictHttpException('This venue is not open for booking');
            }

            $label = null;

            if ($slotId !== null) {
                $slot = VenueSlot::query()->where('venue_id', $venueId)->find($slotId);

                if ($slot === null) {
                    throw new NotFoundHttpException('Slot not found');
                }

                if (! $slot->is_available) {
                    throw new ConflictHttpException('That slot is not available');
                }

                $label = trim(($slot->day ?? '').' · '.($slot->time ?? ''), " ·\t");

                $taken = Booking::query()
                    ->where('booking_type', 'venue')
                    ->where('venue_id', $venueId)
                    ->where('venue_slot_id', $slotId)
                    ->whereDate('slot_date', $date)
                    ->where('status', 'CONFIRMED')
                    ->exists();

                if ($taken) {
                    throw new ConflictHttpException('That slot is already booked for this date');
                }
            }

            return Booking::query()->create([
                'quantity'      => 1,
                'total_amount'  => (float) ($venue->price ?? 0),
                'status'        => 'CONFIRMED',
                'booking_type'  => 'venue',
                'user_id'       => $user->id,
                'event_id'      => null,
                'venue_id'      => $venue->id,
                'venue_slot_id' => $slotId,
                'slot_date'     => $date,
                'slot_label'    => $label,
            ]);
        });

        return $booking;
    }

    /**
     * Cancel an existing booking and restore the event's available slots.
     *
     * Only the booking owner or an admin may cancel. If the booking is
     * already cancelled the method returns it unchanged.
     *
     * @throws NotFoundHttpException      When the booking does not exist.
     * @throws AccessDeniedHttpException  When the user is not authorised.
     */
    public function cancel(User $user, string $bookingId): Booking
    {
        $booking = Booking::query()->find($bookingId);

        if ($booking === null) {
            throw new NotFoundHttpException('Booking not found');
        }

        if ($user->role !== 'ADMIN' && (int) $booking->user_id !== (int) $user->id) {
            throw new AccessDeniedHttpException('Forbidden');
        }

        if ($booking->status === 'CANCELLED') {
            return $booking;
        }

        DB::transaction(static function () use ($booking): void {
            $event = Event::query()->find($booking->event_id);

            if ($event !== null) {
                $event->available_slots += (int) $booking->quantity;
                $event->save();
            }

            $booking->status = 'CANCELLED';
            $booking->save();
        });

        return $booking;
    }
}
