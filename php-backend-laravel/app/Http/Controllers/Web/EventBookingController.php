<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Event;
use App\Services\BookingService;
use App\Support\ContactPrefill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
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

    /** POST /events/{id}/book — create the confirmed order. */
    public function store(Request $request, string $id): RedirectResponse
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

        try {
            $order = $this->bookings->createOrder(
                $request->user(),
                $event->id,
                $lines,
                $coupon !== '' ? $coupon : null,
                $contact,
            );
        } catch (ConflictHttpException|NotFoundHttpException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        // Backfill only what the account was missing, so a booker never has to type
        // their own details twice. Never overwrite details they already set.
        $this->backfillAccount($request->user(), $contact);

        return redirect()
            ->route('site.booking.pass', ['id' => $order->first()->id])
            ->with('success', 'Booking confirmed!');
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
        return Event::query()
            ->whereRaw('lower(status) = ?', ['published'])
            ->findOrFail((int) $id);
    }

    /**
     * Read `qty[...]` into service order lines, keeping only tiers that belong
     * to this event (key 0 = the flat event price when no tiers exist).
     *
     * @return list<array{ticketTypeId: int|null, quantity: int}>
     */
    private function linesFrom(Request $request, Event $event): array
    {
        $qty = $request->input('qty');
        if (! is_array($qty)) {
            return [];
        }

        $validTierIds = $event->ticketTypes->pluck('id')->all();
        $lines        = [];

        foreach ($qty as $key => $value) {
            $quantity = min(10, max(0, (int) $value));
            if ($quantity === 0) {
                continue;
            }

            if ((int) $key === 0 && $validTierIds === []) {
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
        $fee      = $event->convenienceFeeFor($subtotal);

        return [
            'lines'    => $priced,
            'subtotal' => $subtotal,
            'fee'      => $fee,
            'total'    => round($subtotal + $fee, 2),
        ];
    }
}
