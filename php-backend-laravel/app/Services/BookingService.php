<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Event;
use App\Models\EventSlot;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBlock;
use App\Models\VenueBlockedDate;
use App\Models\VenueCourt;
use App\Models\VenueSlot;
use App\Services\Membership\MemberBookingPerks;
use App\Support\ContactPrefill;
use App\Support\Operations;
use App\Support\PlatformRules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
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
     * How long a selected ticket stays locked for the buyer who reserved it.
     *
     * Reserving a ticket writes a PENDING booking that holds the seat out of the
     * pool. If the buyer doesn't pay within this window the hold lapses and the
     * seat returns to the pool for the next person — see {@see releaseExpired()}
     * and the scheduled `bookings:release-expired` sweep. Keep short so an
     * abandoned checkout doesn't strand inventory; long enough to finish paying.
     *
     * "Long enough to finish paying" is the load-bearing half, and one minute was
     * not it. A UPI collect request has to travel to the payer's bank app, wait for
     * a human to open it and approve, and travel back; card 3-D Secure adds an OTP
     * screen. Sixty seconds routinely lapses mid-payment, and the every-minute sweep
     * would then expire the hold while the charge went through — money taken against
     * a booking marked EXPIRED, invisible in every report. Fifteen minutes matches
     * what the desk-registration flow already pushed its own holds out to.
     */
    public const RESERVATION_HOLD_MINUTES = 15;

    /**
     * The hold actually applied: /control → Platform rules → Bookings (`bookings.event_hold_minutes`),
     * never below 10 minutes for the reason above. RESERVATION_HOLD_MINUTES is its default.
     */
    public static function holdMinutes(): int
    {
        return max(10, PlatformRules::int('bookings.event_hold_minutes'));
    }

    public function __construct(
        private readonly RazorpayGateway $razorpay,
        private readonly BookingLedger $ledger,
    ) {}

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
            eventSlotId: isset($data['eventSlotId']) ? (int) $data['eventSlotId'] : null,
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
    /**
     * @param  array{name?: string|null, email?: string|null, phone?: string|null}  $contact
     *         Who the ticket is for, captured at checkout. Optional so older callers
     *         (and venue bookings) keep working; stored on every row of the order.
     */
    public function createOrder(User $user, int $eventId, array $lines, ?string $couponCode = null, array $contact = [], ?int $eventSlotId = null, bool $reserve = false, bool $desk = false): Collection
    {
        // Emergency switch (/control → Operations). The host's own desk keeps working: it is
        // their box office, and the pause is about the public checkout.
        if (! $desk) {
            Operations::assertBookingsOpen(Operations::EVENTS);
        }

        // Reserve mode holds inventory in a PENDING row until payment confirms. Sweep any
        // holds that were never paid first, so their seats are back in the pool for this order.
        if ($reserve) {
            $this->releaseExpired($eventId);
        }

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

        $orderCap = PlatformRules::int('bookings.max_tickets_per_order');
        if ($orderCap > 0 && $totalTickets > $orderCap) {
            throw new ConflictHttpException("At most {$orderCap} tickets per order");
        }

        // Fall back to the account for anything checkout didn't supply, so a booking
        // always carries a contact even from a caller that doesn't collect one.
        $attendee = [
            'attendee_name' => self::clean($contact['name'] ?? null) ?? (trim((string) $user->name) ?: null),
            'attendee_email' => self::clean($contact['email'] ?? null)
                ?? (ContactPrefill::isRealEmail($user->email) ? trim((string) $user->email) : null),
            'attendee_phone' => self::clean($contact['phone'] ?? null) ?? (trim((string) $user->phone) ?: null),
        ];

        return DB::transaction(function () use ($user, $eventId, $normalised, $totalTickets, $couponCode, $attendee, $eventSlotId, $reserve): Collection {
            $event = Event::query()->lockForUpdate()->find($eventId);

            if ($event === null) {
                throw new NotFoundHttpException('Event not found');
            }

            // Host's manual "Sold out" override closes sales regardless of slot count.
            if ($event->is_sold_out) {
                throw new ConflictHttpException('This event is sold out');
            }

            if ($event->available_slots < $totalTickets) {
                throw new ConflictHttpException('Not enough seats available');
            }

            // Resolve the session ("time slot") this order is for. When the event runs
            // across several sessions one must be chosen; a single-session event defaults
            // to its sole slot so per-session inventory stays accurate everywhere.
            $slot     = null;
            $slotIds  = $event->slots()->orderBy('sort')->orderBy('id')->pluck('id');
            $slotCount = $slotIds->count();

            if ($eventSlotId !== null) {
                /** @var EventSlot|null $slot */
                $slot = EventSlot::query()->lockForUpdate()->where('event_id', $event->id)->find($eventSlotId);

                if ($slot === null) {
                    throw new NotFoundHttpException('Session not found');
                }
            } elseif ($slotCount > 1) {
                throw new ConflictHttpException('Please choose a session');
            } elseif ($slotCount === 1) {
                $slot = EventSlot::query()->lockForUpdate()->find($slotIds->first());
            }

            if ($slot !== null) {
                $slotLeft = $slot->remaining();
                if ($slotLeft !== null && $slotLeft < $totalTickets) {
                    throw new ConflictHttpException("Only {$slotLeft} left for “{$slot->displayLabel()}”");
                }
            }

            // Eloquent's collection, not collect(): callers relation-load the result
            // (BookingsController does `->load('ticketType')`), and a plain Support
            // collection has no load() — that threw AFTER this transaction committed,
            // so the app's booking succeeded in the DB and still returned a 500.
            $bookings = new EloquentCollection();

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

                    // A member's early-access perk opens the tier's sales window sooner.
                    if (! $tier->isOnSale(app(MemberBookingPerks::class)->earlyAccessHours($user))) {
                        throw new ConflictHttpException("“{$tier->name}” is not on sale right now");
                    }

                    // Sequential release: a later-phase tier can't be bought until every
                    // earlier phase has sold out (see Event::phaseReleased()).
                    if (! $event->phaseReleased((int) $tier->release_phase)) {
                        throw new ConflictHttpException("“{$tier->name}” isn’t on sale yet");
                    }

                    $remaining = $tier->remaining();
                    if ($remaining !== null && $remaining < $qty) {
                        throw new ConflictHttpException("Only {$remaining} left for “{$tier->name}”");
                    }

                    // Bulk-booking bounds: a tier may set a min/max per order.
                    ['min' => $minQty, 'max' => $maxQty] = $tier->orderBounds();
                    if ($qty < $minQty) {
                        throw new ConflictHttpException("Buy at least {$minQty} of “{$tier->name}”");
                    }
                    if ($qty > $maxQty) {
                        throw new ConflictHttpException("At most {$maxQty} of “{$tier->name}” per order");
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
                    // A reserved order sits PENDING (holding its seats) until payment
                    // confirms; the legacy direct path still writes CONFIRMED immediately.
                    'status'         => $reserve ? 'PENDING' : 'CONFIRMED',
                    'reserved_until' => $reserve ? now()->addMinutes(self::holdMinutes()) : null,
                    'coupon_code'    => $couponCode,
                    'discount'       => 0,
                    'user_id'        => $user->id,
                    'event_id'       => $event->id,
                    'ticket_type_id' => $tier?->id,
                    'event_slot_id'  => $slot?->id,
                    ...$attendee,
                ]));
            }

            // Advance the session's own sold count so per-session inventory tracks
            // alongside the tier and event totals.
            if ($slot !== null) {
                $slot->sold += $totalTickets;
                $slot->save();
            }

            // Every charge on the order comes from Event::orderCharges() — host fees, the
            // platform and gateway fees, tax — charged once for the whole order and stored on
            // the first booking row. The coupon is clamped against the fees the customer pays.
            $subtotal = (float) $bookings->sum('total_amount');
            $quote = $event->orderCharges($subtotal);

            // Coupon: a redeemable code takes an amount off the payable total (never below
            // zero). Same resolution the checkout preview ran, re-done here on the prices
            // this transaction actually wrote — the preview is a quote, this is the charge.
            $applied = $this->resolveCoupon($user, $event->id, $couponCode, $subtotal, $quote['charges_before_discount'], $totalTickets);
            $charges = $event->orderCharges($subtotal, $applied['discount']);

            // Payments paused (/control → Operations): a free order still goes through, a paid
            // one is refused before any seat is held.
            if ($reserve && $charges['total'] > 0) {
                Operations::assertPaymentsOn();
            }

            // A reserved (unpaid) order must not yet burn a coupon use — that's counted
            // on confirmation, so an abandoned checkout doesn't consume the code.
            if ($applied['coupon'] !== null && ! $reserve) {
                $applied['coupon']->recordUse();
            }

            $first = $bookings->first();
            $first->convenience_fee = $charges['fees'];
            $first->platform_fee = $charges['platform_fee'];
            $first->gateway_fee = $charges['gateway_fee'];
            $first->tax_amount = $charges['tax'];
            $first->host_deduction = $charges['host_deduction'];
            $first->discount = $charges['discount'];
            if ($first->isDirty()) {
                $first->save();
            }

            $event->available_slots -= $totalTickets;
            $event->save();

            return $bookings;
        });
    }

    /**
     * Decide whether a code applies to an order, and for how much.
     *
     * The single place that answers that question: checkout calls it to preview a
     * discount the moment the buyer hits Apply, and {@see createOrder()} calls it
     * again to apply the discount for real. Sharing it is the point — a preview that
     * disagreed with the charge would be worse than no preview at all.
     *
     * `coupon` is non-null only when the code is genuinely usable (that's the caller's
     * cue to count a use); otherwise `message` says why not, in words meant for the buyer.
     * The discount never exceeds the payable total, so an order can't go below ₹0.
     *
     * @return array{coupon: Coupon|null, discount: float, message: string}
     */
    /**
     * The venue twin of {@see resolveCoupon()}. Same shape, same rejection messages, same
     * cap on the discount — only the applicability check differs, because a coupon reaches
     * turf through `scope`/`venue_id` rather than `event_id`.
     *
     * Kept as its own method rather than adding a nullable $venueId to the event resolver:
     * that one is called from the ticket checkout and the coupon preview, and threading a
     * second product through it is how the two flows start silently sharing bugs.
     *
     * @return array{coupon: ?Coupon, discount: float, message: string}
     */
    public function resolveVenueCoupon(User $user, int $venueId, ?string $code, float $subtotal, float $fee = 0.0): array
    {
        $reject = static fn (string $message): array => ['coupon' => null, 'discount' => 0.0, 'message' => $message];

        $coupon = Coupon::findByCode($code);

        if ($coupon === null || ! $coupon->isRedeemable() || ! $coupon->usableBy($user)) {
            return $reject('This code isn’t valid.');
        }

        if ($this->ownedCouponAlreadyInUse($coupon, (int) $user->id)) {
            return $reject('You’ve already used this code.');
        }

        if (! $coupon->appliesToVenue($venueId)) {
            return $reject('This code isn’t valid for this venue.');
        }

        if (! $coupon->meetsMinOrder($subtotal)) {
            return $reject('Applies on orders over ₹' . number_format((float) $coupon->min_order) . '.');
        }

        if (! $this->couponWithinPerCustomerLimit($coupon, (int) $user->id)) {
            return $reject('You’ve already used this code.');
        }

        return [
            'coupon'   => $coupon,
            // Never discount below zero: the charge is subtotal + fee, so that is the cap.
            'discount' => round(min($coupon->discountFor($subtotal), $subtotal + $fee), 2),
            'message'  => 'Coupon applied.',
        ];
    }

    public function resolveCoupon(User $user, int $eventId, ?string $code, float $subtotal, float $fee = 0.0, ?int $tickets = null): array
    {
        $reject = static fn (string $message): array => ['coupon' => null, 'discount' => 0.0, 'message' => $message];

        $coupon = Coupon::findByCode($code);

        if ($coupon === null || ! $coupon->isRedeemable() || ! $coupon->usableBy($user)) {
            return $reject('This code isn’t valid.');
        }

        if ($this->ownedCouponAlreadyInUse($coupon, (int) $user->id)) {
            return $reject('You’ve already used this code.');
        }

        if (! $coupon->appliesToEvent($eventId)) {
            return $reject('This code isn’t valid for this event.');
        }

        if (! $coupon->meetsMinOrder($subtotal)) {
            return $reject('Applies on orders over ₹' . number_format((float) $coupon->min_order) . '.');
        }

        if (! $coupon->meetsMinTickets($tickets)) {
            return $reject('Applies on ' . (int) $coupon->min_tickets . ' tickets or more.');
        }

        if (! $this->couponWithinPerCustomerLimit($coupon, (int) $user->id)) {
            return $reject('You’ve already used this code.');
        }

        return [
            'coupon'   => $coupon,
            'discount' => round(min($coupon->discountFor($subtotal), $subtotal + $fee), 2),
            'message'  => 'Coupon applied.',
        ];
    }

    /**
     * Whether this user is still under the coupon's per-customer usage cap (null = unlimited).
     *
     * One discounted booking row is written per order (the discount lives on the first row of
     * an order), so counting this user's discounted rows for this code counts their prior orders
     * that actually used it.
     */
    private function ownedCouponAlreadyInUse(Coupon $coupon, int $userId): bool
    {
        // A single-use reward coupon is spent once an order carrying it is confirmed — or
        // while one is still waiting for payment, so two checkouts opened side by side can't
        // both take the discount. An abandoned (expired/cancelled) checkout gives it back.
        if ($coupon->owner_user_id === null) {
            return false;
        }

        return Booking::query()
            ->where('user_id', $userId)
            ->whereRaw('lower(coupon_code) = ?', [strtolower((string) $coupon->code)])
            ->where('discount', '>', 0)
            ->whereRaw('upper(status) in (?, ?)', ['PENDING', 'CONFIRMED'])
            ->exists();
    }

    private function couponWithinPerCustomerLimit(Coupon $coupon, int $userId): bool
    {
        if ($coupon->per_customer_limit === null) {
            return true;
        }

        $used = Booking::query()
            ->where('user_id', $userId)
            ->whereRaw('lower(coupon_code) = ?', [strtolower((string) $coupon->code)])
            ->where('discount', '>', 0)
            ->count();

        return $used < (int) $coupon->per_customer_limit;
    }

    /**
     * Stamp the freshly-created Razorpay order id onto every row of a reserved order, so the
     * confirm step can find them all by that id. Returns the same collection for chaining.
     *
     * @param  Collection<int, Booking>  $bookings
     * @return Collection<int, Booking>
     */
    public function attachOrderId(Collection $bookings, string $razorpayOrderId): Collection
    {
        $ids = $bookings->pluck('id')->all();

        Booking::query()->whereIn('id', $ids)->update(['razorpay_order_id' => $razorpayOrderId]);

        return $bookings->each(fn (Booking $b) => $b->razorpay_order_id = $razorpayOrderId);
    }

    /**
     * Confirm a reserved order after its payment signature has verified (or immediately for a
     * free order, where $paymentId is null). Flips the PENDING rows to CONFIRMED, records the
     * payment id, clears the hold, and counts the coupon use once. Idempotent: rows already
     * CONFIRMED are returned unchanged.
     *
     * @return Collection<int, Booking>
     *
     * @throws NotFoundHttpException  When no matching reserved order exists for this user.
     */
    public function confirmReservedOrder(User $user, string $razorpayOrderId, ?string $paymentId): Collection
    {
        /** @var Collection<int, Booking> $bookings */
        $bookings = Booking::query()
            ->where('razorpay_order_id', $razorpayOrderId)
            ->where('user_id', $user->id)
            ->get();

        if ($bookings->isEmpty()) {
            throw new NotFoundHttpException('Reservation not found');
        }

        return $this->confirmReservation($bookings->pluck('id')->all(), $paymentId);
    }

    /**
     * Confirm a set of reserved rows by id: flip PENDING → CONFIRMED, stamp the payment id (null
     * for a free order), clear the hold, and count the order's coupon use once. Idempotent.
     *
     * @param  list<int>  $bookingIds
     * @return Collection<int, Booking>
     */
    public function confirmReservation(array $bookingIds, ?string $paymentId): Collection
    {
        return DB::transaction(function () use ($bookingIds, $paymentId): Collection {
            /** @var Collection<int, Booking> $bookings */
            $bookings = Booking::query()->whereIn('id', $bookingIds)->lockForUpdate()->get();

            if ($bookings->isEmpty()) {
                throw new NotFoundHttpException('Reservation not found');
            }

            $alreadyConfirmed = strtoupper((string) $bookings->first()->status) === 'CONFIRMED';

            foreach ($bookings as $booking) {
                $was = strtoupper((string) $booking->status);

                if ($was === 'CONFIRMED') {
                    continue;
                }

                // A row that is no longer PENDING had its hold released and its seats
                // handed back to the pool (EXPIRED / CANCELLED). Confirming it without
                // taking that inventory again would sell the same seat twice over: the
                // sell-through and "Bookings x / y" figures read the event's slot counts,
                // so a paid ticket would show revenue and no seat sold.
                if ($was !== 'PENDING') {
                    if ($booking->booking_type === 'venue') {
                        $this->reclaimVenueCourt($booking, $paymentId);
                    } else {
                        $this->reclaimInventory($booking);
                    }
                }

                $booking->status = 'CONFIRMED';
                $booking->reserved_until = null;
                if ($paymentId !== null) {
                    $booking->razorpay_payment_id = $paymentId;
                }
                $booking->save();

                // The money, not just the state. Both routes into this method carry a
                // verified gateway payment — the buyer's confirm call and the webhook
                // backstop — and until this line neither of them told the ledger, so a
                // court paid for in the app read 'unpaid' at the partner's desk. The
                // ledger row is idempotent on the payment id, so whichever of the two
                // arrives second is a no-op rather than double takings.
                $this->ledger->settleOnline($booking, $booking->amountCharged(), $paymentId);
            }

            // Count the coupon use now that the order is paid — once, and not on a re-confirm.
            if (! $alreadyConfirmed) {
                $coupon = Coupon::findByCode($bookings->first()->coupon_code);
                if ($coupon !== null) {
                    $coupon->recordUse();
                }
            }

            return $bookings;
        });
    }

    /**
     * Release a set of just-reserved rows by id (used when order creation fails before an id is
     * attached), restoring their inventory.
     *
     * @param  list<int>  $bookingIds
     */
    public function releaseReservation(array $bookingIds): void
    {
        if ($bookingIds !== []) {
            $this->releaseBookings($bookingIds);
        }
    }

    /**
     * Cancel a reserved order the buyer walked away from (modal dismissed / payment failed),
     * restoring the seats it was holding. Safe to call with an order id that no longer has any
     * PENDING rows — it simply does nothing.
     */
    public function releaseReservedOrder(User $user, string $razorpayOrderId): void
    {
        $ids = Booking::query()
            ->where('razorpay_order_id', $razorpayOrderId)
            ->where('user_id', $user->id)
            ->whereRaw('upper(status) = ?', ['PENDING'])
            ->pluck('id')
            ->all();

        if ($ids !== []) {
            $this->releaseBookings($ids);
        }
    }

    /**
     * Sweep expired PENDING holds for an event and hand their seats back. Called lazily at the
     * start of every new reservation for the event, so a dead cron isn't required for correctness.
     */
    public function releaseExpired(int $eventId): void
    {
        $this->settleLapsedHolds(
            Booking::query()
                ->where('event_id', $eventId)
                ->whereRaw('upper(status) = ?', ['PENDING'])
                ->whereNotNull('reserved_until')
                ->where('reserved_until', '<', now())
                ->pluck('id')
                ->all(),
        );
    }

    /**
     * Sweep every event's expired ticket locks in one pass and hand their seats back.
     *
     * The lazy {@see releaseExpired()} only clears an event's holds when someone next
     * tries to book *that* event — so a ticket one person locked and abandoned would
     * stay locked (and its seat missing from `available_slots`) until the next buyer
     * happened along. The scheduled `bookings:release-expired` command calls this every
     * minute so a lapsed lock frees up for the next buyer on its own. Returns the number
     * of holds released.
     */
    public function releaseAllExpired(): int
    {
        return $this->settleLapsedHolds(
            Booking::query()
                ->whereRaw('upper(status) = ?', ['PENDING'])
                ->whereNotNull('reserved_until')
                ->where('reserved_until', '<', now())
                // WhatsApp holds (desk or booking bot) are settled by `whatsapp:expire-holds`:
                // their money arrives by payment link, not by a Razorpay order this sweep
                // could ask about, so only that sweep can tell a lapsed hold from a paid one.
                ->where(fn ($q) => $q->whereNull('channel')->orWhere('channel', '!=', 'whatsapp'))
                ->whereNotIn('id', DB::table('whatsapp_payment_links')->select('booking_id'))
                ->pluck('id')
                ->all(),
        );
    }

    /**
     * Decide what a lapsed hold actually is, then act: an abandoned checkout is released,
     * a hold whose payment went through anyway is CONFIRMED.
     *
     * A lapsed hold is not proof nobody paid. The buyer's browser can die between Razorpay
     * capturing the money and our confirm callback firing, and the hold then looks exactly
     * like an abandoned one. Expiring it loses a paid ticket: the customer is charged, gets
     * nothing, and the order never appears in the host's analytics because every report
     * counts only confirmed/paid rows. So any hold that got as far as a gateway order is
     * checked against Razorpay before it is written off.
     *
     * Fails SAFE in both directions. If Razorpay can't be reached we keep the hold and try
     * again on the next sweep (a minute later) rather than guess "unpaid" — stranding a seat
     * briefly is recoverable, expiring a paid ticket is not.
     *
     * @param  list<int>  $bookingIds
     * @return int  how many holds were actually released
     */
    private function settleLapsedHolds(array $bookingIds): int
    {
        if ($bookingIds === []) {
            return 0;
        }

        /** @var array<string, list<int>> $byOrder */
        $byOrder = [];
        $neverCharged = [];

        foreach (Booking::query()->whereIn('id', $bookingIds)->get(['id', 'razorpay_order_id']) as $row) {
            $orderId = trim((string) $row->razorpay_order_id);

            // No gateway order — checkout never reached payment, so nothing can have been
            // charged and the seats go straight back.
            if ($orderId === '') {
                $neverCharged[] = (int) $row->id;

                continue;
            }

            $byOrder[$orderId][] = (int) $row->id;
        }

        $released = 0;

        if ($neverCharged !== []) {
            $this->releaseBookings($neverCharged);
            $released += count($neverCharged);
        }

        foreach ($byOrder as $orderId => $ids) {
            try {
                $paymentId = $this->razorpay->capturedPaymentFor($orderId);
            } catch (RuntimeException $e) {
                // "Don't know" — leave the hold standing and re-ask next sweep.
                Log::warning("Could not check Razorpay order {$orderId} before expiring its hold: " . $e->getMessage());

                continue;
            }

            if ($paymentId !== null) {
                $this->confirmReservation($ids, $paymentId);

                Log::warning("Razorpay order {$orderId} was paid but never confirmed by the client; confirmed booking(s) " . implode(', ', $ids) . ' from the sweep.');

                BookingNotifier::dispatch(Booking::query()->find($ids[0]));

                continue;
            }

            $this->releaseBookings($ids);
            $released += count($ids);
        }

        return $released;
    }

    /**
     * Put the gateway money for already-confirmed online bookings onto the ledger.
     *
     * Checkout used to confirm a booking without telling {@see BookingLedger} anything, so
     * every order paid before that was fixed still reads `amount_paid = 0` /
     * `payment_status = 'unpaid'` — indistinguishable from a walk-in who hasn't paid yet.
     * Live rows, not history: those bookings are on partner chase lists right now, and
     * their venues' "collected today" is short by exactly this money.
     *
     * Only touches rows that carry a Razorpay payment id (so the money is not in
     * question) and have no ledger row at all (so a desk payment recorded by hand is
     * never doubled). Reports by default; changes nothing until $apply is true.
     *
     * @return list<array{booking: int, type: string, amount: float, payment: string}>
     */
    public function backfillOnlinePayments(bool $apply = false): array
    {
        $rows = Booking::query()
            ->whereNotNull('razorpay_payment_id')
            ->where('razorpay_payment_id', '!=', '')
            ->whereRaw('upper(status) = ?', ['CONFIRMED'])
            ->whereDoesntHave('payments')
            ->orderBy('id')
            ->get();

        $found = [];

        foreach ($rows as $booking) {
            $amount = $booking->amountCharged();

            if ($amount <= 0.0) {
                continue;
            }

            $found[] = [
                'booking' => (int) $booking->id,
                'type'    => (string) ($booking->booking_type ?: 'event'),
                'amount'  => $amount,
                'payment' => (string) $booking->razorpay_payment_id,
            ];

            if ($apply) {
                $this->ledger->settleOnline(
                    $booking,
                    $amount,
                    (string) $booking->razorpay_payment_id,
                    'Razorpay checkout (backfilled)',
                );
            }
        }

        return $found;
    }

    /**
     * Find orders Razorpay says were paid but that never became tickets, and (optionally) put
     * them right.
     *
     * The sweep only ever looks at PENDING holds, so it cannot reach an order that a previous
     * build already wrote off as EXPIRED — those rows are settled as far as the system is
     * concerned, and settled wrongly: the buyer was charged, has no ticket, and the host's
     * reports have never counted the sale. This is the one-off repair for that backlog, and a
     * reconciliation any host can re-run afterwards.
     *
     * Reports by default and changes nothing until $apply is true, because the failure mode of
     * getting this wrong is issuing tickets nobody paid for.
     *
     * @return list<array{order: string, bookings: list<int>, payment: ?string, action: string}>
     */
    public function reconcileUnconfirmedPayments(int $sinceDays = 30, bool $apply = false): array
    {
        $rows = Booking::query()
            ->whereNotNull('razorpay_order_id')
            ->whereNotIn(DB::raw('upper(status)'), ['CONFIRMED', 'CANCELLED', 'REFUNDED'])
            ->where('created_at', '>=', now()->subDays(max(1, $sinceDays)))
            ->get(['id', 'razorpay_order_id']);

        /** @var array<string, list<int>> $byOrder */
        $byOrder = [];

        foreach ($rows as $row) {
            $byOrder[trim((string) $row->razorpay_order_id)][] = (int) $row->id;
        }

        $found = [];

        foreach ($byOrder as $orderId => $ids) {
            try {
                $paymentId = $this->razorpay->capturedPaymentFor($orderId);
            } catch (RuntimeException $e) {
                $found[] = ['order' => $orderId, 'bookings' => $ids, 'payment' => null, 'action' => 'unreachable'];

                continue;
            }

            if ($paymentId === null) {
                continue;
            }

            if (! $apply) {
                $found[] = ['order' => $orderId, 'bookings' => $ids, 'payment' => $paymentId, 'action' => 'would confirm'];

                continue;
            }

            $this->confirmReservation($ids, $paymentId);
            BookingNotifier::dispatch(Booking::query()->find($ids[0]));

            Log::warning("Reconciled paid-but-unconfirmed Razorpay order {$orderId} → booking(s) " . implode(', ', $ids) . '.');

            $found[] = ['order' => $orderId, 'bookings' => $ids, 'payment' => $paymentId, 'action' => 'confirmed'];
        }

        return $found;
    }

    /**
     * Restore inventory for a set of PENDING bookings and mark them EXPIRED, atomically.
     * Adds each line's quantity back to its event's available_slots and its tier's `sold`.
     *
     * @param  list<int>  $bookingIds
     */
    private function releaseBookings(array $bookingIds): void
    {
        DB::transaction(function () use ($bookingIds): void {
            $bookings = Booking::query()->whereIn('id', $bookingIds)->lockForUpdate()->get();

            foreach ($bookings as $booking) {
                if (strtoupper((string) $booking->status) !== 'PENDING') {
                    continue;
                }

                // Never expire a row that carries a payment id. Nothing should route a paid
                // booking here — but this is the one path that can turn a customer's money
                // into a cancelled ticket, so it refuses rather than trusts its callers.
                if (trim((string) $booking->razorpay_payment_id) !== '') {
                    Log::error("Refused to expire booking {$booking->id}: it has payment {$booking->razorpay_payment_id} against it.");

                    continue;
                }

                $event = $booking->event_id !== null
                    ? Event::query()->lockForUpdate()->find($booking->event_id)
                    : null;

                if ($event !== null) {
                    $event->available_slots += (int) $booking->quantity;
                    $event->save();
                }

                if ($booking->ticket_type_id !== null) {
                    $tier = TicketType::query()->lockForUpdate()->find($booking->ticket_type_id);
                    if ($tier !== null) {
                        $tier->sold = max(0, (int) $tier->sold - (int) $booking->quantity);
                        $tier->save();
                    }
                }

                if ($booking->event_slot_id !== null) {
                    $slot = EventSlot::query()->lockForUpdate()->find($booking->event_slot_id);
                    if ($slot !== null) {
                        $slot->sold = max(0, (int) $slot->sold - (int) $booking->quantity);
                        $slot->save();
                    }
                }

                $booking->status = 'EXPIRED';
                $booking->reserved_until = null;
                $booking->save();
            }
        });
    }

    /**
     * Take back the inventory a released booking had handed to the pool, for a row that is
     * being confirmed after its hold already lapsed. The exact inverse of what
     * {@see releaseBookings()} gave back: the event's available slots, the tier's sold count,
     * and the session's sold count.
     *
     * Runs inside the caller's transaction ({@see confirmReservation()}) and locks the same
     * rows, so a concurrent buyer can't slip between the check and the write.
     *
     * The seats may be gone — someone else can have bought them while the hold was lapsed.
     * The booking is confirmed anyway: the money is taken, and a customer holding a paid
     * ticket must have a ticket. Available slots floor at zero and the oversell is logged
     * loudly, because that is a real-world problem for the host to resolve at the door, not
     * something to hide by quietly dropping the order.
     */
    private function reclaimInventory(Booking $booking): void
    {
        $qty = (int) $booking->quantity;

        if ($qty < 1) {
            return;
        }

        if ($booking->event_id !== null) {
            $event = Event::query()->lockForUpdate()->find($booking->event_id);

            if ($event !== null) {
                $left = (int) $event->available_slots;

                if ($left < $qty) {
                    Log::error("Booking {$booking->id} confirmed after its hold lapsed, but event {$event->id} only had {$left} of {$qty} seat(s) left — the event is oversold.");
                }

                $event->available_slots = max(0, $left - $qty);
                $event->save();
            }
        }

        if ($booking->ticket_type_id !== null) {
            $tier = TicketType::query()->lockForUpdate()->find($booking->ticket_type_id);

            if ($tier !== null) {
                $tier->sold = (int) $tier->sold + $qty;
                $tier->save();
            }
        }

        if ($booking->event_slot_id !== null) {
            $slot = EventSlot::query()->lockForUpdate()->find($booking->event_slot_id);

            if ($slot !== null) {
                $slot->sold = (int) $slot->sold + $qty;
                $slot->save();
            }
        }
    }

    /**
     * Check if the court is still free when an expired hold is reclaimed by a late payment.
     * If the court was sold in the meantime, mark the booking failed and issue an automatic refund.
     */
    private function reclaimVenueCourt(Booking $booking, ?string $paymentId): void
    {
        $startMin = self::timeToMinutes($booking->start_time);
        $endMin = self::endMinutes($booking->end_time);

        try {
            $this->assertCourtHourFree(
                (int) $booking->venue_id,
                $booking->venue_court_id !== null ? (int) $booking->venue_court_id : null,
                $booking->venue_slot_id !== null ? (int) $booking->venue_slot_id : null,
                (string) $booking->slot_date,
                $startMin,
                $endMin,
                (int) $booking->id
            );
        } catch (ConflictHttpException $e) {
            $booking->status = 'FAILED_OVERBOOKED';
            $booking->reserved_until = null;
            $booking->save();

            Log::critical("Booking {$booking->id} payment cleared after hold expired, but court is now occupied: " . $e->getMessage());

            if ($paymentId !== null) {
                $charged = $booking->amountCharged();
                $this->ledger->refund($booking, $charged, 'online', null, $paymentId, 'Automatic refund: court overbooked after hold expired');
                try {
                    $this->razorpay->refund($paymentId, (int) round($charged * 100), [
                        'booking_id' => (string) $booking->id,
                        'reason' => 'Court overbooked after hold expired',
                    ]);
                } catch (\Throwable $re) {
                    Log::error("Automated Razorpay refund failed for overbooked booking {$booking->id}: " . $re->getMessage());
                }
            }

            throw new ConflictHttpException('The court reservation hold expired and was booked by another player. An automatic refund has been issued.');
        }
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
        bool $reserve = false,
        ?string $couponCode = null,
    ): Booking {
        // Emergency switch (/control → Operations). Walk-ins at the desk are not affected.
        Operations::assertBookingsOpen(Operations::VENUES);

        return $this->reserveVenue($venueId, $slotId, $courtId, $date, $duration, [
            'user_id'     => $user->id,
            'channel'     => 'online',
            'guest_name'  => null,
            'guest_phone' => null,
            // Carried for the coupon's per-customer cap; only the online path can discount.
            'user'        => $user,
            'coupon_code' => $couponCode,
        ], $reserve);
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
        ?int $holdMinutes = null,
    ): Booking {
        // A walk-in paying online (UPI QR / link) is a HOLD until the money lands: PENDING
        // with `reserved_until`, so it blocks the court while the customer pays and frees
        // it by itself if they don't. Paid at the counter, it is confirmed straight away.
        return $this->reserveVenue($venueId, $slotId, $courtId, $date, $duration, array_filter([
            'user_id'      => $partner->id,
            'channel'      => 'offline',
            'guest_name'   => $guestName,
            'guest_phone'  => $guestPhone,
            // Desk bookings are priced by the partner at the counter, not by app coupons.
            'user'         => null,
            'coupon_code'  => null,
            'hold_minutes' => $holdMinutes,
        ], fn ($v, $k) => $v !== null || in_array($k, ['user', 'coupon_code', 'guest_name', 'guest_phone'], true), ARRAY_FILTER_USE_BOTH), reserve: $holdMinutes !== null);
    }

    /**
     * Hold a court for a customer the partner is talking to on WhatsApp.
     *
     * Same engine as every other venue booking — a PENDING row with `reserved_until`,
     * so {@see occupyingStatuses()} counts it while it's live and forgets it the moment it
     * lapses, and the app, the web checkout and the desk grid all see the same court as
     * taken. Like a walk-in, `user_id` is the partner (the customer has no account) and the
     * price is the court's own rate for that time; no platform commission or tax, because
     * the partner found this customer on their own chat.
     *
     * @throws NotFoundHttpException  When the venue or court does not exist.
     * @throws ConflictHttpException  When the venue is closed or the window is taken.
     */
    public function createDeskHold(
        User $partner,
        int $venueId,
        int $courtId,
        string $date,
        int $startMin,
        int $durationMin,
        ?string $guestName,
        ?string $guestPhone,
        int $holdMinutes,
    ): Booking {
        return $this->reserveVenue($venueId, null, $courtId, $date, 1, [
            'user_id'      => $partner->id,
            'channel'      => 'whatsapp',
            'guest_name'   => $guestName,
            'guest_phone'  => $guestPhone,
            'user'         => null,
            'coupon_code'  => null,
            'start_min'    => $startMin,
            'duration_min' => $durationMin,
            'hold_minutes' => $holdMinutes,
        ], reserve: true);
    }

    /**
     * Hold a court for a customer booking through the WhatsApp bot. Everything the app
     * checkout applies applies here — published venue, booking window, Operations
     * switches, Pulse commission and tax — because it IS an online booking; only the
     * start time is free rather than a slot template, and the hold runs as long as the
     * bot's own setting says.
     *
     * @throws NotFoundHttpException  When the venue or court does not exist.
     * @throws ConflictHttpException  When the venue isn't bookable or the window is taken.
     */
    public function createOnlineHoldAt(User $user, int $venueId, int $courtId, string $date, int $startMin, int $durationMin, int $holdMinutes): Booking
    {
        Operations::assertBookingsOpen(Operations::VENUES);

        return $this->reserveVenue($venueId, null, $courtId, $date, 1, [
            'user_id'      => $user->id,
            'channel'      => 'online',
            'guest_name'   => null,
            'guest_phone'  => null,
            'user'         => $user,
            'coupon_code'  => null,
            'start_min'    => $startMin,
            'duration_min' => $durationMin,
            'hold_minutes' => $holdMinutes,
        ], reserve: true);
    }

    /**
     * Is this court free for this window — no live booking or hold on it or on a court
     * sharing its ground, and no block covering it? The same rule the booking engine
     * enforces, for screens that need to show availability before anyone books.
     */
    public function isCourtHourFree(int $venueId, int $courtId, string $date, int $startMin, int $endMin, ?int $excludeBookingId = null): bool
    {
        try {
            $this->assertCourtHourFree($venueId, $courtId, null, $date, $startMin, $endMin, $excludeBookingId);

            return true;
        } catch (ConflictHttpException) {
            return false;
        }
    }

    /**
     * Turn a desk hold into a booking once the desk has the money in hand (cash / UPI at
     * the counter). The ledger row is the caller's to write — this only settles the state.
     *
     * A hold that already lapsed is re-checked first: if someone else took the court in
     * the meantime this refuses, and the desk has not taken any money yet to hand back.
     *
     * @throws ConflictHttpException  When the lapsed hold's court has since been taken,
     *                                or the booking is not a hold at all.
     */
    public function confirmDeskHold(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking): Booking {
            /** @var Booking $row */
            $row = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $status = strtoupper((string) $row->status);

            if ($status === 'CONFIRMED') {
                return $row;
            }

            if (! in_array($status, ['PENDING', 'EXPIRED'], true)) {
                throw new ConflictHttpException('This booking is '.strtolower($status).' and can’t be confirmed.');
            }

            $live = $status === 'PENDING' && $row->reserved_until !== null && $row->reserved_until->isFuture();

            if (! $live) {
                $this->assertCourtHourFree(
                    (int) $row->venue_id,
                    $row->venue_court_id !== null ? (int) $row->venue_court_id : null,
                    $row->venue_slot_id !== null ? (int) $row->venue_slot_id : null,
                    (string) Carbon::parse($row->slot_date)->toDateString(),
                    self::timeToMinutes($row->start_time),
                    self::endMinutes($row->end_time),
                    (int) $row->id,
                );
            }

            $row->status = 'CONFIRMED';
            $row->reserved_until = null;
            $row->save();

            return $row;
        });
    }

    /**
     * Shared reservation routine behind the online and offline venue-booking paths.
     * Validates the venue/slot/court, rejects blocked dates, refuses a court whose sports
     * the slot doesn't run for, enforces the court+window overlap rule, and writes the
     * confirmed booking — all inside one locked transaction.
     *
     * Both public entry points funnel through here, so every rule below applies to the
     * customer app and the partner desk alike.
     *
     * @param array{user_id:int,channel:string,guest_name:?string,guest_phone:?string,user?:?User,coupon_code?:?string} $meta
     */
    private function reserveVenue(int $venueId, ?int $slotId, ?int $courtId, string $date, int $duration, array $meta, bool $reserve = false): Booking
    {
        $duration = max(1, $duration);
        $date = date('Y-m-d', strtotime($date) ?: time());

        /** @var Booking $booking */
        $booking = DB::transaction(function () use ($venueId, $slotId, $courtId, $date, $duration, $meta, $reserve): Booking {
            $venue = Venue::query()->lockForUpdate()->find($venueId);

            if ($venue === null) {
                throw new NotFoundHttpException('Venue not found');
            }

            // Published + "open for booking" govern the PUBLIC checkout only. The partner
            // desk is the owner's own counter: a venue still in draft on Haraan (no photos
            // yet, or unpublished) keeps taking walk-ins, exactly as the event desk ignores
            // the Operations pause. Courts, blocks and overlaps below still apply to both.
            if ($meta['channel'] === 'online') {
                if (! $venue->isPublished()) {
                    throw new NotFoundHttpException('Venue not found or not currently available');
                }

                if (! $venue->is_bookable) {
                    throw new ConflictHttpException('This venue is not open for booking');
                }
            }

            // Owner-blocked day (holiday / maintenance) — no bookings taken.
            $blocked = VenueBlockedDate::query()
                ->where('venue_id', $venue->id)
                ->whereDate('date', $date)
                ->exists();

            if ($blocked) {
                throw new ConflictHttpException('This venue is closed on that date');
            }

            // Customers book within the venue's window (plus any priority days their plan
            // adds). The partner desk is never held to it — it's the owner's own calendar.
            if ($meta['channel'] === 'online') {
                $window = app(VenueBookingWindow::class);
                if (! $window->allows($venue, $meta['user'] ?? null, Carbon::parse($date))) {
                    throw new ConflictHttpException($window->refusal($venue, $meta['user'] ?? null));
                }
            }

            // Structured operating hours: refuse bookings on a day the venue isn't open.
            if (! $venue->isOpenOn(Carbon::parse($date))) {
                throw new ConflictHttpException('This venue is closed on that day');
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

            // A desk booking a free time rather than a slot template (the WhatsApp Desk:
            // "Court 2, 6:30 to 8") names the start in minutes. Without it a court-only
            // booking has no window, and a windowless booking blocks the court all day.
            if ($slotId === null && isset($meta['start_min'])) {
                $startMin = (int) $meta['start_min'];
                $timeLabel = $this->minutesToHm($startMin);
            }

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

            // A slot may run for only some of the venue's sports, and a court hosts only
            // some too. Without this a three-sport venue sold its football turf at a
            // 06:00 AM row that exists for the badminton courts, at the full turf rate.
            //
            // Refuse only when both sides name sports and share none: either side left
            // empty still means "no restriction", so a venue that never touches sports is
            // unaffected, and a booking with no court at all (every row from before courts
            // existed) is never rejected here.
            if ($slot !== null && $court !== null && ! $slot->allowsCourt($court)) {
                throw new ConflictHttpException(sprintf(
                    'That time is for %s only — %s cannot be booked then',
                    implode(', ', $slot->sportsList()),
                    $court->name,
                ));
            }

            // Per-court peak pricing wins when it applies (weekday/time window on the court).
            if ($court !== null) {
                $perHour = $court->rateFor(Carbon::parse($date), $timeLabel, (int) ($venue->price ?? 0));
            }

            // Length in minutes: whole hours for slot bookings, or exactly what the desk
            // asked for (90 minutes is a normal turf booking).
            // A slot booking lasts `duration` of the venue's slots (30 or 60 min each).
            $lengthMin = isset($meta['duration_min']) ? max(30, (int) $meta['duration_min']) : $duration * $venue->slotLength();
            $endMin = $startMin !== null ? $startMin + $lengthMin : null;

            // Every booking lives inside one calendar date (slot_date + HH:MM). A venue open
            // past midnight lists its after-midnight hours on the next day's date instead.
            if ($endMin !== null && $endMin > 24 * 60) {
                throw new ConflictHttpException('A booking can’t run past midnight. Book the hours after 12 AM on the next day’s date.');
            }
            $startHm = $startMin !== null ? $this->minutesToHm($startMin) : null;
            $endHm = $endMin !== null ? $this->minutesToHm($endMin) : null;

            // Reject online reservations for slot times that have already passed today
            if ($meta['channel'] === 'online' && $date === today()->toDateString() && $startMin !== null) {
                $nowMin = (int) now()->format('H') * 60 + (int) now()->format('i');
                if ($startMin <= $nowMin) {
                    throw new ConflictHttpException('That slot time has already passed for today');
                }
            }

            $this->assertCourtHourFree($venue->id, $courtId, $slotId, $date, $startMin, $endMin);

            // Subtotal → fee → discount, in that order, and `total_amount` is what the
            // customer actually pays. The Razorpay order is built from this number, so the
            // arithmetic lives here and nowhere else — a second copy in the controller is
            // how a summary and a charge drift apart.
            $subtotal = round($perHour * $lengthMin / 60, 2);
            $fee      = $venue->convenienceFeeFor($subtotal);

            $applied  = $meta['coupon_code'] !== null && $meta['user'] instanceof User
                ? $this->resolveVenueCoupon($meta['user'], $venue->id, $meta['coupon_code'], $subtotal, $fee)
                : ['coupon' => null, 'discount' => 0.0, 'message' => ''];
            $discount = (float) $applied['discount'];

            // A held (unpaid) booking must not burn a coupon use — that is counted on
            // confirmation, so an abandoned checkout doesn't consume the code.
            $payable = max(0.0, round($subtotal + $fee - $discount, 2));

            // Pulse tax (/control → Platform rules → Fees): online only, kept in `tax_amount`
            // beside the venue's share so it never reaches the payout. amountCharged() adds it.
            $tax = $meta['channel'] === 'online' ? Venue::taxFor($subtotal, $discount) : 0.0;

            // Payments paused (/control → Operations): a paid online slot is refused before
            // the court is held; free slots and desk bookings go through (a desk hold can
            // still be settled in cash, and its payment link checks the switch itself).
            if ($reserve && $meta['channel'] === 'online' && $payable + $tax > 0) {
                Operations::assertPaymentsOn();
            }

            if ($applied['coupon'] !== null && ! $reserve) {
                $applied['coupon']->recordUse();
            }

            // Pulse commission (/control → Platform rules → Fees): Haraan's share of an ONLINE
            // booking, on the slot value after discount, deducted from the venue's payout. Never
            // charged on a desk walk-in — that money never passed through the platform.
            $commission = $meta['channel'] === 'online'
                ? round(max(0.0, $subtotal - $discount) * PlatformRules::float('fees.venue_commission_percent') / 100, 2)
                : 0.0;

            return Booking::query()->create([
                'quantity'        => 1,
                'host_deduction' => $commission,
                'convenience_fee' => $fee,
                'discount'        => round($discount, 2),
                'coupon_code'     => $applied['coupon']?->code,
                'total_amount' => $payable,
                'tax_amount'   => $tax,
                // Paid online bookings are held PENDING until Razorpay confirms. The hold is
                // load-bearing: occupyingStatuses() only counts a PENDING row while
                // `reserved_until` is in the future, so an abandoned checkout frees the court
                // by itself instead of blocking it forever. Desk/offline bookings and free
                // slots skip the hold and confirm outright.
                'status'         => $reserve ? 'PENDING' : 'CONFIRMED',
                'reserved_until' => $reserve ? now()->addMinutes((int) ($meta['hold_minutes'] ?? self::holdMinutes())) : null,
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
    private function assertCourtHourFree(int $venueId, ?int $courtId, ?int $slotId, string $date, ?int $startMin, ?int $endMin, ?int $excludeBookingId = null): void
    {
        $this->assertNoBookingOverlap($venueId, $courtId, $slotId, $date, $startMin, $endMin, $excludeBookingId);
        $this->assertNoBlockOverlap($venueId, $courtId, $date, $startMin, $endMin);
    }

    /**
     * Reject the reservation if a non-booking block already owns this court-hour —
     * maintenance, a holiday, an academy batch, a tournament hold, a private hire.
     *
     * Three rules, kept in PHP because they read better than the SQL would:
     * a block with no court covers every court; a block with no time window covers
     * the whole day; a block with a weekday applies only on that weekday inside its
     * date range (the range + weekday narrowing happens in scopeApplyingOn).
     *
     * @throws ConflictHttpException  When a block covers the requested window.
     */
    private function assertNoBlockOverlap(int $venueId, ?int $courtId, string $date, ?int $startMin, ?int $endMin): void
    {
        $day = Carbon::parse($date);

        $blocks = VenueBlock::query()->applyingOn($venueId, $day)->get();

        foreach ($blocks as $block) {
            if (! $block->coversCourt($courtId)) {
                continue;
            }

            // Whole-day block, or a booking with no window we can reason about:
            // refuse rather than risk a clash we can't see.
            if ($block->isAllDay() || $startMin === null || $endMin === null) {
                throw new ConflictHttpException(
                    sprintf('That court is unavailable on this date (%s)', $block->label()),
                );
            }

            $bs = $this->timeToMinutes($block->start_time);
            $be = self::endMinutes($block->end_time);

            if ($bs === null || $be === null) {
                throw new ConflictHttpException(
                    sprintf('That court is unavailable on this date (%s)', $block->label()),
                );
            }

            if ($startMin < $be && $endMin > $bs) {
                throw new ConflictHttpException(
                    sprintf('That court is unavailable at this time (%s)', $block->label()),
                );
            }
        }
    }

    /**
     * Reject the reservation if it clashes with another booking on the same court.
     *
     * @throws ConflictHttpException  When the window (or slot) is already taken.
     */
    private function assertNoBookingOverlap(int $venueId, ?int $courtId, ?int $slotId, string $date, ?int $startMin, ?int $endMin, ?int $excludeBookingId = null): void
    {
        $occupying = fn () => Booking::query()
            ->where('booking_type', 'venue')
            ->where('venue_id', $venueId)
            ->whereDate('slot_date', $date)
            ->where(fn ($q) => $this->occupyingStatuses($q))
            ->when($excludeBookingId !== null, fn ($q) => $q->where('id', '!=', $excludeBookingId));

        if ($courtId !== null) {
            $court = VenueCourt::find($courtId);
            $courtIds = $court ? $court->allRelatedCourtIds() : [$courtId];

            // A booking with NO court is a booking of the venue, not of one court: the app
            // and web checkouts at a venue with a single court store it that way. SQL `IN`
            // never matches NULL, so these rows used to be invisible here — and the desk
            // sold 7 AM on a court the app had already sold at 7 AM.
            $existing = $occupying()
                ->where(fn ($q) => $q->whereIn('venue_court_id', $courtIds)->orWhereNull('venue_court_id'))
                ->get(['start_time', 'end_time', 'venue_court_id', 'venue_slot_id']);

            foreach ($existing as $b) {
                [$es, $ee] = $this->bookedWindow($b);

                // A booking with no window (or ours has none) coarsely blocks the whole day —
                // safer than silently allowing a possible clash we can't reason about.
                if ($startMin === null || $endMin === null || $es === null || $ee === null) {
                    throw new ConflictHttpException('That court is already booked for this date');
                }

                if ($startMin < $ee && $endMin > $es) {
                    throw new ConflictHttpException(match (true) {
                        $b->venue_court_id === null => 'That time is already booked at this venue',
                        (int) $b->venue_court_id === (int) $courtId => 'That court is already booked for this time',
                        default => 'Court conflict: Connected composite or sub-court is already booked for this time',
                    });
                }
            }

            return;
        }

        // No court named: the venue is sold by the hour as a whole. The same slot, or any
        // booking whose hours overlap — online checkouts store a start time and no slot
        // id, so matching on the slot id alone let the desk sell the same hour twice.
        if ($slotId === null && ($startMin === null || $endMin === null)) {
            return;
        }

        foreach ($occupying()->get(['start_time', 'end_time', 'venue_court_id', 'venue_slot_id']) as $b) {
            if ($slotId !== null && (int) $b->venue_slot_id === $slotId) {
                throw new ConflictHttpException('That slot is already booked for this date');
            }

            [$es, $ee] = $this->bookedWindow($b);
            if ($startMin !== null && $endMin !== null && $es !== null && $ee !== null && $startMin < $ee && $endMin > $es) {
                throw new ConflictHttpException('That time is already booked at this venue');
            }
        }
    }

    /**
     * The minutes a booking occupies: its own start/end, or — for rows saved against a
     * slot template with no times of their own — that slot's hour.
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function bookedWindow(Booking $b): array
    {
        $start = self::timeToMinutes($b->start_time);
        $end = self::endMinutes($b->end_time);

        if ($start === null && $b->venue_slot_id !== null) {
            $slotTime = VenueSlot::query()->whereKey($b->venue_slot_id)->value('time');
            $start = self::timeToMinutes($slotTime);
            $end = $start !== null ? $start + 60 : null;
        }

        if ($start !== null && $end === null) {
            $end = $start + 60;
        }

        return [$start, $end];
    }

    /**
     * Bookings that physically hold their court-hour.
     *
     * Occupancy is a question about LIFECYCLE, never about money — an unpaid
     * booking and one with a ₹500 advance both still own the slot. `payment_status`
     * must never appear in this predicate; that is the mistake that would let a
     * venue sell the same 7pm Saturday twice. Live holds (a PENDING row inside its
     * `reserved_until` window) count too; expired ones release the slot.
     *
     * Filament-created bookings store lowercase 'confirmed', so match case-insensitively.
     */
    public static function occupyingStatuses(Builder $query): Builder
    {
        return $query
            ->whereIn(DB::raw('lower(status)'), ['confirmed', 'paid', 'completed', 'checked_in'])
            ->orWhere(fn (Builder $hold) => $hold
                ->whereRaw('lower(status) = ?', ['pending'])
                ->whereNotNull('reserved_until')
                ->where('reserved_until', '>', now()));
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
    /**
     * Minutes-of-day for an END time, where midnight means the end of the day (1440), not its
     * start. A court booked 11 PM–12 AM used to read back as ending at minute 0, so the
     * overlap checks (start < end) never saw it and the same hour could be sold twice. An
     * end can never be the start of its own day, so "00:00" / "24:00" / "12:00 AM" → 1440.
     */
    public static function endMinutes(?string $label): ?int
    {
        if ($label !== null && str_starts_with(trim($label), '24:')) {
            return 24 * 60;
        }

        $m = self::timeToMinutes($label);

        return $m === 0 ? 24 * 60 : $m;
    }

    public static function timeToMinutes(?string $label): ?int
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
    public function cancel(User $user, string $bookingId, string $reason = ''): Booking
    {
        $booking = Booking::query()->with('venue')->find($bookingId);

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

        // If already checked in, refuse cancellation
        if ($booking->checked_in_at !== null && $user->role !== 'ADMIN') {
            throw new ConflictHttpException('Cannot cancel an already checked-in booking');
        }

        // If past start time, refuse cancellation for regular user
        if ($user->role !== 'ADMIN' && $booking->booking_type === 'venue' && $booking->slot_date !== null) {
            $slotDate = Carbon::parse($booking->slot_date);
            $startMin = self::timeToMinutes($booking->start_time);
            $slotStart = $startMin !== null ? $slotDate->copy()->addMinutes($startMin) : $slotDate->copy()->endOfDay();
            if ($slotStart->isPast()) {
                throw new ConflictHttpException('Cannot cancel a booking after its start time');
            }
        }

        DB::transaction(function () use ($booking, $user): void {
            $event = Event::query()->find($booking->event_id);

            if ($event !== null) {
                $event->available_slots += (int) $booking->quantity;
                $event->save();
            }

            if ($booking->ticket_type_id !== null) {
                $tier = TicketType::query()->lockForUpdate()->find($booking->ticket_type_id);
                if ($tier !== null) {
                    $tier->sold = max(0, (int) $tier->sold - (int) $booking->quantity);
                    $tier->save();
                }
            }

            if ($booking->event_slot_id !== null) {
                $slot = EventSlot::query()->lockForUpdate()->find($booking->event_slot_id);
                if ($slot !== null) {
                    $slot->sold = max(0, (int) $slot->sold - (int) $booking->quantity);
                    $slot->save();
                }
            }

            if ($booking->booking_type === 'venue' && (float) $booking->amount_paid > 0) {
                $this->processVenueCancellationRefund($booking, $user);
            }

            $booking->status = 'CANCELLED';
            $booking->save();
        });

        BookingNotifier::dispatchCancellation($booking, $reason);

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
    public function cancelAsPartner(User $partner, string $bookingId, string $reason = ''): Booking
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

        DB::transaction(function () use ($booking, $partner): void {
            if ($booking->booking_type === 'venue' && (float) $booking->amount_paid > 0) {
                $this->processVenueCancellationRefund($booking, $partner);
            }

            $booking->status = 'CANCELLED';
            $booking->save();
        });

        BookingNotifier::dispatchCancellation($booking, $reason, byPartner: true);

        return $booking;
    }

    /**
     * Process refund on cancellation based on venue's cancel policy window.
     */
    private function processVenueCancellationRefund(Booking $booking, User $actor): void
    {
        $venue = $booking->venue;
        $refundPercent = 100;

        if ($venue !== null && $actor->role !== 'ADMIN' && $booking->slot_date !== null) {
            $slotDate = Carbon::parse($booking->slot_date);
            $startMin = self::timeToMinutes($booking->start_time);
            $slotStart = $startMin !== null ? $slotDate->copy()->addMinutes($startMin) : $slotDate->copy()->startOfDay();

            $hoursUntilSlot = now()->diffInHours($slotStart, false);
            $freeHours = (int) ($venue->cancel_free_hours ?? 24);

            if ($hoursUntilSlot < $freeHours) {
                $refundPercent = max(0, min(100, (int) ($venue->cancel_refund_percent ?? 0)));
            }
        }

        if ($refundPercent > 0 && (float) $booking->amount_paid > 0) {
            $refundAmount = round(((float) $booking->amount_paid * $refundPercent) / 100, 2);
            if ($refundAmount > 0) {
                $this->ledger->refund(
                    $booking,
                    $refundAmount,
                    'online',
                    $actor->role === 'PARTNER' ? $actor : null,
                    $booking->razorpay_payment_id,
                    "Cancellation refund ({$refundPercent}%)",
                );

                if ($booking->razorpay_payment_id !== null) {
                    try {
                        $this->razorpay->refund($booking->razorpay_payment_id, (int) round($refundAmount * 100), [
                            'booking_id' => (string) $booking->id,
                            'reason'     => 'Booking cancellation',
                        ]);
                    } catch (\Throwable $e) {
                        Log::error("Razorpay refund exception on booking {$booking->id}: " . $e->getMessage());
                    }
                }
            }
        }
    }

    /**
     * Reschedule a confirmed venue booking to a new date, slot, or court.
     * Checks conflict on target slot/court, adjusts window, and notifies customer & partner.
     *
     * @throws NotFoundHttpException When booking, target slot or court is missing.
     * @throws ConflictHttpException When target slot is taken, booking has started, or already checked in.
     */
    public function rescheduleVenueBooking(
        User $user,
        string $bookingId,
        string $newDate,
        ?int $newSlotId = null,
        ?int $newCourtId = null,
    ): Booking {
        $booking = Booking::query()->with(['venue', 'venueCourt'])->find($bookingId);

        if ($booking === null) {
            throw new NotFoundHttpException('Booking not found');
        }

        if ($user->role !== 'ADMIN' && (int) $booking->user_id !== (int) $user->id) {
            throw new AccessDeniedHttpException('Forbidden');
        }

        if (strtoupper((string) $booking->status) !== 'CONFIRMED') {
            throw new ConflictHttpException('Only confirmed bookings can be rescheduled');
        }

        if ($booking->checked_in_at !== null) {
            throw new ConflictHttpException('Cannot reschedule an already checked-in booking');
        }

        $newDateStr = date('Y-m-d', strtotime($newDate) ?: time());
        $venue = $booking->venue;
        if ($venue === null) {
            throw new NotFoundHttpException('Venue not found');
        }

        $slotId = $newSlotId ?? $booking->venue_slot_id;
        $courtId = $newCourtId ?? $booking->venue_court_id;

        return DB::transaction(function () use ($booking, $venue, $newDateStr, $slotId, $courtId, $user): Booking {
            if (! $venue->isOpenOn(Carbon::parse($newDateStr))) {
                throw new ConflictHttpException('The venue is closed on that day');
            }

            $blocked = VenueBlockedDate::query()
                ->where('venue_id', $venue->id)
                ->whereDate('date', $newDateStr)
                ->exists();
            if ($blocked) {
                throw new ConflictHttpException('The venue is closed on that date');
            }

            $court = null;
            if ($courtId !== null) {
                $court = VenueCourt::query()->where('venue_id', $venue->id)->find($courtId);
                if ($court === null || ! $court->is_active) {
                    throw new NotFoundHttpException('Court not found or inactive');
                }
            }

            $slot = null;
            $startMin = null;
            $timeLabel = null;
            $dayLabel = null;

            if ($slotId !== null) {
                $slot = VenueSlot::query()->where('venue_id', $venue->id)->find($slotId);
                if ($slot === null) {
                    throw new NotFoundHttpException('Slot not found');
                }
                $startMin = self::timeToMinutes($slot->time);
                $timeLabel = $slot->time;
                $dayLabel = $slot->day;
            }

            $duration = 1;
            if ($booking->start_time !== null && $booking->end_time !== null) {
                $origStart = self::timeToMinutes($booking->start_time);
                $origEnd = self::endMinutes($booking->end_time);
                if ($origStart !== null && $origEnd !== null && $origEnd > $origStart) {
                    $duration = max(1, (int) round(($origEnd - $origStart) / 60));
                }
            }

            $endMin = $startMin !== null ? $startMin + $duration * 60 : null;

            // Every booking lives inside one calendar date (slot_date + HH:MM). A venue open
            // past midnight lists its after-midnight hours on the next day's date instead.
            if ($endMin !== null && $endMin > 24 * 60) {
                throw new ConflictHttpException('A booking can’t run past midnight. Book the hours after 12 AM on the next day’s date.');
            }
            $startHm = $startMin !== null ? self::minutesToHm($startMin) : null;
            $endHm = $endMin !== null ? self::minutesToHm($endMin) : null;

            if ($newDateStr === today()->toDateString() && $startMin !== null) {
                $nowMin = (int) now()->format('H') * 60 + (int) now()->format('i');
                if ($startMin <= $nowMin && $user->role !== 'ADMIN') {
                    throw new ConflictHttpException('That slot time has already passed for today');
                }
            }

            $this->assertCourtHourFree(
                $venue->id,
                $courtId,
                $slotId,
                $newDateStr,
                $startMin,
                $endMin,
                (int) $booking->id
            );

            $booking->slot_date = $newDateStr;
            $booking->venue_slot_id = $slotId;
            $booking->venue_court_id = $courtId;
            $booking->start_time = $startHm;
            $booking->end_time = $endHm;
            $booking->slot_label = $this->bookingLabel($court?->name, $dayLabel, $timeLabel, $endHm);
            $booking->save();

            BookingNotifier::dispatch($booking);

            return $booking;
        });
    }

    /**
     * Resolve a booking from its scannable ticket code — the payload the attendee's ticket QR
     * encodes as `haraan:ticket:<code>`. Used by the Filament host check-in scanner.
     *
     * Access is gated to staff who manage the events or venues workspace, and then
     * tenant-scoped to the acting partner: the ticket must be for one of their own
     * events/venues (and, for a scoped desk person, one assigned to them).
     *
     * @throws AccessDeniedHttpException  When the actor may not manage this ticket.
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

        // $actor is non-null here (assertCanManageAttendance throws otherwise).
        $this->assertCanManageBookingAttendance($actor, $booking);

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
        $booking = DB::transaction(function () use ($bookingId, $actor): Booking {
            $booking = Booking::query()->lockForUpdate()->find($bookingId);

            if ($booking === null) {
                throw new NotFoundHttpException('Booking not found');
            }

            // Tenant-scope the mutation too, not just the resolve — checkIn takes a
            // raw id and could be reached without resolveByCode.
            $this->assertCanManageBookingAttendance($actor, $booking);

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

    /**
     * Tenant-scope a specific ticket to the acting partner: the booking's event or
     * venue must belong to them (bookings have no partner_id — ownership runs
     * through the event/venue), and a desk person limited to specific events or
     * venues may only touch those. Super-admins and internal /control staff keep
     * their broad access (they've already passed assertCanManageAttendance).
     *
     * @throws AccessDeniedHttpException  When the ticket isn't the partner's / assigned to them.
     */
    private function assertCanManageBookingAttendance(User $actor, Booking $booking): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }

        // Only partner-side actors are tenant-scoped; internal department staff
        // (FINANCE/MARKETING/OPS) manage across the platform as they did before.
        $isPartnerActor = $actor->isDeskStaff() || $actor->hasRoleEither(['PARTNER']);
        if (! $isPartnerActor) {
            return;
        }

        $partnerId = $actor->effectivePartnerId();

        $ownsEvent = $booking->event_id !== null
            && Event::query()->whereKey($booking->event_id)->where('partner_id', $partnerId)->exists();
        $ownsVenue = $booking->venue_id !== null
            && Venue::query()->whereKey($booking->venue_id)->where('partner_id', $partnerId)->exists();

        if (! $ownsEvent && ! $ownsVenue) {
            throw new AccessDeniedHttpException('This ticket is not for your event');
        }

        // Per-staff assignment scoping (Phase 3): null means "all of the owner's".
        if ($booking->event_id !== null
            && ($allowedEvents = $actor->scopedEventIds()) !== null
            && ! in_array((int) $booking->event_id, $allowedEvents, true)) {
            throw new AccessDeniedHttpException('This event is not assigned to you');
        }

        if ($booking->venue_id !== null
            && ($allowedVenues = $actor->scopedVenueIds()) !== null
            && ! in_array((int) $booking->venue_id, $allowedVenues, true)) {
            throw new AccessDeniedHttpException('This venue is not assigned to you');
        }
    }

    /** Trim to null: an empty string must fall through to the account, not store "". */
    private static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
