<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Services\BookingNotifier;
use App\Services\BookingService;
use App\Services\RazorpayGateway;
use App\Services\VenueBookingWindow;
use App\Services\VenueSlotAvailability;
use App\Support\Operations;
use App\Support\PlatformRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Public website sports venue booking controller.
 * Handles slot reservations, Razorpay standard checkout initiation,
 * signature verification, and booking confirmation.
 */
final class VenueBookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly RazorpayGateway $razorpay,
        private readonly VenueSlotAvailability $availability,
        private readonly VenueBookingWindow $window,
    ) {}

    /**
     * POST /gamehub/{id}/book — Reserve court slots and prepare payment.
     */
    public function reserve(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Authentication required to book slots.'], 401);
        }

        $venue = Venue::published()->findOrFail($id);

        if (! $venue->is_bookable) {
            return response()->json(['error' => 'This venue is not currently open for bookings.'], 422);
        }

        $validated = $request->validate([
            'date'       => ['required', 'string'],
            'sport'      => ['nullable', 'string', 'max:100'],
            'court'      => ['nullable', 'string', 'max:150'],
            'court_id'   => ['nullable', 'integer'],
            'slots'      => ['required', 'array', 'min:1'],
            'couponCode' => ['nullable', 'string', 'max:40'],
        ]);

        try {
            $bookingDate = Carbon::parse($validated['date'])->startOfDay();
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Invalid booking date selected.'], 422);
        }

        if ($bookingDate->lt(today())) {
            return response()->json(['error' => 'Cannot book slots for past dates.'], 422);
        }

        if (! $this->window->allows($venue, $user, $bookingDate)) {
            return response()->json(['error' => $this->window->refusal($venue, $user)], 422);
        }

        // Resolve court
        $court = null;
        if (! empty($validated['court_id'])) {
            $court = VenueCourt::query()->where('venue_id', $venue->id)->where('is_active', true)->find($validated['court_id']);
        } elseif (! empty($validated['court'])) {
            $court = VenueCourt::query()
                ->where('venue_id', $venue->id)
                ->where('is_active', true)
                ->where('name', trim((string) $validated['court']))
                ->first();
        }

        if ($court === null && $venue->courts()->where('is_active', true)->exists()) {
            $court = $venue->courts()->where('is_active', true)->first();
        }

        if ($court === null) {
            return response()->json(['error' => 'A valid court must be selected for booking.'], 422);
        }

        $baseRate = (int) ($court->price ?? $venue->price ?? 0);
        if ($baseRate <= 0) {
            return response()->json(['error' => 'Pricing for this court is not configured.'], 422);
        }

        // Only the venue's own slots can be sold: the start times on its template for this
        // day (/control → venue → slots), each for one slot length, and only while open for
        // this court (bookings, holds, blocks, closed days — VenueSlotAvailability). This used
        // to take any time range the browser sent, so the website's made-up 6 AM–11 PM grid
        // could sell hours the venue never opened, or a 5-hour range at a one-hour price.
        $step = max(30, (int) ($venue->slot_minutes ?: 60));
        $states = collect($this->availability->forDate($venue, $bookingDate, 1, (int) $court->id))->keyBy('id');
        $sport = trim((string) ($validated['sport'] ?? ''));
        $sellable = [];
        foreach ($venue->slotsOn($bookingDate) as $slot) {
            $m = BookingService::timeToMinutes($slot->time);
            if ($m === null || ! $slot->allowsCourt($court) || ($sport !== '' && ! $slot->supportsSport($sport))) {
                continue;
            }
            $sellable[$m] = $states[$slot->id]['state'] ?? VenueSlotAvailability::CLOSED;
        }

        // Parse and validate each slot
        $slotsToReserve = [];
        $nowMinutes = (int) now()->format('H') * 60 + (int) now()->format('i');
        $isToday = $bookingDate->isToday();

        foreach ($validated['slots'] as $slotRaw) {
            $timeRange = is_array($slotRaw) ? (string) ($slotRaw['time'] ?? '') : (string) $slotRaw;
            $timeRange = trim($timeRange);
            if ($timeRange === '') {
                continue;
            }

            $parts = explode('-', $timeRange);
            $startStr = trim($parts[0] ?? '');
            $endStr = trim($parts[1] ?? '');

            $startMin = BookingService::timeToMinutes($startStr);
            // endMinutes: "12:00 AM" as an END is midnight at the end of the day (1440), not 0.
            $endMin = BookingService::endMinutes($endStr);
            if ($startMin === null) {
                return response()->json(['error' => "Invalid slot time: {$timeRange}"], 422);
            }
            if ($endMin === null) {
                $endMin = $startMin + 60;
            }
            // Every booking lives inside one calendar date; after-midnight hours are sold on
            // the next day's date (see Venue::regenerateSlotsFromHours).
            if ($endMin <= $startMin || $endMin > 24 * 60) {
                return response()->json(['error' => "The slot '{$timeRange}' runs past midnight. Book the hours after 12 AM on the next day's date."], 422);
            }

            $state = $sellable[$startMin] ?? null;
            if ($state === null || $endMin !== min(24 * 60, $startMin + $step)) {
                return response()->json(['error' => "'{$timeRange}' is not one of this venue's slots. Please pick a slot from the list."], 422);
            }
            if ($state === VenueSlotAvailability::BOOKED) {
                return response()->json(['error' => "The slot '{$timeRange}' is already reserved or booked. Please select another slot."], 422);
            }
            if ($state !== VenueSlotAvailability::OPEN) {
                return response()->json(['error' => "The slot '{$timeRange}' is not open for booking."], 422);
            }

            // Reject slot if time has already passed today
            if ($isToday && $startMin <= $nowMinutes) {
                return response()->json(['error' => "The slot '{$timeRange}' has already passed for today."], 422);
            }

            $startHm = sprintf('%02d:%02d', intdiv($startMin, 60), $startMin % 60);
            $endHm = sprintf('%02d:%02d', intdiv($endMin, 60), $endMin % 60);

            // Determine slot price (court rate, peak rate, or venue base rate)
            $rate = $baseRate;
            if ($court !== null) {
                $rate = $court->rateFor($bookingDate, $startStr, $baseRate);
            }
            // Never fall back to a price the browser sent — that would let the buyer name it.
            if ($rate <= 0) {
                return response()->json(['error' => "Pricing for '{$timeRange}' is not configured."], 422);
            }

            // Check overlap against existing confirmed or live held bookings
            $overlapQuery = Booking::query()
                ->where('booking_type', 'venue')
                ->where('venue_id', $venue->id)
                ->whereDate('slot_date', $bookingDate->toDateString())
                ->where(function ($q) {
                    $q->whereRaw('upper(status) = ?', ['CONFIRMED'])
                        ->orWhere(function ($sub) {
                            $sub->whereRaw('upper(status) = ?', ['PENDING'])
                                ->where('reserved_until', '>', now());
                        });
                });

            if ($court !== null) {
                $overlapQuery->whereIn('venue_court_id', $court->allRelatedCourtIds());
            }

            // Compared in minutes, not as "HH:MM" strings: a booking ending at midnight is
            // stored "24:00" (or "00:00" on older rows), and as text "00:00" > "23:00" is
            // false — the 11 PM hour could be sold twice.
            $clash = $overlapQuery->get(['start_time', 'end_time'])->contains(function (Booking $b) use ($startMin, $endMin): bool {
                $bs = BookingService::timeToMinutes($b->start_time);
                $be = BookingService::endMinutes($b->end_time);

                // No window on the existing row: it blocks the day, as the app path does.
                return $bs === null || $be === null || ($startMin < $be && $endMin > $bs);
            });

            if ($clash) {
                return response()->json([
                    'error' => "The slot '{$timeRange}' is already reserved or booked. Please select another slot.",
                ], 422);
            }

            $slotsToReserve[] = [
                'time_range' => $timeRange,
                'start_hm'   => $startHm,
                'end_hm'     => $endHm,
                'rate'       => $rate,
            ];
        }

        if (empty($slotsToReserve)) {
            return response()->json(['error' => 'Please select at least one valid slot.'], 422);
        }

        // Same pricing as the app (BookingService::reserveVenue): subtotal → the venue's own
        // convenience fee → coupon → Pulse tax, and commission on the post-discount slot value. The
        // fee and coupon are worked out once on the whole order (a flat fee is charged once,
        // not per slot), then split across the rows so that every row's `total_amount` is its
        // own share of the charge. The ledger settles each row for amountCharged(), so the
        // rows must add up to exactly the Razorpay amount — no row may carry the order total.
        $rates = array_map(static fn (array $s): float => (float) $s['rate'], $slotsToReserve);
        $subtotal = round(array_sum($rates), 2);
        $fee = $venue->convenienceFeeFor($subtotal);

        $couponCode = trim((string) ($validated['couponCode'] ?? ''));
        $applied = $couponCode !== ''
            ? $this->bookings->resolveVenueCoupon($user, (int) $venue->id, $couponCode, $subtotal, $fee)
            : ['coupon' => null, 'discount' => 0.0, 'message' => ''];
        if ($couponCode !== '' && $applied['coupon'] === null) {
            return response()->json(['error' => $applied['message']], 422);
        }
        $discount = round((float) $applied['discount'], 2);

        // Pulse tax sits beside each row's total_amount (never in it), so the venue's payout
        // — a sum of total_amount — never includes it. amountCharged() adds it back.
        $tax = Venue::taxFor($subtotal, $discount);

        $grandTotal = max(0.0, round($subtotal + $fee - $discount + $tax, 2));
        $grandPaise = (int) round($grandTotal * 100);

        try {
            Operations::assertBookingsOpen(Operations::VENUES);
            if ($grandPaise > 0) {
                Operations::assertPaymentsOn();
            }
        } catch (ConflictHttpException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }

        $feeShares = self::split($fee, $rates);
        $discountShares = self::split($discount, $rates);
        $taxShares = self::split($tax, $rates);
        $commissionPercent = PlatformRules::float('fees.venue_commission_percent');

        /** @var Collection<int, Booking> $bookings */
        $bookings = collect();

        DB::transaction(function () use ($venue, $court, $user, $bookingDate, $slotsToReserve, $feeShares, $discountShares, $taxShares, $commissionPercent, $applied, &$bookings) {
            foreach ($slotsToReserve as $i => $s) {
                $slotRate = (float) $s['rate'];
                $rowFee = $feeShares[$i];
                $rowDiscount = $discountShares[$i];

                $booking = Booking::query()->create([
                    'quantity'        => 1,
                    'total_amount'    => max(0.0, round($slotRate + $rowFee - $rowDiscount, 2)),
                    'convenience_fee' => $rowFee,
                    'tax_amount'      => $taxShares[$i],
                    'discount'        => $rowDiscount,
                    // One row per order carries the code: the per-customer coupon limit counts
                    // discounted rows, so tagging every slot would spend it once per slot.
                    'coupon_code'     => $i === 0 ? $applied['coupon']?->code : null,
                    'host_deduction'  => round(max(0.0, $slotRate - $rowDiscount) * $commissionPercent / 100, 2),
                    'status'          => 'PENDING',
                    'reserved_until'  => now()->addMinutes(BookingService::holdMinutes()),
                    'booking_type'    => 'venue',
                    'user_id'         => $user->id,
                    'event_id'        => null,
                    'venue_id'        => $venue->id,
                    'venue_court_id'  => $court?->id,
                    'slot_date'       => $bookingDate->toDateString(),
                    'start_time'      => $s['start_hm'],
                    'end_time'        => $s['end_hm'],
                    'slot_label'      => ($court ? $court->name . ' · ' : '') . $s['time_range'],
                    'channel'         => 'online',
                    'attendee_name'   => $user->name,
                    'attendee_email'  => $user->email,
                    'attendee_phone'  => $user->phone,
                ]);

                $bookings->push($booking);
            }
        });

        $primary = $bookings->first();
        $slotsSummary = $bookings->pluck('slot_label')->implode(', ');
        $breakdown = [
            'subtotal'       => $subtotal,
            'convenienceFee' => $fee,
            'discount'       => $discount,
            'tax'            => $tax,
            'taxLabel'       => Venue::taxLabel(),
            'total'          => $grandTotal,
        ];

        // Free order (0 paise)
        if ($grandPaise <= 0) {
            $confirmed = $this->bookings->confirmReservation($bookings->pluck('id')->all(), null);
            BookingNotifier::dispatch($confirmed->first());

            return response()->json([
                'ok'               => true,
                'requires_payment' => false,
                'reference'        => $primary->ticket_code,
                'total'            => $grandTotal,
                'breakdown'        => $breakdown,
                'date'             => $bookingDate->format('D, d M Y'),
                'slots'            => $slotsSummary,
                'redirect'         => route('site.bookings'),
            ]);
        }

        // Paid order — Create Razorpay order
        try {
            $rzp = $this->razorpay->createOrder($grandPaise, 'vnu_' . $venue->id . '_' . $primary->id);
        } catch (RuntimeException $e) {
            $this->bookings->releaseReservation($bookings->pluck('id')->all());

            return response()->json(['error' => 'Payment gateway unavailable. Please try again.'], 502);
        }

        $this->bookings->attachOrderId($bookings, (string) $rzp['id']);

        return response()->json([
            'ok'               => true,
            'requires_payment' => true,
            'payment'          => [
                'key'         => $this->razorpay->publicKey(),
                'orderId'     => $rzp['id'],
                'amount'      => $rzp['amount'],
                'currency'    => $rzp['currency'],
                'name'        => $venue->name,
                'description' => ($court ? $court->name . ' - ' : '') . $bookings->count() . ' Slot(s)',
                'prefill'     => [
                    'name'    => (string) ($user->name ?? ''),
                    'email'   => (string) ($user->email ?? ''),
                    'contact' => (string) ($user->phone ?? ''),
                ],
            ],
            'bookingRef'       => $primary->ticket_code,
            'total'            => $grandTotal,
            'breakdown'        => $breakdown,
            'date'             => $bookingDate->format('D, d M Y'),
            'slots'            => $slotsSummary,
        ]);
    }

    /**
     * Split an order-level amount across rows in proportion to their slot rates, in whole
     * paise, with the rounding remainder on the last row so the shares add up exactly.
     *
     * @param  list<float>  $weights
     * @return list<float>
     */
    private static function split(float $amount, array $weights): array
    {
        $totalPaise = (int) round($amount * 100);
        $weightSum = array_sum($weights);
        $last = count($weights) - 1;

        if ($totalPaise <= 0 || $weightSum <= 0) {
            return array_fill(0, count($weights), 0.0);
        }

        $shares = [];
        $given = 0;
        foreach ($weights as $i => $w) {
            $paise = $i === $last ? $totalPaise - $given : (int) floor($totalPaise * $w / $weightSum);
            $given += $paise;
            $shares[] = $paise / 100;
        }

        return $shares;
    }

    /**
     * POST /gamehub/{id}/confirm — Finalise reserved venue booking after Razorpay checkout.
     */
    public function confirm(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'razorpay_order_id'   => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature'  => ['required', 'string'],
        ]);

        if (! $this->razorpay->verifySignature($data['razorpay_order_id'], $data['razorpay_payment_id'], $data['razorpay_signature'])) {
            return response()->json(['ok' => false, 'error' => 'Payment signature verification failed'], 400);
        }

        try {
            $order = $this->bookings->confirmReservedOrder(
                $user,
                $data['razorpay_order_id'],
                $data['razorpay_payment_id'],
            );
        } catch (NotFoundHttpException $e) {
            return response()->json(['ok' => false, 'error' => 'Reservation not found or expired.'], 404);
        }

        BookingNotifier::dispatch($order->first());

        return response()->json([
            'ok'        => true,
            'reference' => $order->first()->ticket_code,
            'message'   => 'Booking confirmed successfully!',
            'redirect'  => route('site.bookings'),
        ]);
    }

    /**
     * POST /gamehub/{id}/release — Release held slots if user cancels payment checkout.
     */
    public function release(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'razorpay_order_id' => ['required', 'string'],
        ]);

        $this->bookings->releaseReservedOrder($user, $data['razorpay_order_id']);

        return response()->json(['ok' => true]);
    }
}
