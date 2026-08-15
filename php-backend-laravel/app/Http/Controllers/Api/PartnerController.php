<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Event;
use App\Models\CustomerPackage;
use App\Models\PackageRedemption;
use App\Models\VenuePackage;
use App\Models\PartnerPayoutAccount;
use App\Models\PayoutBatch;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBlockedDate;
use App\Models\VenueCourt;
use App\Models\VenueSlot;
use App\Services\BookingLedger;
use App\Services\BookingNotifier;
use App\Services\BookingService;
use App\Services\PartnerSettlement;
use App\Services\RazorpayGateway;
use App\Support\BookingReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Read/dashboard API for the partner mobile app. Everything is scoped to the
 * signed-in partner (auth.jwt + auth.partner). Successful bookings carry the
 * status "CONFIRMED"; cancelled/refunded/failed are excluded from money math.
 *
 * Ticket check-in is intentionally NOT here — the app reuses the existing
 * host-gated flow: GET /api/bookings/resolve/{code} and
 * PATCH /api/bookings/{id}/check-in.
 */
class PartnerController extends Controller
{
    private const PAID = 'CONFIRMED';

    /** Case-insensitive money statuses, matching the web analytics widgets. */
    private const PAID_STATUSES = ['confirmed', 'paid', 'completed'];

    public function __construct(
        private readonly BookingService $bookings,
        private readonly BookingLedger $ledger,
        private readonly RazorpayGateway $razorpay,
    ) {
    }

    /** GET /api/partner/overview — headline numbers for the home screen. */
    public function overview(Request $request): JsonResponse
    {
        $partnerId = $request->user()->effectivePartnerId();
        $today = now()->toDateString();

        $eventIds = Event::query()->where('partner_id', $partnerId)->pluck('id');
        $venueIds = Venue::query()->where('partner_id', $partnerId)->pluck('id');

        $paid = fn () => Booking::query()
            ->where('status', self::PAID)
            ->where(function ($q) use ($eventIds, $venueIds): void {
                $q->whereIn('event_id', $eventIds)->orWhereIn('venue_id', $venueIds);
            });

        // 14-day revenue trend across all of the partner's events + venues, for
        // the home hero sparkline. Gaps filled with 0.
        $start = now()->startOfDay()->subDays(13);
        $rows = (clone $paid())->where('created_at', '>=', $start)->get(['total_amount', 'created_at']);
        $byDay = [];
        foreach ($rows as $row) {
            $byDay[$row->created_at->format('Y-m-d')] = ($byDay[$row->created_at->format('Y-m-d')] ?? 0) + (float) $row->total_amount;
        }
        $trend = [];
        for ($i = 0; $i < 14; $i++) {
            $trend[] = round($byDay[$start->copy()->addDays($i)->format('Y-m-d')] ?? 0, 2);
        }

        return response()->json([
            'partner' => [
                'name' => $request->user()->name,
                'type' => $request->user()->partner_type, // 'event' | 'venue' | null
            ],
            'events' => [
                'total'    => $eventIds->count(),
                'upcoming' => Event::query()->where('partner_id', $partnerId)
                    ->whereDate('date', '>=', $today)->count(),
            ],
            'venues' => [
                'total' => $venueIds->count(),
            ],
            'sales' => [
                'revenue'        => round((float) (clone $paid())->sum('total_amount'), 2),
                'tickets_sold'   => (int) (clone $paid())->sum('quantity'),
                'bookings_total' => (clone $paid())->count(),
                'bookings_today' => (clone $paid())->whereDate('created_at', $today)->count(),
                'online'         => (clone $paid())->where('channel', 'online')->count(),
                'offline'        => (clone $paid())->where('channel', 'offline')->count(),
                'cancelled'      => Booking::query()
                    ->whereRaw('lower(status) in (?, ?, ?)', ['cancelled', 'refunded', 'failed'])
                    ->where(function ($q) use ($eventIds, $venueIds): void {
                        $q->whereIn('event_id', $eventIds)->orWhereIn('venue_id', $venueIds);
                    })->count(),
            ],
            'trend' => $trend,
        ]);
    }

    /** GET /api/partner/events — the partner's own events with a sales rollup. */
    public function events(Request $request): JsonResponse
    {
        $partnerId = $request->user()->effectivePartnerId();

        $events = Event::query()
            ->where('partner_id', $partnerId)
            ->orderByDesc('date')
            ->get()
            ->map(fn (Event $e): array => $this->eventSummary($e));

        return response()->json(['data' => $events]);
    }

