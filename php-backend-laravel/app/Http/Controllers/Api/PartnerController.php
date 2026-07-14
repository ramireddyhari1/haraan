<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBlockedDate;
use App\Models\VenueSlot;
use App\Services\BookingService;
use App\Support\BookingReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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

    public function __construct(private readonly BookingService $bookings)
    {
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

        $bookings = Booking::query()
            ->where('booking_type', 'venue')->where('venue_id', $venue->id)
            ->whereDate('slot_date', $date)->where('status', self::PAID)
            ->with('user:id,name')->get();
        $bySlot = $bookings->groupBy('venue_slot_id');

        $rows = $slots->map(function (VenueSlot $s) use ($bySlot): array {
            $b = $bySlot->get($s->id) ?? collect();
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
            ];
        });

        return response()->json([
            'date'       => $date,
            'venue'      => ['id' => $venue->id, 'name' => $venue->name],
            'is_blocked' => $isBlocked,
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
        ]);

        $booking = $this->bookings->createOfflineVenueBooking(
            $request->user(),
            (int) $id,
            isset($data['slotId']) ? (int) $data['slotId'] : null,
            (string) $data['date'],
            $data['guestName'] ?? null,
            $data['guestPhone'] ?? null,
            isset($data['courtId']) ? (int) $data['courtId'] : null,
            isset($data['duration']) ? (int) $data['duration'] : 1,
        );

        return response()->json(['status' => 'ok', 'booking' => $this->slotBooking($booking)], 201);
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
