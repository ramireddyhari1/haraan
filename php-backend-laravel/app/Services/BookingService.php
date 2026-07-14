<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBlockedDate;
use App\Models\VenueCourt;
use App\Models\VenueSlot;
use Illuminate\Support\Carbon;
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
     * Create a confirmed venue booking for a customer (online / app).
     *
     * The booking reserves a physical {@see VenueCourt} for a time window; because a court
     * can host several sports, the overlap check locks it across every sport, so the same
     * ground shared by football and cricket can't be double-booked. Price is the court's own
     * hourly rate (or the venue price) times the duration in hours.
     *
     * @throws NotFoundHttpException  When the venue, slot or court does not exist.
     * @throws ConflictHttpException  When the venue isn't bookable or the window is taken.
     */
    public function createVenueBooking(
        User $user,
        int $venueId,
        ?int $slotId,
        string $date,
        ?int $courtId = null,
        int $duration = 1,
    ): Booking {
        return $this->reserveVenue($venueId, $slotId, $courtId, $date, $duration, [
            'user_id'     => $user->id,
            'channel'     => 'online',
            'guest_name'  => null,
            'guest_phone' => null,
        ]);
    }

    /**
     * Create a walk-in (offline) venue booking at the partner desk. The customer has no app
     * account, so their contact details ride on the booking and `user_id` holds the partner
     * who took it. Same court + time-window conflict rules as the online path.
     *
     * @throws NotFoundHttpException  When the venue, slot or court does not exist.
     * @throws ConflictHttpException  When the venue isn't bookable or the window is taken.
     */
    public function createOfflineVenueBooking(
        User $partner,
        int $venueId,
        ?int $slotId,
        string $date,
        ?string $guestName,
        ?string $guestPhone,
        ?int $courtId = null,
        int $duration = 1,
    ): Booking {
        return $this->reserveVenue($venueId, $slotId, $courtId, $date, $duration, [
            'user_id'     => $partner->id,
            'channel'     => 'offline',
            'guest_name'  => $guestName,
            'guest_phone' => $guestPhone,
        ]);
    }

    /**
     * Shared reservation routine behind the online and offline venue-booking paths.
     * Validates the venue/slot/court, rejects blocked dates, enforces the court+window
     * overlap rule, and writes the confirmed booking — all inside one locked transaction.
     *
     * @param array{user_id:int,channel:string,guest_name:?string,guest_phone:?string} $meta
     */
    private function reserveVenue(int $venueId, ?int $slotId, ?int $courtId, string $date, int $duration, array $meta): Booking
    {
        $duration = max(1, $duration);
        $date = date('Y-m-d', strtotime($date) ?: time());

        /** @var Booking $booking */
        $booking = DB::transaction(function () use ($venueId, $slotId, $courtId, $date, $duration, $meta): Booking {
            $venue = Venue::query()->lockForUpdate()->find($venueId);

            if ($venue === null || ! $venue->is_active) {
                throw new NotFoundHttpException('Venue not found');
            }

            if (! $venue->is_bookable) {
                throw new ConflictHttpException('This venue is not open for booking');
            }

            // Owner-blocked day (holiday / maintenance) — no bookings taken.
            $blocked = VenueBlockedDate::query()
                ->where('venue_id', $venue->id)
                ->whereDate('date', $date)
                ->exists();

            if ($blocked) {
                throw new ConflictHttpException('This venue is closed on that date');
            }

            // Resolve the court (physical unit) — the thing that can only hold one booking
            // at a time. Its own price wins over the venue base price when set.
            $court = null;
            if ($courtId !== null) {
                $court = VenueCourt::query()->where('venue_id', $venueId)->find($courtId);

                if ($court === null || ! $court->is_active) {
                    throw new NotFoundHttpException('Court not found');
                }
            }

            // Resolve the slot (start-time template) and per-hour price.
            $slot = null;
            $perHour = (int) ($court->price ?? $venue->price ?? 0);
            $startMin = null;
            $dayLabel = null;
            $timeLabel = null;

            if ($slotId !== null) {
                $slot = VenueSlot::query()->where('venue_id', $venueId)->find($slotId);

                if ($slot === null) {
                    throw new NotFoundHttpException('Slot not found');
                }

                if (! $slot->is_available) {
                    throw new ConflictHttpException('That slot is not available');
                }

                if ($court === null && (int) $slot->price > 0) {
                    $perHour = (int) $slot->price;
                }

                $dayLabel = $slot->day;
                $timeLabel = $slot->time;
                $startMin = $this->timeToMinutes($slot->time);
            }

            // Per-court peak pricing wins when it applies (weekday/time window on the court).
            if ($court !== null) {
                $perHour = $court->rateFor(Carbon::parse($date), $timeLabel, (int) ($venue->price ?? 0));
            }

            $endMin = $startMin !== null ? $startMin + $duration * 60 : null;
            $startHm = $startMin !== null ? $this->minutesToHm($startMin) : null;
            $endHm = $endMin !== null ? $this->minutesToHm($endMin) : null;

            $this->assertWindowFree($venue->id, $courtId, $slotId, $date, $startMin, $endMin);

            return Booking::query()->create([
                'quantity'       => 1,
                'total_amount'   => (float) ($perHour * $duration),
                'status'         => 'CONFIRMED',
                'booking_type'   => 'venue',
                'user_id'        => $meta['user_id'],
                'event_id'       => null,
                'venue_id'       => $venue->id,
                'venue_slot_id'  => $slotId,
                'venue_court_id' => $courtId,
                'slot_date'      => $date,
                'start_time'     => $startHm,
                'end_time'       => $endHm,
                'slot_label'     => $this->bookingLabel($court?->name, $dayLabel, $timeLabel, $endHm),
                'channel'        => $meta['channel'],
                'guest_name'     => $meta['guest_name'],
                'guest_phone'    => $meta['guest_phone'],
            ]);
        });

        return $booking;
    }

    /**
     * Reject the reservation if it clashes with an existing confirmed booking.
     *
     * When a court is chosen, two bookings conflict if they share that court on the date and
     * their [start,end) windows overlap — the sport is irrelevant, which is exactly what stops
     * one physical court being sold to football and cricket at the same time. When no court is
     * chosen (venues that don't model courts), we fall back to the legacy one-booking-per-slot
     * rule so those venues keep working unchanged.
     *
     * @throws ConflictHttpException  When the window (or slot) is already taken.
     */
    private function assertWindowFree(int $venueId, ?int $courtId, ?int $slotId, string $date, ?int $startMin, ?int $endMin): void
    {
        if ($courtId !== null) {
            $existing = Booking::query()
                ->where('booking_type', 'venue')
                ->where('venue_id', $venueId)
                ->where('venue_court_id', $courtId)
                ->whereDate('slot_date', $date)
                // Filament-created bookings store lowercase 'confirmed'; match case-insensitively.
                ->whereRaw('lower(status) = ?', ['confirmed'])
                ->get(['start_time', 'end_time']);

            foreach ($existing as $b) {
                $es = $this->timeToMinutes($b->start_time);
                $ee = $this->timeToMinutes($b->end_time);

                // A booking with no window (or ours has none) coarsely blocks the whole day —
                // safer than silently allowing a possible clash we can't reason about.
                if ($startMin === null || $endMin === null || $es === null || $ee === null) {
                    throw new ConflictHttpException('That court is already booked for this date');
                }

                if ($startMin < $ee && $endMin > $es) {
                    throw new ConflictHttpException('That court is already booked for this time');
                }
            }

            return;
        }

        if ($slotId !== null) {
            $taken = Booking::query()
                ->where('booking_type', 'venue')
                ->where('venue_id', $venueId)
                ->where('venue_slot_id', $slotId)
                ->whereDate('slot_date', $date)
                ->whereRaw('lower(status) = ?', ['confirmed'])
                ->exists();

            if ($taken) {
                throw new ConflictHttpException('That slot is already booked for this date');
            }
        }
    }

    /** Human-readable booking label, e.g. "Court 1 · Today · 7:00 PM – 8:00 PM". */
    private function bookingLabel(?string $court, ?string $day, ?string $time, ?string $endHm): string
    {
        $window = trim(($day ?? '').' · '.($time ?? ''), " ·\t");
        if ($time !== null && $endHm !== null) {
            $endLabel = date('g:i A', strtotime($endHm) ?: 0);
            $window = trim(($day ?? '').' · '.$time.' – '.$endLabel, " ·\t");
        }

        return trim(($court !== null ? $court.' · ' : '').$window, " ·\t");
    }

    /** Parse a time label ("7:00 PM", "07:00", "19:00") to minutes-from-midnight, or null. */
    private function timeToMinutes(?string $label): ?int
    {
        if ($label === null || trim($label) === '') {
            return null;
        }

        $ts = strtotime(trim($label));
        if ($ts === false) {
            return null;
        }

        return (int) date('G', $ts) * 60 + (int) date('i', $ts);
    }

    /** Format minutes-from-midnight as 24h "HH:MM", clamped to a single day. */
    private function minutesToHm(int $minutes): string
    {
        $minutes = max(0, min(24 * 60, $minutes));

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
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
     * Cancel a venue booking on behalf of the partner who owns it (desk / partner app).
     * The booking's venue must belong to the acting partner (admins may cancel anything).
     * Idempotent: an already-cancelled booking is returned unchanged.
     *
     * @throws NotFoundHttpException      When the booking does not exist.
     * @throws AccessDeniedHttpException  When the venue isn't the partner's.
     */
    public function cancelAsPartner(User $partner, string $bookingId): Booking
    {
        $booking = Booking::query()->with('venue')->find($bookingId);

        if ($booking === null) {
            throw new NotFoundHttpException('Booking not found');
        }

        if ($partner->role !== 'ADMIN') {
            $partnerId = $partner->effectivePartnerId();
            $ownsVenue = $partnerId !== null
                && $booking->venue !== null
                && (int) $booking->venue->partner_id === (int) $partnerId;

            if (! $ownsVenue) {
                throw new AccessDeniedHttpException('This booking is not on your venue');
            }
        }

        if (strtolower((string) $booking->status) === 'cancelled') {
            return $booking;
        }

        $booking->status = 'CANCELLED';
        $booking->save();

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