    /** GET /api/partner/events/{id} — one event, its sales and recent bookings. */
    public function showEvent(Request $request, string $id): JsonResponse
    {
        $partnerId = $request->user()->effectivePartnerId();

        $event = Event::query()->where('partner_id', $partnerId)->findOrFail($id);

        $bookings = Booking::query()
            ->where('event_id', $event->id)
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Booking $b): array => $this->bookingSummary($b));

        return response()->json([
            'event'    => $this->eventSummary($event),
            'bookings' => $bookings,
        ]);
    }

    /** GET /api/partner/venues — the partner's own venues with a bookings rollup. */
    public function venues(Request $request): JsonResponse
    {
        $partnerId = $request->user()->effectivePartnerId();

        $venues = Venue::query()
            ->where('partner_id', $partnerId)
            ->orderBy('name')
            ->get()
            ->map(fn (Venue $v): array => $this->venueSummary($v));

        return response()->json(['data' => $venues]);
    }

    /** GET /api/partner/venues/{id} — one venue, its slots and upcoming bookings. */
    public function showVenue(Request $request, string $id): JsonResponse
    {
        $partnerId = $request->user()->effectivePartnerId();

        $venue = Venue::query()->with('slots')->where('partner_id', $partnerId)->findOrFail($id);

        $bookings = Booking::query()
            ->where('venue_id', $venue->id)
            ->orderByDesc('slot_date')
            ->limit(50)
            ->get()
            ->map(fn (Booking $b): array => $this->bookingSummary($b));

        return response()->json([
            'venue'    => $this->venueSummary($venue),
            'slots'    => $venue->slots->map(fn ($s): array => [
                'id'    => $s->id,
                'label' => $s->label ?? $s->name ?? null,
                'price' => $s->price ?? null,
            ]),
            'bookings' => $bookings,
        ]);
    }

    /**
     * GET /api/partner/bookings — a unified recent-sales feed across the
     * partner's events and venues, newest first.
     */
    public function bookings(Request $request): JsonResponse
    {
        $partnerId = $request->user()->effectivePartnerId();

        $bookings = Booking::query()
            ->where(function ($q) use ($partnerId): void {
                $q->whereHas('event', fn ($e) => $e->where('partner_id', $partnerId))
                    ->orWhereHas('venue', fn ($v) => $v->where('partner_id', $partnerId));
            })
            ->with(['event:id,title', 'venue:id,name'])
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (Booking $b): array => $this->bookingSummary($b) + [
                'event' => $b->event?->title,
                'venue' => $b->venue?->name,
            ]);

        return response()->json(['data' => $bookings]);
    }

    /**
     * POST /api/partner/check-in — resolve a scanned ticket code and mark it
     * arrived, in one call. Only succeeds if the ticket belongs to one of this
     * partner's own events or venues. Idempotent: re-scanning reports "already".
     */
    public function checkInByCode(Request $request): JsonResponse
    {
        $partnerId = $request->user()->effectivePartnerId();
        $code = trim((string) $request->input('code'));

        if ($code === '') {
            return response()->json(['error' => 'Missing ticket code'], 422);
        }

        $booking = Booking::query()->where('ticket_code', $code)->first();
        if ($booking === null) {
            return response()->json(['error' => 'Ticket not found'], 404);
        }

        $ownsEvent = $booking->event_id !== null
            && Event::query()->where('id', $booking->event_id)->where('partner_id', $partnerId)->exists();
        $ownsVenue = $booking->venue_id !== null
            && Venue::query()->where('id', $booking->venue_id)->where('partner_id', $partnerId)->exists();

        if (! $ownsEvent && ! $ownsVenue) {
            return response()->json(['error' => 'This ticket is not for your event'], 403);
        }

        // Per-staff assignment scoping (Phase 3): a desk person limited to specific
        // events/venues may only check in those; null means all of the owner's.
        $actor = $request->user();
        if ($booking->event_id !== null
            && ($allowedEvents = $actor->scopedEventIds()) !== null
            && ! in_array((int) $booking->event_id, $allowedEvents, true)) {
            return response()->json(['error' => 'This event is not assigned to you'], 403);
        }
        if ($booking->venue_id !== null
            && ($allowedVenues = $actor->scopedVenueIds()) !== null
            && ! in_array((int) $booking->venue_id, $allowedVenues, true)) {
            return response()->json(['error' => 'This venue is not assigned to you'], 403);
        }

        if (in_array(strtolower((string) $booking->status), ['cancelled', 'refunded', 'failed'], true)) {
            return response()->json(['status' => 'invalid', 'booking' => $this->bookingSummary($booking)], 409);
        }

        if ((int) $booking->checked_in_count >= (int) $booking->quantity) {
            return response()->json(['status' => 'already', 'booking' => $this->bookingSummary($booking)]);
        }

        $booking->checked_in_count = (int) $booking->quantity;
        $booking->checked_in_at = now();
        $booking->save();

        return response()->json(['status' => 'ok', 'booking' => $this->bookingSummary($booking)]);
    }

    /**
     * GET /api/partner/venues/{id}/day?date=YYYY-MM-DD — the day-grid: every slot
     * with its booked/capacity for that date, plus the bookings in each slot.
     */
    public function venueDay(Request $request, string $id): JsonResponse
    {
        $partnerId = $request->user()->effectivePartnerId();
        $venue = Venue::query()->where('partner_id', $partnerId)->findOrFail($id);

        $date = $request->query('date');
        $date = is_string($date) && strtotime($date) ? date('Y-m-d', strtotime($date)) : now()->toDateString();

        $isBlocked = VenueBlockedDate::query()
            ->where('venue_id', $venue->id)->whereDate('date', $date)->exists();

        $slots = VenueSlot::query()->where('venue_id', $venue->id)->orderBy('sort_order')->get();
        $courts = VenueCourt::query()->where('venue_id', $venue->id)
            ->where('is_active', true)->orderBy('sort_order')->get();

        $bookings = Booking::query()
            ->where('booking_type', 'venue')->where('venue_id', $venue->id)
            ->whereDate('slot_date', $date)->where('status', self::PAID)
            ->with('user:id,name')->get();
        $bySlot = $bookings->groupBy('venue_slot_id');
        // One bucket per (court, slot) cell so the grid can render each court column.
        // Court id 0 keys the bookings made before courts existed (venue-level only).
        $byCell = $bookings->groupBy(
            fn (Booking $b): string => ((int) ($b->venue_court_id ?? 0)).'-'.((int) ($b->venue_slot_id ?? 0)),
        );

        $rows = $slots->map(function (VenueSlot $s) use ($bySlot, $byCell, $courts, $date, $venue): array {
            $b = $bySlot->get($s->id) ?? collect();
            $cells = $courts->map(function (VenueCourt $c) use ($s, $byCell, $date, $venue): array {
                $cb = $byCell->get($c->id.'-'.$s->id) ?? collect();
                // The rate this cell would actually CHARGE — peak included. Showing the
                // base rate here while reserveVenue() bills the peak one would have the
                // desk quoting a price the customer is never charged.
                $rate = $c->rateFor(Carbon::parse($date), $s->time, (int) ($venue->price ?? 0));

                return [
                    'court_id'  => $c->id,
                    'booked'    => $cb->count(),
                    'is_booked' => $cb->isNotEmpty(),
                    'price'     => (float) $rate,
                    'is_peak'   => $c->isPeak(Carbon::parse($date), $s->time),
                    'bookings'  => $cb->map(fn (Booking $x): array => $this->slotBooking($x))->values(),
                ];
            })->values();

            return [
                'slot_id'   => $s->id,
                'label'     => trim(($s->day ?? '').' · '.($s->time ?? ''), " ·\t"),
                'time'      => $s->time,
                'price'     => (float) $s->price,
                'capacity'  => (int) $s->capacity,
                'booked'    => $b->count(),
                'available' => max((int) $s->capacity - $b->count(), 0),
                'is_open'   => (bool) $s->is_available,
                'bookings'  => $b->map(fn (Booking $x): array => $this->slotBooking($x))->values(),
                'courts'    => $cells,
            ];
        });

        return response()->json([
            'date'       => $date,
            'venue'      => ['id' => $venue->id, 'name' => $venue->name],
            'is_blocked' => $isBlocked,
            'courts'     => $courts->map(fn (VenueCourt $c): array => [
                'id'     => $c->id,
                'name'   => $c->name,
                'sports' => $c->sportsList(),
            ])->values(),
            'slots'      => $rows,
        ]);
    }

    /** POST /api/partner/venues/{id}/bookings — create a walk-in (offline) booking. */
    public function storeOfflineBooking(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'slotId'     => ['nullable', 'integer'],
            'courtId'    => ['nullable', 'integer'],
            'date'       => ['required', 'date'],
            'duration'   => ['nullable', 'integer', 'min:1', 'max:12'],
            'guestName'  => ['nullable', 'string', 'max:120'],
            'guestPhone' => ['nullable', 'string', 'max:30'],
            // How the desk took the money. 'link' mints a Razorpay payment link and
            // leaves the booking unpaid until the payment webhook says otherwise;
            // 'later' records nothing at all.
            'paymentMethod' => ['nullable', 'string', 'in:cash,upi,card,link,later,package'],
            // Which of the customer's passes to spend a session from.
            'customerPackageId' => ['nullable', 'integer'],
        ]);

        // Scope the venue to the acting partner before booking — a caller must not be
        // able to create a booking on another tenant's venue by passing its id.
        $partnerId = $request->user()->effectivePartnerId();
        $venue = Venue::query()->where('partner_id', $partnerId)->findOrFail($id);

        $booking = $this->bookings->createOfflineVenueBooking(
            $request->user(),
            (int) $venue->id,
            isset($data['slotId']) ? (int) $data['slotId'] : null,
            (string) $data['date'],
            $data['guestName'] ?? null,
            $data['guestPhone'] ?? null,
            isset($data['courtId']) ? (int) $data['courtId'] : null,
            isset($data['duration']) ? (int) $data['duration'] : 1,
        );

        $method = $data['paymentMethod'] ?? 'later';
        $amount = (float) $booking->total_amount;
        $payLink = null;
        $payLinkId = null;

        // Money that physically changed hands at the desk is recorded straight away;
        // the ledger recomputes amount_paid/payment_status in the same transaction.
        if ($method === 'package') {
            // Spend a session instead of taking money. The pass was already paid
            // for when it was sold, so the booking is settled without a payment.
            $pkg = CustomerPackage::query()
                ->where('partner_id', $partnerId)
                ->when(! empty($data['customerPackageId']), fn ($q) => $q->where('id', $data['customerPackageId']))
                ->when(empty($data['customerPackageId']), fn ($q) => $q->where('customer_phone', (string) $this->digits($data['guestPhone'] ?? '')))
                ->with('package')->withCount('redemptions')
                ->orderBy('expires_at')
                ->get()
                ->first(fn (CustomerPackage $c): bool => $c->isUsable());

            if ($pkg === null) {
                // Refuse rather than silently charge: the desk chose "use a pass",
                // and quietly making it payable would be a different transaction.
                $booking->delete();

                return response()->json(['error' => 'No usable package for that customer.'], 422);
            }

            PackageRedemption::query()->create([
                'customer_package_id' => $pkg->id,
                'booking_id'          => $booking->id,
            ]);

            // Zero-rate the booking so it can't also appear as money owed.
            $booking->total_amount = 0;
            $booking->amount_paid = 0;
            $booking->payment_status = 'paid';
            $booking->save();
        } elseif (in_array($method, ['cash', 'upi', 'card'], true) && $amount > 0) {
            $this->ledger->collect($booking, $amount, $method, $request->user());
            $booking->refresh();
        } elseif ($method === 'link' && $amount > 0) {
            // Best-effort: a link that can't be minted must not lose the booking the
            // partner just took — surface the failure and leave it payable at the desk.
            try {
                $link = $this->razorpay->createPaymentLink(
                    (int) round($amount * 100),
                    'Booking at '.$venue->name,
                    $data['guestName'] ?? null,
                    $data['guestPhone'] ?? null,
                    ['booking_id' => (string) $booking->id],
                );
                $payLink = $link['short_url'];
                $payLinkId = $link['id'] ?: null;
            } catch (\Throwable $e) {
                Log::warning("Booking {$booking->id}: payment link failed: ".$e->getMessage());
            }
        }

        // Confirmation to the CUSTOMER (WhatsApp, with the booking code + QR link).
        //
        // ONLY once the money is actually in. The ticket carries a QR that gets someone
        // through the gate, so sending it against an unpaid "pay by link" booking hands
        // out free entry — the customer holds a valid ticket whether or not they ever pay.
        // For the link path the confirmation is sent by the payment webhook instead, when
        // Razorpay reports the capture ({@see RazorpayBilling::applyPaidPaymentLink()}).
        //
        // BookingNotifier is guest-only for walk-ins, so this can never fire the
        // customer's ticket at the venue's own phone.
        $notified = false;
        $isPaid = strtolower((string) $booking->payment_status) === 'paid' || $amount <= 0;
        if ($isPaid && trim((string) ($data['guestPhone'] ?? '')) !== '') {
            BookingNotifier::dispatch($booking);
            $notified = true;
        }

        return response()->json([
            'status'         => 'ok',
            'booking'        => $this->slotBooking($booking),
            'payment_method' => $method,
            'payment_link'   => $payLink,
            'payment_link_id' => $payLinkId,
            'notified'       => $notified,
        ], 201);
    }

    /**
     * POST /api/partner/bookings/{id}/payment-status { linkId } — has this walk-in's
     * payment link been paid yet?
     *
     * Asks Razorpay directly rather than waiting on a webhook, so the desk can watch the
     * payment land while the customer is still at the counter — and so a venue whose
     * webhook was never configured still gets paid bookings settled.
     *
     * Settles exactly once: the payment id is the ledger row's reference, so polling this
     * every few seconds (or racing the webhook) cannot collect the same money twice.
     */
    public function paymentStatus(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'linkId' => ['required', 'string', 'max:64'],
        ]);

        $partnerId = $request->user()->effectivePartnerId();

        // Scope to the acting partner: a booking id alone must not expose another
        // tenant's payment state.
        $booking = Booking::query()
            ->where('id', $id)
            ->whereIn('venue_id', Venue::query()->where('partner_id', $partnerId)->select('id'))
            ->firstOrFail();

        if (strtolower((string) $booking->payment_status) === 'paid') {
            return response()->json(['paid' => true, 'status' => 'paid', 'booking' => $this->slotBooking($booking)]);
        }

        try {
            $link = $this->razorpay->paymentLinkStatus($data['linkId']);
        } catch (\Throwable $e) {
            // "Don't know yet" — never report unpaid on an unreachable gateway.
            return response()->json(['paid' => false, 'status' => 'unknown', 'error' => 'Could not reach Razorpay'], 200);
        }

        if (! $link['paid']) {
            return response()->json(['paid' => false, 'status' => $link['status']]);
        }

        $paymentId = $link['payment_id'];
        $already = $paymentId !== null && BookingPayment::query()
            ->where('booking_id', $booking->id)->where('reference', $paymentId)->exists();

        if (! $already) {
            $amount = $link['amount_paid'] > 0 ? $link['amount_paid'] : (float) $booking->total_amount;
            $this->ledger->collect($booking, $amount, 'online', null, $paymentId, 'Razorpay payment link');
            $booking->refresh();
            // The money is real now, so the ticket may go out.
            BookingNotifier::dispatch($booking);
        }

        return response()->json([
            'paid'    => true,
            'status'  => 'paid',
            'booking' => $this->slotBooking($booking),
        ]);
    }

    /** PATCH /api/partner/bookings/{id}/cancel — cancel a booking on my event/venue. */
    public function cancelBooking(Request $request, string $id): JsonResponse
    {
        $booking = $this->bookings->cancelAsPartner($request->user(), $id);

        return response()->json(['status' => 'cancelled', 'booking' => $this->slotBooking($booking)]);
    }

    /** POST /api/partner/venues/{id}/block — close a date (maintenance/holiday). */
    public function blockDate(Request $request, string $id): JsonResponse
    {
        $partnerId = $request->user()->effectivePartnerId();
        $venue = Venue::query()->where('partner_id', $partnerId)->findOrFail($id);
        $data = $request->validate(['date' => ['required', 'date']]);

        VenueBlockedDate::query()->firstOrCreate([
            'venue_id' => $venue->id,
            'date'     => date('Y-m-d', strtotime((string) $data['date'])),
        ]);

        return response()->json(['status' => 'blocked', 'date' => $data['date']]);
    }

    /** DELETE /api/partner/venues/{id}/block — reopen a previously closed date. */
    public function unblockDate(Request $request, string $id): JsonResponse
    {
        $partnerId = $request->user()->effectivePartnerId();
        $venue = Venue::query()->where('partner_id', $partnerId)->findOrFail($id);
        $data = $request->validate(['date' => ['required', 'date']]);

        VenueBlockedDate::query()->where('venue_id', $venue->id)
            ->whereDate('date', date('Y-m-d', strtotime((string) $data['date'])))->delete();

        return response()->json(['status' => 'unblocked', 'date' => $data['date']]);
    }

    /** GET /api/partner/staff — the owner's desk persons. Owner-only. */
    public function staff(Request $request): JsonResponse
    {
        $ownerId = $request->user()->effectivePartnerId();

        $staff = User::query()->where('parent_partner_id', $ownerId)->orderBy('name')->get()
            ->map(fn (User $u): array => $this->staffRow($u));

        return response()->json([
            'data'        => $staff,
            'permissions' => User::STAFF_PERMISSIONS,
        ]);
    }

    /** POST /api/partner/staff — create a desk person. Owner-only. */
    public function createStaff(Request $request): JsonResponse
    {
        $ownerId = $request->user()->effectivePartnerId();

        $data = $request->validate([
            'name'          => ['required', 'string', 'max:120'],
            'email'         => ['required', 'email', 'max:190', 'unique:users,email'],
            'password'      => ['required', 'string', 'min:6'],
            'permissions'   => ['array'],
            'permissions.*' => ['string', 'in:'.implode(',', User::STAFF_PERMISSIONS)],
        ]);

        $owner = User::query()->find($ownerId);

        $staff = User::query()->create([
            'name'              => $data['name'],
            'email'             => $data['email'],
            'password'          => Hash::make($data['password']),
            'role'              => 'PARTNER',
            'status'            => 'ACTIVE',
            'partner_type'      => $owner?->partner_type,
            'parent_partner_id' => $ownerId,
            'staff_permissions' => $this->cleanPermissions($data['permissions'] ?? []),
        ]);

        return response()->json(['status' => 'ok', 'staff' => $this->staffRow($staff)], 201);
    }

    /** POST /api/partner/staff/{id} — update a desk person's permissions. Owner-only. */
    public function updateStaff(Request $request, string $id): JsonResponse
    {
        $ownerId = $request->user()->effectivePartnerId();
        $staff = User::query()->where('parent_partner_id', $ownerId)->findOrFail($id);

        $data = $request->validate([
            'permissions'   => ['array'],
            'permissions.*' => ['string', 'in:'.implode(',', User::STAFF_PERMISSIONS)],
        ]);

        $staff->staff_permissions = $this->cleanPermissions($data['permissions'] ?? []);
        $staff->save();

        return response()->json(['status' => 'ok', 'staff' => $this->staffRow($staff)]);
    }

    /** DELETE /api/partner/staff/{id} — remove a desk person. Owner-only. */
    public function deleteStaff(Request $request, string $id): JsonResponse
    {
        $ownerId = $request->user()->effectivePartnerId();
        User::query()->where('parent_partner_id', $ownerId)->where('id', $id)->delete();

        return response()->json(['status' => 'deleted']);
    }

    /** @param array<int, string> $perms @return array<int, string> */
    private function cleanPermissions(array $perms): array
    {
        return array_values(array_intersect($perms, User::STAFF_PERMISSIONS));
    }

    /** @return array<string, mixed> */
    private function staffRow(User $u): array
    {
        return [
            'id'          => $u->id,
            'name'        => $u->name,
            'email'       => $u->email,
            'permissions' => $u->staff_permissions ?? [],
        ];
    }

    /**
     * GET /api/partner/reports/bookings?from=&to=&format=csv|json
     * A downloadable booking report across the partner's events + venues.
     */
    public function bookingsReport(Request $request)
    {
        $partnerId = $request->user()->effectivePartnerId();
        $data = $request->validate([
            'from'   => ['nullable', 'date'],
            'to'     => ['nullable', 'date'],
            'format' => ['nullable', 'in:csv,json'],
        ]);

        $from = isset($data['from']) ? date('Y-m-d', strtotime($data['from'])) : now()->subDays(30)->toDateString();
        $to = isset($data['to']) ? date('Y-m-d', strtotime($data['to'])) : now()->toDateString();

        if (($data['format'] ?? 'csv') === 'json') {
            return response()->json([
                'from'    => $from,
                'to'      => $to,
                'headers' => BookingReport::headers(),
                'rows'    => BookingReport::rows($partnerId, $from, $to),
            ]);
        }

        $csv = BookingReport::csv($partnerId, $from, $to);
        $filename = "bookings_{$from}_to_{$to}.csv";

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /** GET /api/partner/packages — the offers this venue sells, plus who's on one. */
    public function packages(Request $request): JsonResponse
    {
        $partnerId = $request->user()->effectivePartnerId();

        $packages = VenuePackage::query()
            ->where('partner_id', $partnerId)
            ->orderByDesc('is_active')->orderBy('price')
            ->get()
            ->map(fn (VenuePackage $p): array => [
                'id'            => $p->id,
                'name'          => $p->name,
                'price'         => $p->price,
                'sessions'      => $p->sessions,
                'per_session'   => $p->perSession(),
                'validity_days' => $p->validity_days,
                'is_active'     => $p->is_active,
            ]);

        // Active holders — the desk's "who's on a pass" list.
        $holders = CustomerPackage::query()
            ->where('partner_id', $partnerId)
            ->with('package:id,name')
            ->withCount('redemptions')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (CustomerPackage $c): array => $this->holderRow($c))
            ->filter(fn (array $r): bool => $r['remaining'] > 0)
            ->values();

        return response()->json(['data' => $packages, 'holders' => $holders]);
    }

    /** POST /api/partner/packages — create or update an offer. */
    public function savePackage(Request $request, ?string $id = null): JsonResponse
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:120'],
            'price'        => ['required', 'integer', 'min:1'],
            'sessions'     => ['required', 'integer', 'min:1', 'max:500'],
            'validityDays' => ['nullable', 'integer', 'min:1', 'max:1095'],
            'venueId'      => ['nullable', 'integer'],
            'isActive'     => ['nullable', 'boolean'],
        ]);

        $partnerId = $request->user()->effectivePartnerId();

        // A venue-scoped package must belong to this partner, or one tenant could
        // attach an offer to another tenant's venue.
        $venueId = null;
        if (! empty($data['venueId'])) {
            $venueId = (int) Venue::query()->where('partner_id', $partnerId)
                ->findOrFail($data['venueId'])->id;
        }

        $attrs = [
            'partner_id'    => $partnerId,
            'venue_id'      => $venueId,
            'name'          => $data['name'],
            'price'         => $data['price'],
            'sessions'      => $data['sessions'],
            'validity_days' => $data['validityDays'] ?? null,
            'is_active'     => $data['isActive'] ?? true,
        ];

        $package = $id === null
            ? VenuePackage::query()->create($attrs)
            : tap(VenuePackage::query()->where('partner_id', $partnerId)->findOrFail($id))->update($attrs);

        return response()->json(['status' => 'ok', 'id' => $package->id], $id === null ? 201 : 200);
    }

    /**
     * POST /api/partner/packages/{id}/sell — sell a package to a customer.
     *
     * Money is recorded the same way a walk-in's is, so package sales show up in
     * the same takings the desk closes out against.
     */
    public function sellPackage(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'customerPhone' => ['required', 'string', 'max:20'],
            'customerName'  => ['nullable', 'string', 'max:120'],
            'paymentMethod' => ['nullable', 'string', 'in:cash,upi,card'],
        ]);

        $partnerId = $request->user()->effectivePartnerId();
        $package = VenuePackage::query()->where('partner_id', $partnerId)->findOrFail($id);

        $phone = $this->digits($data['customerPhone']);

        if ($phone === null) {
            return response()->json(['error' => 'A valid phone number is required.'], 422);
        }

        $sold = CustomerPackage::query()->create([
            'venue_package_id' => $package->id,
            'partner_id'       => $partnerId,
            'customer_phone'   => $phone,
            'customer_name'    => $data['customerName'] ?? null,
            'sessions_total'   => $package->sessions,
            'amount_paid'      => $package->price,
            'payment_method'   => $data['paymentMethod'] ?? 'cash',
            'expires_at'       => $package->validity_days !== null
                ? now()->addDays($package->validity_days)
                : null,
        ]);

        return response()->json([
            'status' => 'ok',
            'holder' => $this->holderRow($sold->fresh(['package'])->loadCount('redemptions')),
        ], 201);
    }

    /**
     * GET /api/partner/packages/holder?phone= — what this number has left.
     * Drives the walk-in sheet's "use a session" option.
     */
    public function packageHolder(Request $request): JsonResponse
    {
        $partnerId = $request->user()->effectivePartnerId();
        $phone = $this->digits((string) $request->query('phone', ''));

        if ($phone === null) {
            return response()->json(['data' => []]);
        }

        $rows = CustomerPackage::query()
            ->where('partner_id', $partnerId)
            ->where('customer_phone', $phone)
            ->with('package:id,name')
            ->withCount('redemptions')
            ->get()
            ->map(fn (CustomerPackage $c): array => $this->holderRow($c))
            ->filter(fn (array $r): bool => $r['usable'])
            ->values();

        return response()->json(['data' => $rows]);
    }

    /** @return array<string, mixed> */
    private function holderRow(CustomerPackage $c): array
    {
        $used = (int) ($c->redemptions_count ?? $c->used());

        return [
            'id'         => $c->id,
            'name'       => $c->customer_name ?: 'Guest',
            'phone'      => $c->customer_phone,
            'package'    => $c->package?->name ?? 'Package',
            'total'      => $c->sessions_total,
            'used'       => $used,
            'remaining'  => max($c->sessions_total - $used, 0),
            'expires_at' => optional($c->expires_at)->toDateString(),
            'expired'    => $c->isExpired(),
            'usable'     => max($c->sessions_total - $used, 0) > 0 && ! $c->isExpired(),
        ];
    }

    /**
     * GET /api/partner/customers — who books here, how often, and what they spend.
     *
     * Identity is keyed on PHONE, never on user_id. A desk walk-in carries the
     * PARTNER's user_id (the desk created the row), so grouping by user would
     * collapse every walk-in into one customer — the venue owner, listed as its
     * own best customer. Online bookings use the booker's account phone; walk-ins
     * use the number the desk typed.
     *
     * Rows without any phone can't be told apart from each other, so they're
     * counted in the totals but not listed as named customers.
     */
    public function customers(Request $request): JsonResponse
    {
        $partnerId = $request->user()->effectivePartnerId();
        $q = trim((string) $request->query('q', ''));

        $rows = app(PartnerSettlement::class)->bookings($partnerId)
            ->whereIn(DB::raw('lower(status)'), self::PAID_STATUSES)
            ->with('user:id,name,phone')
            ->orderByDesc('created_at')
            ->limit(5000)
            ->get(['id', 'user_id', 'channel', 'guest_name', 'guest_phone', 'total_amount', 'slot_date', 'created_at']);

        $byPhone = [];
        $anonymous = 0;

        foreach ($rows as $b) {
            $isDesk = strtolower((string) $b->channel) === 'offline';

            $phone = $this->digits($isDesk ? $b->guest_phone : ($b->user->phone ?? $b->guest_phone));
            $name = trim((string) ($isDesk
                ? ($b->guest_name ?: '')
                : ($b->user->name ?? $b->guest_name ?? ''))) ?: 'Guest';

            if ($phone === null) {
                $anonymous++;
                continue;
            }

            $when = ($b->slot_date ?? $b->created_at);

            if (! isset($byPhone[$phone])) {
                $byPhone[$phone] = [
                    'name' => $name,
                    'phone' => $phone,
                    'bookings' => 0,
                    'spent' => 0.0,
                    'last_visit' => null,
                    'first_visit' => null,
                ];
            }

            $c = &$byPhone[$phone];
            $c['bookings']++;
            $c['spent'] += (float) $b->total_amount;
            // Keep the most recently used name — people get re-typed at the desk.
            if ($c['last_visit'] === null || ($when !== null && $when->gt($c['last_visit']))) {
                $c['last_visit'] = $when;
                $c['name'] = $name;
            }
            if ($c['first_visit'] === null || ($when !== null && $when->lt($c['first_visit']))) {
                $c['first_visit'] = $when;
            }
            unset($c);
        }

        $all = array_values($byPhone);

        if ($q !== '') {
            $needle = mb_strtolower($q);
            $all = array_values(array_filter($all, fn (array $c): bool => str_contains(mb_strtolower($c['name']), $needle)
                || str_contains($c['phone'], preg_replace('/[^0-9]/', '', $q) ?: $needle)));
        }

        // Best customers first — the list is for "who should I look after".
        usort($all, fn (array $x, array $y): int => $y['spent'] <=> $x['spent']);

        $repeat = count(array_filter($all, fn (array $c): bool => $c['bookings'] > 1));

        return response()->json([
            'summary' => [
                'total'     => count($all),
                'repeat'    => $repeat,
                'anonymous' => $anonymous,
            ],
            'data' => array_map(fn (array $c): array => [
                'name'       => $c['name'],
                'phone'      => $c['phone'],
                'bookings'   => $c['bookings'],
                'spent'      => round($c['spent'], 2),
                'is_repeat'  => $c['bookings'] > 1,
                'last_visit' => optional($c['last_visit'])->toDateString(),
            ], array_slice($all, 0, 300)),
        ]);
    }

    /** Digits-only phone, or null when there aren't enough to identify anyone. */
    private function digits(?string $raw): ?string
    {
        $d = preg_replace('/[^0-9]/', '', (string) $raw);

        return ($d !== null && strlen($d) >= 10) ? substr($d, -10) : null;
    }

    /**
     * GET /api/partner/payouts — the settlement home: balance, where the money is
     * sent, and the history of settlements.
     *
     * The arithmetic comes from {@see PartnerSettlement} — the same service the web
     * Payouts page and /control settle against, so a partner can never see one
     * "available" figure in the app and a different one on the web.
     */
    public function payouts(Request $request): JsonResponse
    {
        $partnerId = $request->user()->effectivePartnerId();
        $settlement = app(PartnerSettlement::class);
        $s = $settlement->summary($partnerId);

        $account = PartnerPayoutAccount::query()->where('partner_id', $partnerId)->first();

        $batches = PayoutBatch::query()
            ->where('partner_id', $partnerId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (PayoutBatch $b): array => [
                'id'        => $b->id,
                'amount'    => round((float) $b->amount, 2),
                'status'    => $b->status,
                'is_paid'   => $b->isPaid(),
                'method'    => $b->method,
                'reference' => $b->reference,
                'period'    => $this->batchPeriod($b),
                'date'      => optional($b->processed_at ?? $b->created_at)->toDateString(),
            ]);

        return response()->json([
            'balance' => [
                'available' => round($s['available'], 2),
                'in_flight' => round($s['inFlight'], 2),
                'settled'   => round($s['settled'], 2),
                'collected' => round($s['collected'], 2),
            ],
            'account' => $account === null ? null : [
                'method'         => $account->method,
                'account_holder' => $account->account_holder,
                'bank_name'      => $account->bank_name,
                // The destination itself is masked — a stolen phone must not be able
                // to read back where the venue's money goes. Changing it requires
                // re-entering it in full, same rule as the web page.
                'masked'         => $this->maskDestination($account),
                'verified'       => $account->verified_at !== null,
            ],
            'batches' => $batches,
        ]);
    }

    /**
     * POST /api/partner/payouts/account — set where settlements are sent.
     *
     * Saving always clears `verified_at`: a destination that changed is a
     * destination nobody has checked yet.
     */
    public function savePayoutAccount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'method'        => ['required', 'string', 'in:bank,upi'],
            'accountHolder' => ['required', 'string', 'max:120'],
            'bankName'      => ['nullable', 'string', 'max:120'],
            'accountNumber' => ['nullable', 'string', 'max:34'],
            'ifsc'          => ['nullable', 'string', 'max:15'],
            'upiVpa'        => ['nullable', 'string', 'max:120'],
        ]);

        // Each method needs its own destination, or the row is a promise to pay
        // money to nowhere.
        if ($data['method'] === 'bank') {
            $request->validate([
                'accountNumber' => ['required', 'string', 'min:6', 'max:34'],
                'ifsc'          => ['required', 'string', 'min:6', 'max:15'],
            ]);
        } else {
            $request->validate(['upiVpa' => ['required', 'string', 'min:3', 'max:120']]);
        }

        $partnerId = $request->user()->effectivePartnerId();

        $account = PartnerPayoutAccount::updateOrCreate(
            ['partner_id' => $partnerId],
            [
                'method'         => $data['method'],
                'account_holder' => $data['accountHolder'],
                'bank_name'      => $data['method'] === 'bank' ? ($data['bankName'] ?? null) : null,
                'account_number' => $data['method'] === 'bank' ? $data['accountNumber'] : null,
                'ifsc_code'      => $data['method'] === 'bank' ? strtoupper((string) $data['ifsc']) : null,
                'upi_vpa'        => $data['method'] === 'upi' ? $data['upiVpa'] : null,
                'verified_at'    => null,
            ],
        );

        return response()->json([
            'status'  => 'ok',
            'account' => [
                'method'         => $account->method,
                'account_holder' => $account->account_holder,
                'bank_name'      => $account->bank_name,
                'masked'         => $this->maskDestination($account),
                'verified'       => false,
            ],
        ]);
    }

    /** "HDFC •••• 4821" / "venue@upi" with the handle kept readable. */
    private function maskDestination(PartnerPayoutAccount $a): string
    {
        if ($a->method === 'upi') {
            $vpa = (string) $a->upi_vpa;
            $at = strpos($vpa, '@');

            if ($at === false || $at <= 2) {
                return $vpa;
            }

            return substr($vpa, 0, 2).str_repeat('•', max($at - 2, 1)).substr($vpa, $at);
        }

        $num = preg_replace('/\s+/', '', (string) $a->account_number);
        $tail = strlen((string) $num) > 4 ? substr((string) $num, -4) : (string) $num;

        return trim(((string) $a->bank_name).' •••• '.$tail);
    }

    private function batchPeriod(PayoutBatch $b): ?string
    {
        if ($b->period_start === null || $b->period_end === null) {
            return null;
        }

        return $b->period_start->format('d M').' – '.$b->period_end->format('d M');
    }

    /** GET /api/partner/venues/{id}/courts — courts with their base + peak pricing. */
    public function venueCourts(Request $request, string $id): JsonResponse
    {
        $partnerId = $request->user()->effectivePartnerId();
        $venue = Venue::query()->where('partner_id', $partnerId)->findOrFail($id);

        $courts = VenueCourt::query()->where('venue_id', $venue->id)
            ->orderBy('sort_order')->get()
            ->map(fn (VenueCourt $c): array => $this->courtRow($c, (int) ($venue->price ?? 0)));

        return response()->json([
            'venue_price' => (int) ($venue->price ?? 0),
            'data'        => $courts,
        ]);
    }

    /**
     * POST /api/partner/venues/{id}/courts/{courtId} — set a court's base and peak pricing.
     *
     * Peak needs a price AND a schedule to mean anything ({@see VenueCourt::isPeak()}), so
     * clearing either one turns peak off rather than leaving a rate that silently never
     * applies — or worse, one that applies to every hour.
     */
    public function saveCourt(Request $request, string $id, string $courtId): JsonResponse
    {
        $data = $request->validate([
            'price'     => ['nullable', 'integer', 'min:0'],
            'peakPrice' => ['nullable', 'integer', 'min:0'],
            'peakDays'  => ['nullable', 'array'],
            'peakDays.*' => ['string', 'max:12'],
            'peakStart' => ['nullable', 'string', 'max:5'],
            'peakEnd'   => ['nullable', 'string', 'max:5'],
        ]);

        $partnerId = $request->user()->effectivePartnerId();
        $venue = Venue::query()->where('partner_id', $partnerId)->findOrFail($id);
        $court = VenueCourt::query()->where('venue_id', $venue->id)->findOrFail($courtId);

        if (array_key_exists('price', $data)) {
            $court->price = $data['price'];
        }

        $peakPrice = $data['peakPrice'] ?? null;
        // Normalise to the 3-letter capitalised form isPeak() compares against.
        $days = array_values(array_filter(array_map(
            static fn ($d): string => ucfirst(strtolower(substr(trim((string) $d), 0, 3))),
            $data['peakDays'] ?? [],
        )));
        $start = trim((string) ($data['peakStart'] ?? '')) ?: null;
        $end = trim((string) ($data['peakEnd'] ?? '')) ?: null;

        $hasSchedule = $days !== [] || ($start !== null && $end !== null);

        if ($peakPrice === null || $peakPrice <= 0 || ! $hasSchedule) {
            $court->peak_price = null;
            $court->peak_days = null;
            $court->peak_start = null;
            $court->peak_end = null;
        } else {
            $court->peak_price = $peakPrice;
            $court->peak_days = $days === [] ? null : $days;
            $court->peak_start = $start;
            $court->peak_end = $end;
        }

        $court->save();

        return response()->json([
            'status' => 'ok',
            'court'  => $this->courtRow($court, (int) ($venue->price ?? 0)),
        ]);
    }

    /** @return array<string, mixed> */
    private function courtRow(VenueCourt $c, int $venuePrice): array
    {
        return [
            'id'          => $c->id,
            'name'        => $c->name,
            'sports'      => $c->sportsList(),
            'is_active'   => (bool) $c->is_active,
            'price'       => (int) ($c->price ?? $venuePrice),
            'has_own_price' => $c->price !== null,
            'peak_price'  => $c->peak_price !== null ? (int) $c->peak_price : null,
            'peak_days'   => $c->peakDaysList(),
            'peak_start'  => $c->peak_start,
            'peak_end'    => $c->peak_end,
        ];
    }

    /** GET /api/partner/venues/{id}/slots — the venue's price/slot rows. */
    public function venueSlots(Request $request, string $id): JsonResponse
    {
        $partnerId = $request->user()->effectivePartnerId();
        $venue = Venue::query()->where('partner_id', $partnerId)->findOrFail($id);

        $slots = VenueSlot::query()->where('venue_id', $venue->id)->orderBy('sort_order')->get()
            ->map(fn (VenueSlot $s): array => $this->slotRow($s));

        return response()->json(['data' => $slots]);
    }

    /**
     * POST /api/partner/venues/{id}/slots            — create a slot
     * POST /api/partner/venues/{id}/slots/{slotId}   — update a slot
     */
    public function saveSlot(Request $request, string $id, ?string $slotId = null): JsonResponse
    {
        $partnerId = $request->user()->effectivePartnerId();
        $venue = Venue::query()->where('partner_id', $partnerId)->findOrFail($id);

        $data = $request->validate([
            'day'      => ['nullable', 'string', 'max:40'],
            'time'     => ['required', 'string', 'max:60'],
            'price'    => ['nullable', 'numeric', 'min:0'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'isOpen'   => ['nullable', 'boolean'],
        ]);

        $attrs = [
            'time'         => $data['time'],
            'price'        => $data['price'] ?? 0,
            'capacity'     => $data['capacity'] ?? 1,
            'is_available' => $data['isOpen'] ?? true,
        ];

        // `day` is NOT NULL — only overwrite it when supplied, so an update that
        // omits it keeps the existing value.
        if (array_key_exists('day', $data) && $data['day'] !== null && $data['day'] !== '') {
            $attrs['day'] = $data['day'];
        }

        if ($slotId !== null) {
            $slot = VenueSlot::query()->where('venue_id', $venue->id)->findOrFail($slotId);
            $slot->update($attrs);
        } else {
            $attrs['day'] = $attrs['day'] ?? 'Every day';
            $attrs['venue_id'] = $venue->id;
            $attrs['sort_order'] = (int) VenueSlot::query()->where('venue_id', $venue->id)->max('sort_order') + 1;
            $slot = VenueSlot::query()->create($attrs);
        }

        return response()->json(['status' => 'ok', 'slot' => $this->slotRow($slot)]);
    }

    /** DELETE /api/partner/venues/{id}/slots/{slotId} — remove a slot. */
    public function deleteSlot(Request $request, string $id, string $slotId): JsonResponse
    {
        $partnerId = $request->user()->effectivePartnerId();
        $venue = Venue::query()->where('partner_id', $partnerId)->findOrFail($id);

        VenueSlot::query()->where('venue_id', $venue->id)->where('id', $slotId)->delete();

        return response()->json(['status' => 'deleted']);
    }

    /** @return array<string, mixed> */
    private function slotRow(VenueSlot $s): array
    {
        return [
            'id'       => $s->id,
            'day'      => $s->day,
            'time'     => $s->time,
            'price'    => (float) $s->price,
            'capacity' => (int) $s->capacity,
            'is_open'  => (bool) $s->is_available,
        ];
    }

    /** @return array<string, mixed> */
    private function slotBooking(Booking $x): array
    {
        return [
            'id'          => $x->id,
            'customer'    => $x->guest_name ?: ($x->user?->name ?? 'Guest'),
            'phone'       => $x->guest_phone,
            'channel'     => $x->channel ?? 'online',
            'status'      => $x->status,
            'checked_in'  => (int) $x->checked_in_count,
            'ticket_code' => $x->ticket_code,
            'amount'      => round((float) $x->total_amount, 2),
            'amount_paid' => round((float) $x->amount_paid, 2),
            // unpaid | part | paid — drives the desk's "who still owes" chase list.
            'payment_status' => $x->payment_status,
        ];
    }

    /**
     * GET /api/partner/events/{id}/analytics — per-event performance, mirroring
     * the web host-analytics page (same real booking data, same math).
     */
    public function eventAnalytics(Request $request, string $id): JsonResponse
    {
        $partnerId = $request->user()->effectivePartnerId();
        $event = Event::query()->where('partner_id', $partnerId)->findOrFail($id);

        $paid = fn () => Booking::query()->where('event_id', $event->id)
            ->whereIn(DB::raw('lower(status)'), self::PAID_STATUSES);

        $revenue   = (float) $paid()->sum('total_amount');
        $attendees = (int) $paid()->sum('quantity');
        $orders    = (int) $paid()->count();
        $checkedIn = (int) $paid()->sum('checked_in_count');

        $capacity  = max((int) $event->total_slots, 0);
        $available = max((int) $event->available_slots, 0);
        $sold      = max($capacity - $available, 0);
        $views     = max((int) $event->views, 0);

        // 14-day sales series (revenue + tickets per day), gaps filled with 0.
        $start = now()->startOfDay()->subDays(13);
        $rows = (clone $paid())->where('created_at', '>=', $start)
            ->get(['total_amount', 'quantity', 'created_at']);
        $byDay = [];
        foreach ($rows as $row) {
            $k = $row->created_at->format('Y-m-d');
            $byDay[$k]['rev'] = ($byDay[$k]['rev'] ?? 0) + (float) $row->total_amount;
            $byDay[$k]['qty'] = ($byDay[$k]['qty'] ?? 0) + (int) $row->quantity;
        }
        $series = [];
        for ($i = 0; $i < 14; $i++) {
            $day = $start->copy()->addDays($i);
            $k = $day->format('Y-m-d');
            $series[] = [
                'label'   => $day->format('d M'),
                'revenue' => round($byDay[$k]['rev'] ?? 0, 2),
                'tickets' => (int) ($byDay[$k]['qty'] ?? 0),
            ];
        }

        // Revenue split by ticket tier.
        $names = $event->ticketTypes()->pluck('name', 'id');
        $byTier = (clone $paid())->groupBy('ticket_type_id')->get([
            'ticket_type_id',
            DB::raw('COUNT(*) as orders'),
            DB::raw('SUM(quantity) as tickets'),
            DB::raw('SUM(total_amount) as revenue'),
        ])->map(fn ($r): array => [
            'name'    => $r->ticket_type_id === null ? 'Untiered' : ($names[$r->ticket_type_id] ?? 'Deleted tier'),
            'orders'  => (int) $r->orders,
            'tickets' => (int) $r->tickets,
            'revenue' => round((float) $r->revenue, 2),
            'pct'     => $revenue > 0 ? (int) round(((float) $r->revenue) / $revenue * 100) : 0,
        ])->values();

        return response()->json([
            'title' => $event->title,
            'stats' => [
                'revenue'         => round($revenue, 2),
                'orders'          => $orders,
                'attendees'       => $attendees,
                'avg_per_attendee' => $attendees > 0 ? round($revenue / $attendees, 2) : 0.0,
                'checked_in'      => $checkedIn,
                'show_up_pct'     => $attendees > 0 ? (int) round($checkedIn / $attendees * 100) : 0,
                'no_shows'        => max($attendees - $checkedIn, 0),
                'fill_pct'        => $capacity > 0 ? (int) round($sold / $capacity * 100) : 0,
                'seats_left'      => $available,
                'views'           => $views,
                'conversion_pct'  => $views > 0 ? round($orders / $views * 100, 2) : 0.0,
            ],
            'sales'    => $series,
            'by_tier'  => $byTier,
        ]);
    }

    /**
     * GET /api/partner/venues/{id}/analytics — per-venue performance, mirroring
     * the web venue-owner analytics page.
     */
    public function venueAnalytics(Request $request, string $id): JsonResponse
    {
        $partnerId = $request->user()->effectivePartnerId();
        $venue = Venue::query()->where('partner_id', $partnerId)->findOrFail($id);

        $paid = fn () => Booking::query()->where('booking_type', 'venue')
            ->where('venue_id', $venue->id)
            ->whereIn(DB::raw('lower(status)'), self::PAID_STATUSES);

        $revenue   = (float) $paid()->sum('total_amount');
        $bookings  = (int) $paid()->count();
        $checkedIn = (int) $paid()->sum('checked_in_count');

        $slotsOffered   = (int) $venue->slots()->count();
        $weeklyCapacity = (int) $venue->slots()->sum('capacity');
        $bookings30d = (int) Booking::query()->where('booking_type', 'venue')
            ->where('venue_id', $venue->id)
            ->where('created_at', '>=', now()->subDays(30))->count();
        $capacity30d = (int) round($weeklyCapacity * 4.3);
        $utilization = $capacity30d > 0 ? min(100, (int) round($bookings30d / $capacity30d * 100)) : 0;

        $upcoming = (int) Booking::query()->where('booking_type', 'venue')
            ->where('venue_id', $venue->id)
            ->whereIn(DB::raw('lower(status)'), self::PAID_STATUSES)
            ->whereDate('slot_date', '>=', now()->toDateString())->count();

        $distinctUsers = (int) $paid()->distinct('user_id')->count('user_id');
        $repeatUsers = (int) Booking::query()->where('booking_type', 'venue')
            ->where('venue_id', $venue->id)
            ->whereIn(DB::raw('lower(status)'), self::PAID_STATUSES)
            ->select('user_id')->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')->get()->count();

        // 14-day bookings series.
        $start = now()->startOfDay()->subDays(13);
        $rows = (clone $paid())->where('created_at', '>=', $start)
            ->get(['total_amount', 'created_at']);
        $byDay = [];
        foreach ($rows as $row) {
            $k = $row->created_at->format('Y-m-d');
            $byDay[$k]['rev'] = ($byDay[$k]['rev'] ?? 0) + (float) $row->total_amount;
            $byDay[$k]['cnt'] = ($byDay[$k]['cnt'] ?? 0) + 1;
        }
        $series = [];
        for ($i = 0; $i < 14; $i++) {
            $day = $start->copy()->addDays($i);
            $k = $day->format('Y-m-d');
            $series[] = [
                'label'    => $day->format('d M'),
                'revenue'  => round($byDay[$k]['rev'] ?? 0, 2),
                'bookings' => (int) ($byDay[$k]['cnt'] ?? 0),
            ];
        }

        return response()->json([
            'name'  => $venue->name,
            'stats' => [
                'revenue'         => round($revenue, 2),
                'bookings'        => $bookings,
                'avg_booking'     => $bookings > 0 ? round($revenue / $bookings, 2) : 0.0,
                'utilization_pct' => $utilization,
                'bookings_30d'    => $bookings30d,
                'capacity_30d'    => $capacity30d,
                'upcoming'        => $upcoming,
                'checked_in'      => $checkedIn,
                'show_up_pct'     => $bookings > 0 ? (int) round($checkedIn / $bookings * 100) : 0,
                'repeat_pct'      => $distinctUsers > 0 ? (int) round($repeatUsers / $distinctUsers * 100) : 0,
                'slots_offered'   => $slotsOffered,
                'rating'          => $venue->rating ? round((float) $venue->rating, 1) : null,
                'reviews'         => (int) ($venue->reviews_count ?? 0),
            ],
            'sales' => $series,
        ]);
    }

    /** @return array<string, mixed> */
    private function eventSummary(Event $e): array
    {
        $paid = Booking::query()->where('event_id', $e->id)->where('status', self::PAID);

        return [
            'id'           => $e->id,
            'title'        => $e->title,
            'category'     => $e->category,
            'date'         => optional($e->date)->toDateString() ?? $e->date,
            'time'         => $e->time,
            'status'       => $e->status,
            'total_slots'  => (int) $e->total_slots,
            'seats_left'   => (int) $e->available_slots,
            'tickets_sold' => (int) (clone $paid)->sum('quantity'),
            'revenue'      => round((float) (clone $paid)->sum('total_amount'), 2),
        ];
    }

    /** @return array<string, mixed> */
    private function venueSummary(Venue $v): array
    {
        $paid = Booking::query()->where('venue_id', $v->id)->where('status', self::PAID);

        return [
            'id'       => $v->id,
            'name'     => $v->name,
            'location' => $v->location ?? null,
            'bookings' => (clone $paid)->count(),
            'revenue'  => round((float) (clone $paid)->sum('total_amount'), 2),
        ];
    }

    /** @return array<string, mixed> */
    private function bookingSummary(Booking $b): array
    {
        return [
            'id'           => $b->id,
            'ticket_code'  => $b->ticket_code,
            'quantity'     => (int) $b->quantity,
            'amount'       => round((float) $b->total_amount, 2),
            'status'       => $b->status,
            'checked_in'   => (int) $b->checked_in_count,
            'slot_date'    => optional($b->slot_date)->toDateString() ?? $b->slot_date,
            'slot_label'   => $b->slot_label,
            'created_at'   => optional($b->created_at)->toIso8601String(),
        ];
    }
}
