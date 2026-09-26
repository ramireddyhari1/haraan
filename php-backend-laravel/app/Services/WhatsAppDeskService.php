<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Models\WhatsAppBotSession;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppIntentExtraction;
use App\Models\WhatsAppMessage;
use App\Support\BusinessClock;
use App\Support\MessageContext;
use App\Support\PlatformRules;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The WhatsApp Desk's inbox: customer messages in, staff replies out.
 *
 * Messages arrive on Haraan's one shared WhatsApp number (Meta or MSG91 webhook →
 * {@see InboundMessages}), so nothing in a message says which venue it's for. The
 * desk works that out from what we know about the sender — see {@see resolveVenue()}
 * — and a message it can't place confidently is left out of every desk rather than
 * dropped into the wrong venue's inbox.
 */
final class WhatsAppDeskService
{
    /** How recent a desk chat or booking must be to decide which venue a sender means. */
    private const RECENT_CHAT_DAYS = 14;

    private const RECENT_BOOKING_DAYS = 120;

    public function __construct(
        private readonly WhatsAppService $whatsappService,
        private readonly WhatsAppIntentEngine $intentEngine,
    ) {}

    /**
     * Route a customer's inbound WhatsApp message to the right venue's desk.
     *
     * @param  int|null  $partnerHint  the partner {@see InboundMessages::attribute()} inferred
     * @return WhatsAppConversation|null null when no venue could be identified
     */
    public function routeInbound(
        string $phoneNumber,
        string $body,
        ?string $providerMessageId = null,
        ?string $customerName = null,
        ?int $partnerHint = null,
    ): ?WhatsAppConversation {
        $phone = self::e164($phoneNumber);
        $venue = $this->resolveVenue($phone, $body, $partnerHint);

        if ($venue === null) {
            Log::info("WhatsApp desk: no venue for inbound from {$phone}; left out of every desk.");

            return null;
        }

        return $this->handleInboundMessage($phone, $body, $providerMessageId, $customerName, (int) $venue->id);
    }

    /**
     * Which venue is this sender talking to? In order of certainty:
     *
     *  1. The message names it — `#V<venue id>`, the reference a "book on WhatsApp"
     *     prefill carries.
     *  2. They already have a recent desk chat with a venue — they're continuing it.
     *  3. Their most recent venue booking (as a guest or with their account).
     *  4. The partner the messaging ledger attributed them to, when that partner
     *     runs exactly one venue.
     */
    public function resolveVenue(string $phone, string $body, ?int $partnerHint = null): ?Venue
    {
        // Mid-booking with the bot: the venue they just picked.
        $session = WhatsAppBotSession::query()->where('phone', $phone)->whereNotNull('venue_id')->first();
        if ($session !== null && ! $session->isStale() && $session->venue !== null) {
            return $session->venue;
        }

        if (preg_match('/#V(\d{1,9})\b/i', $body, $m)) {
            $venue = Venue::query()->find((int) $m[1]);
            if ($venue !== null && $venue->partner_id !== null) {
                return $venue;
            }
        }

        $recentChat = WhatsAppConversation::query()
            ->where('phone_number', $phone)
            ->where('last_message_at', '>=', now()->subDays(self::RECENT_CHAT_DAYS))
            ->latest('last_message_at')
            ->first();

        if ($recentChat !== null && $recentChat->venue !== null) {
            return $recentChat->venue;
        }

        $digits = self::lastTen($phone);
        if ($digits !== null) {
            $like = '%'.$digits;
            // Numbers are stored however the desk typed them ("98765 43210", "+91-98…"),
            // so compare digits only.
            $bare = fn (string $col): string => "replace(replace(replace(replace({$col}, ' ', ''), '-', ''), '+', ''), '(', '')";
            $booking = Booking::query()
                ->where('booking_type', 'venue')
                ->whereNotNull('venue_id')
                ->where('created_at', '>=', now()->subDays(self::RECENT_BOOKING_DAYS))
                ->where(function ($q) use ($like, $bare): void {
                    $q->whereRaw($bare('guest_phone').' like ?', [$like])
                        ->orWhereRaw($bare('attendee_phone').' like ?', [$like])
                        // The account's phone — but not on a desk booking, whose user is
                        // the partner who took it, not this customer.
                        ->orWhere(fn ($own) => $own
                            ->where(fn ($c) => $c->whereNull('channel')->orWhereNotIn('channel', Booking::DESK_CHANNELS))
                            ->whereHas('user', fn ($u) => $u->whereRaw($bare('users.phone').' like ?', [$like])));
                })
                ->latest('id')
                ->first();

            if ($booking?->venue !== null && $booking->venue->partner_id !== null) {
                return $booking->venue;
            }
        }

        if ($partnerHint !== null) {
            $venues = Venue::query()->where('partner_id', $partnerHint)->limit(2)->get();
            if ($venues->count() === 1) {
                return $venues->first();
            }
        }

        return null;
    }

