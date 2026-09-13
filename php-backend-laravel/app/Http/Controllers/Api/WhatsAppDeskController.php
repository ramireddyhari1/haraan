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
use App\Models\WhatsAppQuickReply;
use App\Models\WhatsAppTag;
use App\Services\WhatsAppDeskService;
use App\Services\WhatsAppIntentEngine;
use App\Services\WhatsAppReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class WhatsAppDeskController extends Controller
{
    public function __construct(
        private readonly WhatsAppDeskService $deskService,
        private readonly WhatsAppReservationService $reservationService,
        private readonly WhatsAppIntentEngine $intentEngine,
    ) {}

    /**
     * Desk Dashboard metrics summary.
     */
    public function dashboard(Request $request, int $id): JsonResponse
    {
        $venue = Venue::findOrFail($id);
        $metrics = $this->deskService->getDashboardMetrics($venue);

        return response()->json([
            'status' => 'success',
            'data'   => $metrics,
        ]);
    }

    /**
     * List conversations with search and tab filter.
     */
    public function index(Request $request, int $id): JsonResponse
    {
        $venue = Venue::findOrFail($id);
        $status = $request->query('status'); // all, needs_action, holds, converted, archived
        $search = $request->query('q');
        $page = (int) $request->query('page', 1);

        $paginator = $this->deskService->getConversations($venue, $status, $search, $page);

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

    /**
     * Conversation detail with message timeline and active hold info.
     */
    public function show(Request $request, int $id, int $convId): JsonResponse
    {
        $venue = Venue::findOrFail($id);
        $conversation = WhatsAppConversation::where('venue_id', $venue->id)
            ->where('id', $convId)
            ->with(['assignedStaff', 'tags', 'activeBooking.venueCourt', 'latestIntent.resolvedCourt'])
            ->firstOrFail();

        // Mark as read when opened
        if ($conversation->unread_count > 0) {
            $conversation->update(['unread_count' => 0]);
        }

        $messages = $conversation->messages()->take(100)->get();
        $notes = $conversation->notes()->with('author')->get();

        // Calculate seconds remaining in active hold if any
        $activeHoldSeconds = 0;
        if ($conversation->activeBooking && $conversation->activeBooking->status === 'hold') {
            $until = $conversation->activeBooking->reserved_until;
            if ($until !== null && $until->isFuture()) {
                $activeHoldSeconds = max(0, (int) now()->diffInSeconds($until, false));
            }
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'conversation'             => $conversation,
                'messages'                 => $messages,
                'notes'                    => $notes,
                'active_hold_seconds_left' => $activeHoldSeconds,
                'window_seconds_left'      => $conversation->secondsRemainingInWindow(),
                'is_window_active'         => $conversation->isWindowActive(),
            ],
        ]);
    }

    /**
     * Send an outbound chat message.
     */
    public function sendMessage(Request $request, int $id, int $convId): JsonResponse
    {
        $venue = Venue::findOrFail($id);
        $conversation = WhatsAppConversation::where('venue_id', $venue->id)->findOrFail($convId);
        $user = $request->user() ?: User::first();

        $validated = $request->validate([
            'body'         => 'required|string|max:4000',
            'message_type' => 'nullable|string|in:text,template,image',
            'media_url'    => 'nullable|string|url',
        ]);

        $msg = $this->deskService->sendOutboundMessage(
            $conversation,
            $validated['body'],
            $user,
            $validated['message_type'] ?? 'text',
            $validated['media_url'] ?? null
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Message sent successfully',
            'data'    => $msg,
        ]);
    }

    /**
     * Run or re-run Intent Engine on conversation.
     */
    public function extractIntent(Request $request, int $id, int $convId): JsonResponse
    {
        $venue = Venue::findOrFail($id);
        $conversation = WhatsAppConversation::where('venue_id', $venue->id)->findOrFail($convId);

        $textToAnalyze = $request->input('text') ?: $conversation->last_message_preview ?: 'Can I book a court today at 6pm?';

        $analysis = $this->intentEngine->analyze($venue, $textToAnalyze, $conversation->customer_name);

        return response()->json([
            'status' => 'success',
            'data'   => $analysis,
        ]);
    }

    /**
     * 1-Tap Hold Slot (Strictly 2 minutes TTL).
     */
    public function holdSlot(Request $request, int $id, int $convId): JsonResponse
    {
        $venue = Venue::findOrFail($id);
        $conversation = WhatsAppConversation::where('venue_id', $venue->id)->findOrFail($convId);
        $user = $request->user();

        $validated = $request->validate([
            'court_id'   => 'required|integer|exists:venue_courts,id',
            'slot_date'  => 'required|date',
            'start_time' => 'required|date_format:H:i:s',
            'end_time'   => 'required|date_format:H:i:s',
            'price'      => 'required|numeric|min:0',
        ]);

        $court = VenueCourt::where('venue_id', $venue->id)->findOrFail($validated['court_id']);
        $date = Carbon::parse($validated['slot_date']);

        $booking = $this->reservationService->holdSlot(
            $venue,
            $conversation,
            $court,
            $date,
            $validated['start_time'],
            $validated['end_time'],
            (float) $validated['price'],
            $user
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Slot held for 2 minutes',
            'data'    => [
                'booking_id'           => $booking->id,
                'reserved_until'       => $booking->reserved_until->toIso8601String(),
                'seconds_remaining'    => WhatsAppReservationService::HOLD_DURATION_MINUTES * 60,
                'ticket_code'          => $booking->ticket_code,
            ],
        ]);
    }

    /**
     * Release active temporary hold.
     */
    public function releaseHold(Request $request, int $id, int $convId): JsonResponse
    {
        $venue = Venue::findOrFail($id);
        $conversation = WhatsAppConversation::where('venue_id', $venue->id)->findOrFail($convId);
        $user = $request->user();

        $this->reservationService->releaseHold($venue, $conversation, $user);

        return response()->json([
            'status'  => 'success',
            'message' => 'Hold released',
        ]);
    }

    /**
     * 1-Tap Send Razorpay Payment Link.
     */
    public function sendPaymentLink(Request $request, int $id, int $convId): JsonResponse
    {
        $venue = Venue::findOrFail($id);
        $conversation = WhatsAppConversation::where('venue_id', $venue->id)->findOrFail($convId);
        $user = $request->user();

        if ($conversation->active_booking_id === null) {
            throw ValidationException::withMessages([
                'booking' => ['No active hold exists for this conversation. Please hold a slot first.'],
            ]);
        }

        $booking = Booking::findOrFail($conversation->active_booking_id);
        $amount = (float) ($request->input('amount') ?: $booking->total_amount);

        $link = $this->reservationService->sendPaymentLink($venue, $conversation, $booking, $amount, $user);

        return response()->json([
            'status'  => 'success',
            'message' => 'Payment link dispatched via WhatsApp',
            'data'    => $link,
        ]);
    }

    /**
     * Manual Paid confirmation (Cash / Counter UPI) with Shift Register reconciliation.
     */
    public function markPaid(Request $request, int $id, int $convId): JsonResponse
    {
        $venue = Venue::findOrFail($id);
        $conversation = WhatsAppConversation::where('venue_id', $venue->id)->findOrFail($convId);
        $user = $request->user();

        if ($conversation->active_booking_id === null) {
            throw ValidationException::withMessages([
                'booking' => ['No active hold exists for this conversation.'],
            ]);
        }

        $booking = Booking::findOrFail($conversation->active_booking_id);
        $method = $request->input('method', 'cash'); // cash or upi

        $this->reservationService->markPaidManual($venue, $conversation, $booking, $method, $user);

        return response()->json([
            'status'  => 'success',
            'message' => 'Booking marked as paid and confirmed. Ticket dispatched.',
            'data'    => [
                'booking_id'  => $booking->id,
                'status'      => 'confirmed',
                'ticket_code' => $booking->ticket_code,
            ],
        ]);
    }

    /**
     * Add an internal team note.
     */
    public function addNote(Request $request, int $id, int $convId): JsonResponse
    {
        $venue = Venue::findOrFail($id);
        $conversation = WhatsAppConversation::where('venue_id', $venue->id)->findOrFail($convId);
        $user = $request->user() ?: User::first();

        $validated = $request->validate(['note' => 'required|string|max:1000']);

        $note = WhatsAppInternalNote::create([
            'conversation_id' => $conversation->id,
            'user_id'         => $user->id,
            'note'            => $validated['note'],
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Internal note added',
            'data'    => $note->load('author'),
        ]);
    }

    /**
     * Assign staff member to conversation.
     */
    public function assignStaff(Request $request, int $id, int $convId): JsonResponse
    {
        $venue = Venue::findOrFail($id);
        $conversation = WhatsAppConversation::where('venue_id', $venue->id)->findOrFail($convId);

        $validated = $request->validate(['staff_id' => 'nullable|integer|exists:users,id']);

        $conversation->update(['assigned_staff_id' => $validated['staff_id'] ?? null]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Staff assigned',
            'data'    => $conversation->load('assignedStaff'),
        ]);
    }

    /**
     * List canned quick replies.
     */
    public function quickReplies(Request $request, int $id): JsonResponse
    {
        $venue = Venue::findOrFail($id);

        $replies = WhatsAppQuickReply::where(function ($q) use ($venue) {
            $q->whereNull('venue_id')->orWhere('venue_id', $venue->id);
        })->get();

        if ($replies->isEmpty()) {
            // Seed system default quick replies
            $defaults = [
                ['shortcut' => '/pricing', 'category' => 'Pricing', 'title' => 'Standard Rate Card', 'body' => "Hi {{customer_name}}! Here are our turf rates for {{venue_name}}:\n- Mon to Thu: ₹800/hr\n- Fri to Sun (Peak): ₹1,200/hr\nAll slots include balls and drinking water!"],
                ['shortcut' => '/rules', 'category' => 'Rules', 'title' => 'Venue Turf Rules', 'body' => "Venue Rules at {{venue_name}}:\n1. Non-marking turf shoes only (no metal studs).\n2. Arrive 10 mins prior to slot.\n3. Outside food not permitted."],
                ['shortcut' => '/location', 'category' => 'Directions', 'title' => 'Venue Location & Map', 'body' => "📍 {{venue_name}} is located at: 124 Main Sports Hub, Near Metro Pillar 84.\nGoogle Maps Link: https://maps.google.com/?q={{venue_name}}"],
                ['shortcut' => '/confirm', 'category' => 'Booking', 'title' => 'Confirm Hold Reminder', 'body' => "Hi {{customer_name}}, your 2-minute slot hold for {{court_name}} is active. Please complete payment at {{payment_url}} to lock your slot!"],
            ];

            foreach ($defaults as $d) {
                WhatsAppQuickReply::create(array_merge($d, ['venue_id' => $venue->id]));
            }

            $replies = WhatsAppQuickReply::where('venue_id', $venue->id)->get();
        }

        return response()->json([
            'status' => 'success',
            'data'   => $replies,
        ]);
    }

    /**
     * Check court availability grid for a date.
     */
    public function availability(Request $request, int $id): JsonResponse
    {
        $venue = Venue::findOrFail($id);
        $dateStr = $request->query('date', now()->toDateString());
        $date = Carbon::parse($dateStr);

        $courts = VenueCourt::where('venue_id', $venue->id)->where('is_active', true)->get();

        $result = [];
        foreach ($courts as $court) {
            $hourlySlots = [];
            for ($hour = 6; $hour <= 23; $hour++) {
                $start = sprintf('%02d:00:00', $hour);
                $end = sprintf('%02d:00:00', $hour + 1);

                $isAvailable = $this->intentEngine->checkCourtAvailability($venue, $court, $date, $start, $end);
                $hourlySlots[] = [
                    'hour'         => $hour,
                    'time_label'   => sprintf('%02d:00 - %02d:00', $hour, $hour + 1),
                    'start_time'   => $start,
                    'end_time'     => $end,
                    'is_available' => $isAvailable,
                ];
            }

            $result[] = [
                'court_id'   => $court->id,
                'court_name' => $court->name,
                'price'      => $court->price ?: $venue->price_per_hour,
                'slots'      => $hourlySlots,
            ];
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'date'   => $date->toDateString(),
                'courts' => $result,
            ],
        ]);
    }
}