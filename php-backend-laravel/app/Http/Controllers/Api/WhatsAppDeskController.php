<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppInternalNote;
use App\Models\WhatsAppPaymentLink;
use App\Models\WhatsAppQuickReply;
use App\Services\BookingService;
use App\Services\WhatsAppDeskService;
use App\Services\WhatsAppIntentEngine;
use App\Services\WhatsAppReservationService;
use App\Support\BusinessClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * The partner app's WhatsApp Desk: one venue's customer chats, and the hold → pay →
 * confirm loop that turns a chat into a booking. Every route is scoped to the
 * caller's own branches and gated on the `bookings` permission (see routes/api.php).
 */
final class WhatsAppDeskController extends Controller
{
    public function __construct(
        private readonly WhatsAppDeskService $deskService,
        private readonly WhatsAppReservationService $reservationService,
        private readonly WhatsAppIntentEngine $intentEngine,
    ) {}

    /**
     * The venue at {id}, but only if it is one of the caller's own branches (staff with a
     * venue assignment see only those). Anything else is a 404 rather than a 403, so a
     * partner probing ids learns nothing about venues that aren't theirs.
     */
    private function branch(Request $request, int $id): Venue
    {
        return $request->user()->branches()->findOrFail($id);
    }

    private function conversation(Venue $venue, int $convId): WhatsAppConversation
    {
        return WhatsAppConversation::query()->where('venue_id', $venue->id)->findOrFail($convId);
    }

    public function dashboard(Request $request, int $id): JsonResponse
    {
        $venue = $this->branch($request, $id);

        return response()->json(['status' => 'success', 'data' => $this->deskService->getDashboardMetrics($venue)]);
    }

    public function index(Request $request, int $id): JsonResponse
    {
        $venue = $this->branch($request, $id);

        $paginator = $this->deskService->getConversations(
            $venue,
            $request->query('status'),
            $request->query('q'),
            max(1, (int) $request->query('page', 1)),
        );

        return response()->json([
            'status' => 'success',
            'data'   => $paginator->items(),
            'meta'   => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /** One chat: its latest messages, and the hold (if any) with its clock. */
    public function show(Request $request, int $id, int $convId): JsonResponse
    {
        $venue = $this->branch($request, $id);
        $conversation = WhatsAppConversation::query()
            ->where('venue_id', $venue->id)
            ->with(['assignedStaff:id,name', 'activeBooking.venueCourt', 'latestIntent.resolvedCourt'])
            ->findOrFail($convId);

        if ($conversation->unread_count > 0) {
            $conversation->update(['unread_count' => 0]);
        }

        // The latest 100, oldest first — not the first 100 the chat ever had.
        $messages = $conversation->messages()->reorder()->latest('id')->take(100)->get()->reverse()->values();
        $notes = $conversation->notes()->with('author:id,name')->get();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'conversation'             => $conversation,
                'messages'                 => $messages,
                'notes'                    => $notes,
                'hold'                     => $this->holdPayload($conversation->activeBooking),
                'active_hold_seconds_left' => $this->secondsLeft($conversation->activeBooking),
                'hold_total_seconds'       => WhatsAppReservationService::holdMinutes() * 60,
                'window_seconds_left'      => $conversation->secondsRemainingInWindow(),
                'is_window_active'         => $conversation->isWindowActive(),
            ],
        ]);
    }

    public function sendMessage(Request $request, int $id, int $convId): JsonResponse
    {
        $venue = $this->branch($request, $id);
        $conversation = $this->conversation($venue, $convId);

        $validated = $request->validate([
            'body'      => 'required|string|max:4000',
            'media_url' => 'nullable|url|max:500',
        ]);

        $message = $this->deskService->sendOutboundMessage($conversation, $validated['body'], $request->user(), $validated['media_url'] ?? null);

        return response()->json(['status' => 'success', 'data' => $message]);
    }

    /** Re-read the chat for a booking — the given text, or the customer's last message. */
    public function extractIntent(Request $request, int $id, int $convId): JsonResponse
    {
        $venue = $this->branch($request, $id);
        $conversation = $this->conversation($venue, $convId);

        $text = trim((string) $request->input('text', ''));
        if ($text === '') {
            $text = (string) $conversation->messages()->reorder()->where('direction', 'inbound')->latest('id')->value('body');
        }

        if ($text === '') {
            throw ValidationException::withMessages(['text' => ['The customer hasn’t sent a message to read yet.']]);
        }

        return response()->json(['status' => 'success', 'data' => $this->intentEngine->analyze($venue, $text, $conversation->customer_name)]);
    }