    /**
     * Record an inbound message on a known venue's desk and read it for a booking.
     */
    public function handleInboundMessage(
        string $phoneNumber,
        string $body,
        ?string $providerMessageId,
        ?string $customerName,
        int $venueId,
        array $rawPayload = [],
    ): ?WhatsAppConversation {
        $venue = Venue::query()->find($venueId);

        if ($venue === null || $venue->partner_id === null) {
            return null;
        }

        $phone = self::e164($phoneNumber);

        // Webhooks retry; the same message must not land twice.
        if ($providerMessageId !== null && $providerMessageId !== '') {
            $seen = WhatsAppMessage::query()->where('provider_message_id', $providerMessageId)->first();
            if ($seen !== null) {
                return $seen->conversation;
            }
        }

        $conversation = DB::transaction(function () use ($venue, $phone, $body, $providerMessageId, $customerName, $rawPayload) {
            $conversation = WhatsAppConversation::query()->firstOrCreate(
                ['venue_id' => $venue->id, 'phone_number' => $phone],
                [
                    'partner_id'   => $venue->partner_id,
                    'status'       => 'needs_action',
                    'unread_count' => 0,
                ],
            );

            if ($customerName !== null && trim($customerName) !== '' && empty($conversation->customer_name)) {
                $conversation->customer_name = mb_substr(trim($customerName), 0, 120);
            }

            // Their message opens (or renews) the 24-hour window free-text replies need.
            $conversation->window_expires_at = now()->addHours(24);
            $conversation->last_message_at = now();
            $conversation->last_message_preview = mb_substr($body, 0, 150);
            $conversation->last_message_sender = 'customer';
            $conversation->unread_count = (int) $conversation->unread_count + 1;

            // A live hold stays a hold; anything else now waits on staff.
            if ($conversation->status !== 'hold_active') {
                $conversation->status = 'needs_action';
            }

            $conversation->save();

            $message = WhatsAppMessage::query()->create([
                'conversation_id'     => $conversation->id,
                'direction'           => 'inbound',
                'sender_type'         => 'customer',
                'message_type'        => 'text',
                'body'                => $body,
                'provider_message_id' => $providerMessageId,
                'delivery_status'     => 'delivered',
                'raw_payload'         => $rawPayload,
            ]);

            return [$conversation, $message];
        });

        [$conversation, $message] = $conversation;

        // While the booking bot is walking them through it, its lists ARE the booking —
        // a second suggestion card from the same words would only confuse staff.
        if (WhatsAppBookingBot::isMidFlow($phone)) {
            return $conversation;
        }

        // Reading the message is a bonus; failing to must never lose it.
        try {
            $analysis = $this->intentEngine->analyze($venue, $body, $conversation->customer_name);

            WhatsAppIntentExtraction::query()->create([
                'conversation_id'           => $conversation->id,
                'message_id'                => $message->id,
                'intent_type'               => $analysis['intent_type'],
                'detected_sport'            => $analysis['detected_sport'],
                'detected_date'             => $analysis['detected_date'],
                'detected_start_time'       => $analysis['detected_start_time'],
                'detected_end_time'         => $analysis['detected_end_time'],
                'detected_duration_minutes' => $analysis['detected_duration_minutes'],
                'detected_court_name'       => $analysis['detected_court_name'],
                'resolved_court_id'         => $analysis['resolved_court_id'],
                'resolved_slot_id'          => $analysis['resolved_slot_id'],
                'calculated_rate'           => $analysis['calculated_rate'],
                'confidence_score'          => $analysis['confidence'],
                'action_state'              => 'suggested',
            ]);
        } catch (\Throwable $e) {
            Log::warning("WhatsApp desk: reading message {$message->id} failed: ".$e->getMessage());
        }

        return $conversation;
    }

