<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueSlot;
use Illuminate\Support\Collection;
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
        // Legacy single-line entry point — normalise to one order line and return
        // the (single) booking so existing callers keep their shape.
        $lines = [[
            'ticketTypeId' => isset($data['ticketTypeId']) ? (int) $data['ticketTypeId'] : null,
            'quantity'     => (int) ($data['quantity'] ?? 1),
        ]];

        return $this->createOrder(
            $user,
            (int) $data['eventId'],
            $lines,
            $data['couponCode'] ?? null,
        )->first();
    }

    /**
     * Create a confirmed order of one or more ticket lines in a single transaction.
     *
     * Each line becomes its own {@see Booking} row (its own scannable code) priced
     * server-side from the tier's live phase price — never trusting a client total.
     * Per-tier inventory (`sold`) and the event's overall `available_slots` are
     * decremented atomically; a shortfall on any line rolls the whole order back.
     *
     * @param  list<array{ticketTypeId: int|null, quantity: int}>  $lines
     * @return Collection<int, Booking>
     *
     * @throws NotFoundHttpException  When the event or a referenced tier is missing.
     * @throws ConflictHttpException  When the event or a tier lacks inventory.
     */
    public function createOrder(User $user, int $eventId, array $lines, ?string $couponCode = null): Collection
    {
        // Collapse duplicate tier lines and drop non-positive quantities up front.
        $normalised = [];
        foreach ($lines as $line) {
            $qty = max(0, (int) ($line['quantity'] ?? 0));
            if ($qty === 0) {
                continue;
            }
            $key = $line['ticketTypeId'] ?? 'flat';
            $normalised[$key] = [
                'ticketTypeId' => $line['ticketTypeId'] ?? null,
                'quantity'     => ($normalised[$key]['quantity'] ?? 0) + $qty,
            ];
        }

        if ($normalised === []) {
            throw new ConflictHttpException('Your cart is empty');
        }

        $totalTickets = array_sum(array_column($normalised, 'quantity'));

        return DB::transaction(function () use ($user, $eventId, $normalised, $totalTickets, $couponCode): Collection {
            $event = Event::query()->lockForUpdate()->find($eventId);

            if ($event === null) {
                throw new NotFoundHttpException('Event not found');
            }

            if ($event->available_slots < $totalTickets) {
                throw new ConflictHttpException('Not enough seats available');
            }

            $bookings = collect();

            foreach ($normalised as $line) {
                $qty      = (int) $line['quantity'];
                $tierId   = $line['ticketTypeId'];
                $tier     = null;
                $unit     = (float) $event->price;

                if ($tierId !== null) {
                    /** @var TicketType|null $tier */
                    $tier = TicketType::query()
                        ->lockForUpdate()
                        ->where('event_id', $event->id)
                        ->find($tierId);

                    if ($tier === null) {
                        throw new NotFoundHttpException('Ticket type not found');
                    }

                    if (! $tier->isOnSale()) {
                        throw new ConflictHttpException("“{$tier->name}” is not on sale right now");
                    }

                    $remaining = $tier->remaining();
                    if ($remaining !== null && $remaining < $qty) {
                        throw new ConflictHttpException("Only {$remaining} left for “{$tier->name}”");
                    }

                    // Price the whole line at the tier's live phase price. Inventory
                    // advances by ticket count (phase boundaries aren't split mid-line).
                    $unit = $tier->effectivePrice();
                    $tier->sold += $qty;
                    $tier->save();
                }

                $bookings->push(Booking::query()->create([
                    'quantity'       => $qty,
                    'total_amount'   => round($unit * $qty, 2),
                    'status'         => 'CONFIRMED',
                    'coupon_code'    => $couponCode,
                    'discount'       => 0,
                    'user_id'        => $user->id,
                    'event_id'       => $event->id,
                    'ticket_type_id' => $tier?->id,
                ]));
            }

            // Host-set convenience fee on the ticket subtotal — charged once for the
            // whole order and stored on the first booking row.
            $subtotal = (float) $bookings->sum('total_amount');
            $fee      = $event->convenienceFeeFor($subtotal);

            // Coupon: a redeemable code takes a flat ₹ amount off the payable total
            // (never below zero). Applied once for the order and its use is counted.
            $discount = 0.0;
            $coupon   = Coupon::findByCode($couponCode);
            if ($coupon !== null && $coupon->isRedeemable() && $coupon->appliesToEvent($event->id)) {
                $discount = min((float) $coupon->discount, $subtotal + $fee);
                $coupon->increment('uses');
            }

            if ($fee > 0 || $discount > 0) {
                $first = $bookings->first();
                $first->convenience_fee = $fee;
                $first->discount = round($discount, 2);
                $first->save();
            }

            $event->available_slots -= $totalTickets;
            $event->save();

            return $bookings;
        });
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
                    // Case-insensitive: bookings made via Filament store lowercase
                    // 'confirmed'; an exact 'CONFIRMED' match would miss them and
                    // allow a double-booking of the same slot.
                    ->whereRaw('lower(status) = ?', ['confirmed'])
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

        // Case-insensitive idempotency guard: a booking cancelled via Filament
        // stores lowercase 'cancelled'. An exact 'CANCELLED' check would miss it
        // and re-run the transaction, refunding inventory a second time.
        if (strtolower((string) $booking->status) === 'cancelled') {
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

    /**
     * Resolve a booking from its scannable ticket code — the payload the attendee's ticket QR
     * encodes as `haraan:ticket:<code>`. Used by the Filament host check-in scanner.
     *
     * Access is gated to staff who manage the events or venues workspace (the check-in page
     * itself is already behind `canManage('events')`); per-partner scoping is handled separately
     * by the partner API path.
     *
     * @throws AccessDeniedHttpException  When the actor may not manage attendance.
     * @throws NotFoundHttpException      When no booking matches the code.
     */
    public function resolveByCode(?User $actor, string $code): Booking
    {
        $this->assertCanManageAttendance($actor);

        $code = trim($code);

        if ($code === '') {
            throw new NotFoundHttpException('No ticket code provided');
        }

        $booking = Booking::query()
            ->with(['user', 'event', 'venue'])
            ->where('ticket_code', $code)
            ->first();

        if ($booking === null) {
            throw new NotFoundHttpException('Ticket not found');
        }

        return $booking;
    }

    /**
     * Mark a booking's party as arrived. One scan checks in the whole party (mirrors the partner
     * check-in): sets `checked_in_count` to the full quantity and stamps `checked_in_at` on the
     * first arrival. Re-scanning an already-arrived ticket is a no-op (the caller reports it as
     * "already checked in"). Void tickets (cancelled/refunded/failed) are rejected.
     *
     * @throws AccessDeniedHttpException  When the actor may not manage attendance.
     * @throws NotFoundHttpException      When the booking does not exist.
     * @throws ConflictHttpException      When the ticket is void.
     */
    public function checkIn(?User $actor, string $bookingId): Booking
    {
        $this->assertCanManageAttendance($actor);

        /** @var Booking $booking */
        $booking = DB::transaction(static function () use ($bookingId): Booking {
            $booking = Booking::query()->lockForUpdate()->find($bookingId);

            if ($booking === null) {
                throw new NotFoundHttpException('Booking not found');
            }

            if (in_array(strtolower((string) $booking->status), ['cancelled', 'refunded', 'failed'], true)) {
                throw new ConflictHttpException('This ticket is '.strtolower((string) $booking->status));
            }

            $already   = (int) $booking->checked_in_count;
            $quantity  = max(1, (int) $booking->quantity);

            if ($already < $quantity) {
                $booking->checked_in_count = $quantity;

                if ($booking->checked_in_at === null) {
                    $booking->checked_in_at = now();
                }

                $booking->save();
            }

            return $booking;
        });

        return $booking->fresh(['user', 'event', 'venue']) ?? $booking;
    }

    /**
     * Only staff managing the events or venues workspace (or a super-admin) may check attendees in.
     *
     * @throws AccessDeniedHttpException
     */
    private function assertCanManageAttendance(?User $actor): void
    {
        $allowed = $actor !== null
            && ($actor->isSuperAdmin() || $actor->canManage('events') || $actor->canManage('gamehub'));

        if (! $allowed) {
            throw new AccessDeniedHttpException('You are not allowed to check in tickets');
        }
    }
}
