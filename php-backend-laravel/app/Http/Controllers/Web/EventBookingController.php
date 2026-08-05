<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Event;
use App\Services\BookingNotifier;
use App\Services\BookingService;
use App\Services\RazorpayGateway;
use App\Support\ContactPrefill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Public-site event ticket booking — the web twin of the app's checkout.
 *
 * Reuses {@see BookingService::createOrder()} so pricing, per-tier inventory,
 * convenience fees and coupons behave identically to /api/bookings. Quantities
 * arrive as `qty[<ticketTypeId>]` (or `qty[0]` for the flat event price when
 * the host defined no tiers) and are re-validated server-side on confirm.
 */
final class EventBookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly RazorpayGateway $razorpay,
    ) {}

    /** GET /events/{id}/book — order review before confirming. */
    public function checkout(Request $request, string $id): View|RedirectResponse
    {
        $event = $this->publishedEvent($id);
        $lines = $this->linesFrom($request, $event);

        if ($lines === []) {
            return redirect("/events/{$event->id}")->with('error', 'Pick at least one ticket first.');
        }

        return view('site.event-book', [
            'title' => 'Review booking',
            'event' => $event,
            // Prefill what the account already knows; checkout only asks for the rest.
            'contact' => ContactPrefill::for($request->user()),
            ...$this->priceLines($event, $lines),
        ]);
    }

    /**
     * POST /events/{id}/book — reserve the order, then either confirm it (free) or hand off to
     * the payment page. Nothing is CONFIRMED for a paid order until the signature verifies.
     */
    public function store(Request $request, string $id): RedirectResponse|View
    {
        $event = $this->publishedEvent($id);
        $lines = $this->linesFrom($request, $event);

        if ($lines === []) {
            return redirect("/events/{$event->id}")->with('error', 'Pick at least one ticket first.');
        }

        // Who the ticket is for. Required here even though the service would fall
        // back to the account: the web form always shows these fields, so an empty
        // one is a user error to report, not a silent substitution.
        $contact = $request->validate([
            'contact.name'  => ['required', 'string', 'max:120'],
            'contact.email' => ['required', 'email', 'max:255'],
            'contact.phone' => ['required', 'string', 'max:32', 'regex:/^[0-9 +()\-]{7,20}$/'],
        ], [
            'contact.name.required'  => 'Enter the name for this ticket.',
            'contact.email.required' => 'Enter an email — your ticket goes here.',
            'contact.email.email'    => 'That email address doesn’t look right.',
            'contact.phone.required' => 'Enter a phone number.',
            'contact.phone.regex'    => 'That phone number doesn’t look right.',
        ])['contact'];

        $coupon = trim((string) $request->input('couponCode', ''));

        $slotId = $request->filled('eventSlotId') ? (int) $request->input('eventSlotId') : null;

        try {
            $order = $this->bookings->createOrder(
                $request->user(),
                $event->id,
                $lines,
                $coupon !== '' ? $coupon : null,
                $contact,
                eventSlotId: $slotId,
                reserve: true,
            );
        } catch (ConflictHttpException|NotFoundHttpException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        // Backfill only what the account was missing, so a booker never has to type
        // their own details twice. Never overwrite details they already set.
        $this->backfillAccount($request->user(), $contact);

        $grand      = $this->grandTotal($order);
        $grandPaise = (int) round($grand * 100);

        // Free order — no payment step, confirm the reservation straight away.
        if ($grandPaise <= 0) {
            $confirmed = $this->bookings->confirmReservation($order->pluck('id')->all(), null);

            BookingNotifier::dispatch($confirmed->first());

            return redirect()
                ->route('site.booking.pass', ['id' => $order->first()->id])
                ->with('success', 'Booking confirmed!');
        }

        // Paid order — create the Razorpay order and show the payment page. If the gateway is
        // unreachable, release the hold so the seats aren't stuck and send the buyer back.
        try {
            $rzp = $this->razorpay->createOrder($grandPaise, 'evt_' . $event->id . '_' . $order->first()->id);
        } catch (RuntimeException $e) {
            $this->bookings->releaseReservation($order->pluck('id')->all());

            return redirect("/events/{$event->id}")->with('error', 'Could not start payment. Please try again.');
        }

        $this->bookings->attachOrderId($order, (string) $rzp['id']);

        return view('site.event-pay', [
            'title'       => 'Complete payment',
            'event'       => $event,
            'amountLabel' => number_format($grand, 2),
            'razorKey'    => $this->razorpay->publicKey(),
            'orderId'     => $rzp['id'],
            'amount'      => $rzp['amount'],
            'currency'    => $rzp['currency'],
            'contact'     => $contact,
            'passUrl'     => route('site.booking.pass', ['id' => $order->first()->id]),
            'eventUrl'    => "/events/{$event->id}",
        ]);
    }

    /**
     * POST /events/{id}/book/coupon — quote a coupon against the cart on screen (AJAX from
     * the review page's Apply button), so the buyer sees the discount before committing.
     *
     * A quote only: it counts no use and holds nothing. The cart is re-priced here from the
     * same `qty[...]` the form carries rather than trusting a posted total, and confirm
     * re-resolves the code anyway — so a tampered subtotal buys nothing but a wrong preview.
     */
    public function previewCoupon(Request $request, string $id): JsonResponse
    {
        $event = $this->publishedEvent($id);
        $lines = $this->linesFrom($request, $event);
        $code  = trim((string) $request->input('couponCode', ''));

        if ($lines === [] || $code === '') {
            return response()->json(['applied' => false, 'message' => 'Enter a coupon code.']);
        }

        $priced   = $this->priceLines($event, $lines);
        $tickets  = array_sum(array_column($lines, 'quantity'));
        $resolved = $this->bookings->resolveCoupon($request->user(), $event->id, $code, $priced['subtotal'], $priced['fee'], $tickets);

        if ($resolved['coupon'] === null) {
            return response()->json(['applied' => false, 'message' => $resolved['message']]);
        }

        $discount = $resolved['discount'];

        return response()->json([
            'applied'       => true,
            'code'          => $resolved['coupon']->code,
            'message'       => $resolved['message'],
            'discount'      => $discount,
            'discountLabel' => number_format($discount, 2),
            'totalLabel'    => number_format(max(0, round($priced['total'] - $discount, 2)), 2),
        ]);
    }

    /**
     * POST /events/{id}/book/confirm — finalise after checkout (AJAX from the payment page).
     * Verifies the signature server-side and returns the pass URL to redirect to.
     */
    public function confirmWeb(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'razorpay_order_id'   => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature'  => ['required', 'string'],
        ]);

        if (! $this->razorpay->verifySignature($data['razorpay_order_id'], $data['razorpay_payment_id'], $data['razorpay_signature'])) {
            return response()->json(['ok' => false, 'error' => 'Payment verification failed'], 400);
        }

        try {
            $order = $this->bookings->confirmReservedOrder(
                $request->user(),
                $data['razorpay_order_id'],
                $data['razorpay_payment_id'],
            );
        } catch (NotFoundHttpException $e) {
            return response()->json(['ok' => false, 'error' => 'Reservation not found'], 404);
        }

        BookingNotifier::dispatch($order->first());

        return response()->json([
            'ok'       => true,
            'redirect' => route('site.booking.pass', ['id' => $order->first()->id]),
        ]);
    }

    /**
     * POST /events/{id}/book/release — hand back an abandoned reservation (modal dismissed /
     * payment failed) so its seats free up immediately.
     */
    public function releaseWeb(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['razorpay_order_id' => ['required', 'string']]);

        $this->bookings->releaseReservedOrder($request->user(), $data['razorpay_order_id']);

        return response()->json(['ok' => true]);
    }

    /** Grand total charged for an order: ticket subtotal + convenience fee − discount. */
    private function grandTotal(\Illuminate\Support\Collection $order): float
    {
        $subtotal = round((float) $order->sum('total_amount'), 2);
        $fee      = round((float) $order->sum('convenience_fee'), 2);
        $discount = round((float) $order->sum('discount'), 2);

        return round($subtotal + $fee - $discount, 2);
    }

    /**
     * GET /bookings/{id}/pass — the entry pass (code + QR) for the buyer.
     *
     * This renders an EVENT pass and the view dereferences the event throughout, so
     * anything without a live event is a 404 rather than a 500: a venue/turf booking
     * (event_id null — it has no event pass), or an event that has since been deleted.
     * Reachable from the bookings list, so it must not assume a happy path.
     */
    public function pass(Request $request, string $id): View
    {
        /** @var Booking $booking */
        $booking = Booking::query()
            ->with(['event', 'ticketType'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return $this->renderPass($booking);
    }

    /**
     * GET /t/{code} — the same entry pass, addressed by ticket code and open to
     * anyone holding it.
     *
     * This is the link that goes out over SMS and email, and it deliberately does
     * NOT require a session. The signed-in route above can't serve that job: a
     * ticket is routinely bought for someone else, and a walk-in registered at the
     * desk has the PARTNER's user_id on the row — so gating on the buyer's login
     * means the person actually holding the ticket can't open it.
     *
     * The 24-character code is the bearer secret, exactly as it already is for the
     * QR image at /t/{code}/qr.png (the QR is the code). Guessing one is not a
     * realistic attack; hammering the endpoint to try is, hence the throttle on
     * the route.
     */
    public function passByCode(string $code): View
    {
        /** @var Booking|null $booking */
        $booking = Booking::query()
            ->with(['event', 'ticketType'])
            ->where('ticket_code', $code)
            ->first();

        abort_if($booking === null, 404);

        return $this->renderPass($booking);
    }

    /** Shared rendering for both pass routes — one order, one pass, one QR per row. */
    private function renderPass(Booking $booking): View
    {
        abort_if($booking->event_id === null || $booking->event === null, 404);

        // Every booking row created in the same order shares the moment of
        // creation — show them all as one pass group (one QR per row).
        $group = Booking::query()
            ->with('ticketType')
            ->where('user_id', $booking->user_id)
            ->where('event_id', $booking->event_id)
            ->where('created_at', $booking->created_at)
            ->orderBy('id')
            ->get();

        return view('site.booking-pass', [
            'title'   => 'Your ticket',
            'booking' => $booking,
            'group'   => $group,
            'event'   => $booking->event,
        ]);
    }

    /**
     * Fill in a missing display name from what they just typed, and nothing else.
     *
     * Email and phone are DELIBERATELY not copied onto the account, however empty
     * or placeholder-ish it looks. Both are login credentials here (email OTP, and
     * WhatsApp OTP on the phone), while this checkout field is unverified free text
     * that people legitimately fill with someone else's details when buying a ticket
     * for them. Copying it over would let a stranger's address become the account's
     * login identity and receive its OTPs — an account takeover via a gift ticket.
     * The order keeps those details (attendee_*); the identity is only changed by a
     * flow that proves ownership.
     *
     * @param  array{name: string, email: string, phone: string}  $contact
     */
    private function backfillAccount(\App\Models\User $user, array $contact): void
    {
        if (blank($user->name)) {
            $user->fill(['name' => $contact['name']])->save();
        }
    }

    private function publishedEvent(string $id): Event
    {
        $event = Event::query()
            ->with('slots')
            ->whereRaw('lower(status) = ?', ['published'])
            ->findOrFail((int) $id);

        // An event that has already happened cannot be sold. This checked `status`
        // only, so a buyer could pay for — and be emailed a ticket to — an event that
        // finished days ago, leaving the organiser to handle the refund. Slot-aware,
        // so a multi-day event stays open until its last session (Event::endsAt()).
        abort_if($event->hasFinished(), 404);

        return $event;
    }

    /**
     * Read `qty[...]` into service order lines, keeping only tiers this buyer may
     * actually purchase — visible, on sale, and in an opened release phase (key 0 =
     * the flat event price when no tiers exist). A hand-typed qty for a hidden or
     * not-yet-released tier is dropped here rather than failing at payment time.
     *
     * @return list<array{ticketTypeId: int|null, quantity: int}>
     */
    private function linesFrom(Request $request, Event $event): array
    {
        $qty = $request->input('qty');
        if (! is_array($qty)) {
            return [];
        }

        $validTierIds = $event->saleableTicketTypes()->pluck('id')->all();
        // Key 0 is the flat event price, and only ever valid when the event has no
        // tiers at all — not merely when none are currently buyable.
        $hasTiers     = $event->ticketTypes->isNotEmpty();
        $lines        = [];

        foreach ($qty as $key => $value) {
            $quantity = min(10, max(0, (int) $value));
            if ($quantity === 0) {
                continue;
            }

            if ((int) $key === 0 && ! $hasTiers) {
                $lines[] = ['ticketTypeId' => null, 'quantity' => $quantity];
            } elseif (in_array((int) $key, $validTierIds, true)) {
                $lines[] = ['ticketTypeId' => (int) $key, 'quantity' => $quantity];
            }
        }

        return $lines;
    }

    /**
     * Price the order lines for the review page (display only — the service
     * re-prices authoritatively on confirm).
     *
     * @param  list<array{ticketTypeId: int|null, quantity: int}>  $lines
     * @return array{lines: list<array<string, mixed>>, subtotal: float, fee: float, total: float}
     */
    private function priceLines(Event $event, array $lines): array
    {
        $tiers  = $event->ticketTypes->keyBy('id');
        $priced = [];
        $subtotal = 0.0;

        foreach ($lines as $line) {
            $tier = $line['ticketTypeId'] !== null ? $tiers->get($line['ticketTypeId']) : null;
            $unit = $tier !== null ? $tier->effectivePrice() : (float) $event->price;
            $amount = round($unit * $line['quantity'], 2);
            $subtotal += $amount;

            $priced[] = [
                'ticketTypeId' => $line['ticketTypeId'],
                'name'         => $tier?->name ?? 'Standard',
                'quantity'     => $line['quantity'],
                'unit'         => $unit,
                'amount'       => $amount,
            ];
        }

        $subtotal = round($subtotal, 2);
        $feeLines = $event->feeLinesFor($subtotal);
        $fee      = $event->feesTotalFor($subtotal);

        return [
            'lines'    => $priced,
            'subtotal' => $subtotal,
            'fee'      => $fee,
            'feeLines' => $feeLines,
            'total'    => round($subtotal + $fee, 2),
        ];
    }
}