    /**
     * Hold a court for this chat's customer. The price is the court's own rate for that
     * time, computed by the booking engine — the client doesn't set it.
     */
    public function holdSlot(Request $request, int $id, int $convId): JsonResponse
    {
        $venue = $this->branch($request, $id);
        $conversation = $this->conversation($venue, $convId);

        $validated = $request->validate([
            'court_id'   => 'required|integer',
            'slot_date'  => 'required|date_format:Y-m-d',
            'start_time' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'end_time'   => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
        ]);

        $court = VenueCourt::query()->where('venue_id', $venue->id)->where('is_active', true)->find($validated['court_id']);
        if ($court === null) {
            throw ValidationException::withMessages(['court_id' => ['That court isn’t one of this venue’s active courts.']]);
        }

        $booking = $this->reservationService->holdSlot(
            $venue,
            $conversation,
            $court,
            Carbon::parse($validated['slot_date']),
            $validated['start_time'],
            $validated['end_time'],
            $request->user(),
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Slot held for '.WhatsAppReservationService::holdMinutes().' minutes',
            'data'    => $this->holdPayload($booking->load('venueCourt')) + [
                'booking_id'         => $booking->id,
                'reserved_until'     => $booking->reserved_until?->toIso8601String(),
                'seconds_remaining'  => $this->secondsLeft($booking),
                'hold_total_seconds' => WhatsAppReservationService::holdMinutes() * 60,
            ],
        ]);
    }

    public function releaseHold(Request $request, int $id, int $convId): JsonResponse
    {
        $venue = $this->branch($request, $id);
        $conversation = $this->conversation($venue, $convId);

        $outcome = $this->reservationService->releaseHold($venue, $conversation, $request->user());

        return response()->json([
            'status'  => 'success',
            'outcome' => $outcome,
            'message' => $outcome === 'paid'
                ? 'The customer had already paid — the booking is confirmed.'
                : 'Hold released',
        ]);
    }

    /** Send the held booking's Razorpay payment link in the chat. */
    public function sendPaymentLink(Request $request, int $id, int $convId): JsonResponse
    {
        $venue = $this->branch($request, $id);
        $conversation = $this->conversation($venue, $convId);

        $link = $this->reservationService->sendPaymentLink($venue, $conversation, $this->activeBooking($venue, $conversation), $request->user());

        return response()->json([
            'status'  => 'success',
            'message' => 'Payment link sent on WhatsApp',
            'data'    => [
                'short_url'          => $link->short_url,
                'amount'             => $link->amount,
                'seconds_remaining'  => $this->secondsLeft($link->booking),
                'hold_total_seconds' => WhatsAppReservationService::holdMinutes() * 60,
            ],
        ]);
    }

    /**
     * Has the customer paid the link yet? Asks Razorpay directly, so the desk sees the
     * payment land without waiting on the webhook (or without one configured at all).
     */
    public function paymentStatus(Request $request, int $id, int $convId): JsonResponse
    {
        $venue = $this->branch($request, $id);
        $conversation = $this->conversation($venue, $convId);

        $bookingId = $conversation->active_booking_id
            ?? WhatsAppPaymentLink::query()->where('conversation_id', $conversation->id)->latest('id')->value('booking_id');

        $booking = $bookingId !== null ? Booking::query()->where('venue_id', $venue->id)->find($bookingId) : null;

        if ($booking === null) {
            return response()->json(['status' => 'success', 'paid' => false, 'state' => 'no_link']);
        }

        if (in_array(strtoupper((string) $booking->status), ['CONFIRMED', 'CHECKED_IN', 'COMPLETED'], true)) {
            return response()->json(['status' => 'success', 'paid' => true, 'state' => 'paid']);
        }

        $paid = $this->reservationService->checkLinks($booking);

        return response()->json([
            'status' => 'success',
            'paid'   => $paid === true,
            'state'  => match ($paid) { true => 'paid', false => 'unpaid', null => 'unknown' },
        ]);
    }

    /** The desk took the money in person. */
    public function markPaid(Request $request, int $id, int $convId): JsonResponse
    {
        $venue = $this->branch($request, $id);
        $conversation = $this->conversation($venue, $convId);
        $method = $request->validate(['method' => 'nullable|in:cash,upi'])['method'] ?? 'cash';

        $booking = $this->reservationService->markPaidManual($venue, $conversation, $this->activeBooking($venue, $conversation), $method, $request->user());

        return response()->json([
            'status'  => 'success',
            'message' => 'Paid and confirmed. The customer has their booking code on WhatsApp.',
            'data'    => [
                'booking_id'  => $booking->id,
                'status'      => 'confirmed',
                'ticket_code' => $booking->ticket_code,
            ],
        ]);
    }

    public function addNote(Request $request, int $id, int $convId): JsonResponse
    {
        $venue = $this->branch($request, $id);
        $conversation = $this->conversation($venue, $convId);

        $validated = $request->validate(['note' => 'required|string|max:1000']);

        $note = WhatsAppInternalNote::query()->create([
            'conversation_id' => $conversation->id,
            'user_id'         => $request->user()->id,
            'note'            => $validated['note'],
        ]);

        return response()->json(['status' => 'success', 'data' => $note->load('author:id,name')]);
    }

    public function assignStaff(Request $request, int $id, int $convId): JsonResponse
    {
        $venue = $this->branch($request, $id);
        $conversation = $this->conversation($venue, $convId);

        $validated = $request->validate(['staff_id' => 'nullable|integer|exists:users,id']);

        // Only someone on this business's own team: the owner or one of their desk staff.
        if (! empty($validated['staff_id'])) {
            $ownerId = (int) $request->user()->effectivePartnerId();
            $onTeam = User::query()
                ->whereKey($validated['staff_id'])
                ->where(fn ($q) => $q->where('id', $ownerId)->orWhere('parent_partner_id', $ownerId))
                ->exists();
            if (! $onTeam) {
                throw ValidationException::withMessages(['staff_id' => ['That person is not on this venue’s team.']]);
            }
        }

        $conversation->update(['assigned_staff_id' => $validated['staff_id'] ?? null]);

        return response()->json(['status' => 'success', 'data' => $conversation->load('assignedStaff:id,name')]);
    }

    /**
     * Canned replies, written in /control → WhatsApp Desk replies, filled in with this
     * venue's real details. A reply that needs a detail the venue hasn't set (no
     * address, no rules) is left out rather than sent with a blank in it.
     * `{{customer_name}}` is left for the app to fill per chat.
     */
    public function quickReplies(Request $request, int $id): JsonResponse
    {
        $venue = $this->branch($request, $id);
        $tokens = $this->venueTokens($venue);

        $replies = WhatsAppQuickReply::query()
            ->where(fn ($q) => $q->whereNull('venue_id')->orWhere('venue_id', $venue->id))
            ->orderByRaw('case when venue_id is null then 1 else 0 end')
            ->orderBy('category')
            ->orderBy('id')
            ->get()
            // A venue's own reply replaces the platform one with the same shortcut.
            ->unique('shortcut')
            ->map(function (WhatsAppQuickReply $reply) use ($tokens): ?array {
                preg_match_all('/\{\{\s*(\w+)\s*\}\}/', $reply->body, $m);
                foreach (array_unique($m[1]) as $token) {
                    if ($token !== 'customer_name' && trim((string) ($tokens[$token] ?? '')) === '') {
                        return null;
                    }
                }

                return [
                    'id'       => $reply->id,
                    'shortcut' => $reply->shortcut,
                    'category' => $reply->category,
                    'title'    => $reply->title,
                    'body'     => $reply->render($tokens),
                ];
            })
            ->filter()
            ->values();

        return response()->json(['status' => 'success', 'data' => $replies]);
    }

    /**
     * Which courts are free when, on a date — the grid the desk picks a hold from. Times
     * follow the venue's opening hours at its slot length; a venue with no hours set
     * falls back to its slot templates, and with neither the grid is empty and says so.
     */
    public function availability(Request $request, int $id): JsonResponse
    {
        $venue = $this->branch($request, $id);
        $date = Carbon::parse((string) $request->query('date', BusinessClock::today()))->startOfDay();
        $step = max(30, (int) ($venue->slot_minutes ?: 60));

        $starts = [];
        foreach ($venue->windowsForWeekday($date->format('D')) as [$open, $close]) {
            for ($m = $open; $m + $step <= $close; $m += $step) {
                $starts[] = $m;
            }
        }

        $hoursMissing = ! is_array($venue->hours_json) || $venue->hours_json === [];
        if ($hoursMissing) {
            $starts = $venue->slotsOn($date)
                ->map(fn ($slot) => BookingService::timeToMinutes($slot->time))
                ->filter(fn ($m) => $m !== null && $m + $step <= 24 * 60)
                ->values()
                ->all();
        }

        $starts = array_values(array_unique($starts));
        sort($starts);

        $now = BusinessClock::now();
        $isToday = $date->toDateString() === $now->toDateString();
        $nowMin = $now->hour * 60 + $now->minute;

        $courts = VenueCourt::query()->where('venue_id', $venue->id)->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();

        $result = $courts->map(function (VenueCourt $court) use ($venue, $date, $starts, $step, $isToday, $nowMin): array {
            $slots = array_map(function (int $m) use ($venue, $court, $date, $step, $isToday, $nowMin): array {
                $start = sprintf('%02d:%02d:00', intdiv($m, 60), $m % 60);
                $end = sprintf('%02d:%02d:00', intdiv($m + $step, 60) % 24, ($m + $step) % 60);
                $past = $isToday && $m <= $nowMin;

                return [
                    'start_time'   => $start,
                    'end_time'     => $m + $step >= 24 * 60 ? '24:00:00' : $end,
                    'time_label'   => Carbon::createFromTime(intdiv($m, 60), $m % 60)->format('g:i A'),
                    'is_available' => ! $past && $this->intentEngine->checkCourtAvailability($venue, $court, $date, $start, $m + $step >= 24 * 60 ? '24:00:00' : $end),
                    'is_past'      => $past,
                    'price'        => round($court->rateFor($date, substr($start, 0, 5), (int) ($venue->price ?? 0)) * $step / 60, 2),
                ];
            }, $starts);

            return [
                'court_id'   => $court->id,
                'court_name' => $court->name,
                'sports'     => $court->sportsList(),
                'slots'      => $slots,
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'date'          => $date->toDateString(),
                'slot_minutes'  => $step,
                'hours_missing' => $hoursMissing && $starts === [],
                'courts'        => $result,
            ],
        ]);
    }

    /** The chat's live hold — sending a link or taking payment needs one. */
    private function activeBooking(Venue $venue, WhatsAppConversation $conversation): Booking
    {
        if ($conversation->active_booking_id === null) {
            throw ValidationException::withMessages(['booking' => ['Hold a slot for this customer first.']]);
        }

        return Booking::query()->with('venueCourt')->where('venue_id', $venue->id)->findOrFail($conversation->active_booking_id);
    }

    private function secondsLeft(?Booking $booking): int
    {
        if ($booking === null
            || strtoupper((string) $booking->status) !== 'PENDING'
            || $booking->reserved_until === null
            || $booking->reserved_until->isPast()) {
            return 0;
        }

        return max(0, (int) now()->diffInSeconds($booking->reserved_until, false));
    }

    /** @return array<string, mixed>|null */
    private function holdPayload(?Booking $booking): ?array
    {
        if ($booking === null) {
            return null;
        }

        $link = WhatsAppPaymentLink::query()->where('booking_id', $booking->id)->latest('id')->first();

        return [
            'booking_id'   => $booking->id,
            'status'       => strtoupper((string) $booking->status),
            'court_id'     => $booking->venue_court_id,
            'court_name'   => $booking->venueCourt?->name,
            'slot_date'    => (string) Carbon::parse($booking->slot_date)->toDateString(),
            'start_time'   => $booking->start_time,
            'end_time'     => $booking->end_time,
            'amount'       => $booking->amountCharged(),
            'link_status'  => $link?->status,
            'link_url'     => $link?->short_url,
        ];
    }

    /** @return array<string, string> */
    private function venueTokens(Venue $venue): array
    {
        $courts = VenueCourt::query()->where('venue_id', $venue->id)->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();

        $rateCard = $courts
            ->map(function (VenueCourt $c) use ($venue): ?string {
                $rate = (int) ($c->price ?? $venue->price ?? 0);

                return $rate > 0 ? "{$c->name}: ₹".number_format($rate).'/hr' : null;
            })
            ->filter()
            ->implode("\n");

        if ($rateCard === '' && (int) $venue->price > 0) {
            $rateCard = '₹'.number_format((int) $venue->price).'/hr';
        }

        if ($rateCard !== '' && trim((string) $venue->price_note) !== '') {
            $rateCard .= "\n".trim((string) $venue->price_note);
        }

        $mapsUrl = trim((string) $venue->map_link);
        if ($mapsUrl === '' && $venue->latitude !== null && $venue->longitude !== null) {
            $mapsUrl = 'https://maps.google.com/?q='.$venue->latitude.','.$venue->longitude;
        }

        $rules = collect(is_array($venue->rules) ? $venue->rules : [])
            ->map(fn ($r) => is_array($r) ? ($r['text'] ?? $r['rule'] ?? null) : $r)
            ->filter(fn ($r) => is_string($r) && trim($r) !== '')
            ->map(fn ($r) => '• '.trim($r))
            ->implode("\n");

        return [
            'venue_name'    => (string) $venue->name,
            'venue_address' => trim((string) ($venue->address ?: $venue->location)),
            'maps_url'      => $mapsUrl,
            'rate_card'     => $rateCard,
            'venue_hours'   => $venue->displayHours(),
            'venue_rules'   => $rules,
            'hold_minutes'  => (string) WhatsAppReservationService::holdMinutes(),
        ];
    }
}