    /**
     * Send a staff reply. Free text only goes out inside the customer's 24-hour window;
     * outside it WhatsApp needs an approved template, which the desk doesn't send.
     */
    public function sendOutboundMessage(WhatsAppConversation $conversation, string $body, User $sender, ?string $mediaUrl = null): WhatsAppMessage
    {
        if (! $conversation->isWindowActive()) {
            throw ValidationException::withMessages([
                'message' => ['The customer hasn’t messaged in 24 hours, so WhatsApp won’t deliver a free-text reply. Call them, or wait for them to message again.'],
            ]);
        }

        $context = new MessageContext((int) $conversation->partner_id, MessageContext::SERVICE, 'desk.reply');

        // A person has taken over: the booking bot stays quiet for this customer.
        WhatsAppBotSession::pauseFor($conversation->phone_number, PlatformRules::int('whatsapp_bot.pause_hours'));

        $ok = $mediaUrl !== null
            ? $this->whatsappService->sendMedia($conversation->phone_number, $body, $mediaUrl, $context)
            : $this->whatsappService->sendMessage($conversation->phone_number, $body, $context);

        $message = WhatsAppMessage::query()->create([
            'conversation_id' => $conversation->id,
            'direction'       => 'outbound',
            'sender_type'     => 'partner',
            'sender_id'       => $sender->id,
            'message_type'    => $mediaUrl !== null ? 'image' : 'text',
            'body'            => $body,
            'media_url'       => $mediaUrl,
            'delivery_status' => $ok ? 'sent' : 'failed',
        ]);

        $conversation->update([
            'last_message_at'      => now(),
            'last_message_preview' => mb_substr($body, 0, 150),
            'last_message_sender'  => 'partner',
            // Answered: it no longer needs action (a live hold keeps its own status).
            'status'               => $conversation->status === 'needs_action' ? 'active' : $conversation->status,
        ]);

        return $message;
    }

    /**
     * One venue's chats, newest first, filtered by the desk's tabs.
     */
    public function getConversations(Venue $venue, ?string $status = null, ?string $query = null, int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        $q = WhatsAppConversation::query()
            ->where('venue_id', $venue->id)
            ->with(['assignedStaff:id,name', 'latestIntent.resolvedCourt'])
            ->orderByDesc('last_message_at');

        $map = ['needs_action' => 'needs_action', 'holds' => 'hold_active', 'converted' => 'converted', 'archived' => 'archived'];

        if ($status !== null && $status !== 'all' && isset($map[$status])) {
            $q->where('status', $map[$status]);
        }

        $query = trim((string) $query);
        if ($query !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $query).'%';
            $q->where(fn ($sub) => $sub
                ->where('phone_number', 'like', $like)
                ->orWhere('customer_name', 'like', $like)
                ->orWhere('last_message_preview', 'like', $like));
        }

        return $q->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * The desk's numbers, for the venue's business day (not the server's UTC day).
     */
    public function getDashboardMetrics(Venue $venue): array
    {
        $now = now();
        [$dayStart, $dayEnd] = BusinessClock::dayBoundsUtc();

        $conversations = WhatsAppConversation::query()->where('venue_id', $venue->id);

        $whatsappBookings = fn () => Booking::query()
            ->where('venue_id', $venue->id)
            ->where('channel', 'whatsapp');

        $convertedToday = $whatsappBookings()
            ->whereIn(DB::raw('upper(status)'), ['CONFIRMED', 'CHECKED_IN', 'COMPLETED'])
            ->whereBetween('created_at', [$dayStart, $dayEnd]);

        $convertedCount = (clone $convertedToday)->count();

        $chatsToday = (clone $conversations)->whereBetween('last_message_at', [$dayStart, $dayEnd])->count();

        return [
            'unread_conversations'     => (int) (clone $conversations)->sum('unread_count'),
            'needs_action_count'       => (clone $conversations)->where('status', 'needs_action')->count(),
            'active_holds_count'       => $whatsappBookings()
                ->whereRaw('upper(status) = ?', ['PENDING'])
                ->where('reserved_until', '>', $now)
                ->count(),
            'converted_today_count'    => $convertedCount,
            'total_desk_revenue_today' => round((float) (clone $convertedToday)->sum(DB::raw('total_amount + coalesce(tax_amount, 0)')), 2),
            'conversion_rate_percent'  => $chatsToday > 0 ? round($convertedCount / $chatsToday * 100, 1) : 0.0,
            'active_window_open_count' => (clone $conversations)->where('window_expires_at', '>', $now)->count(),
            'hold_minutes'             => WhatsAppReservationService::holdMinutes(),
        ];
    }

    /** "+919876543210" from "919876543210", "+91 98765 43210" or a bare 10-digit Indian number. */
    public static function e164(string $phone): string
    {
        $digits = (string) preg_replace('/\D/', '', $phone);

        if (strlen($digits) === 10) {
            $digits = '91'.$digits;
        }

        return '+'.$digits;
    }

    private static function lastTen(string $phone): ?string
    {
        $digits = (string) preg_replace('/\D/', '', $phone);

        return strlen($digits) >= 10 ? substr($digits, -10) : null;
    }
}
