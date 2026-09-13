<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Models\WhatsAppAuditLog;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppIntentExtraction;
use App\Models\WhatsAppInternalNote;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppQuickReply;
use App\Models\WhatsAppTag;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WhatsAppDeskService
{
    public function __construct(
        private readonly WhatsAppService $whatsappService,
        private readonly WhatsAppIntentEngine $intentEngine,
        private readonly WhatsAppReservationService $reservationService,
    ) {}

    /**
     * Intake an inbound WhatsApp message from a customer (via Meta or MSG91 webhook).
     */
    public function handleInboundMessage(
        string $phoneNumber,
        string $body,
        ?string $providerMessageId = null,
        ?string $customerName = null,
        ?int $knownVenueId = null,
        array $rawPayload = []
    ): WhatsAppConversation {
        return DB::transaction(function () use ($phoneNumber, $body, $providerMessageId, $customerName, $knownVenueId, $rawPayload) {
            // Find appropriate venue: either known or default to first venue for partner or venue 1
            $venue = $knownVenueId !== null
                ? Venue::find($knownVenueId)
                : Venue::orderBy('id')->first();

            if (! $venue) {
                $venue = Venue::firstOrCreate(
                    ['name' => 'Main Sports Arena'],
                    ['price_per_hour' => 800, 'is_active' => true]
                );
            }

            $partnerId = $venue->user_id ?? User::where('role', 'partner')->value('id') ?? 1;

            // Normalize phone number to E.164
            $phone = str_starts_with($phoneNumber, '+') ? $phoneNumber : ('+' . ltrim($phoneNumber, '+'));

            // Find or create conversation
            $conversation = WhatsAppConversation::firstOrCreate(
                [
                    'venue_id'     => $venue->id,
                    'phone_number' => $phone,
                ],
                [
                    'partner_id'        => $partnerId,
                    'customer_name'     => $customerName,
                    'status'            => 'needs_action',
                    'unread_count'      => 0,
                    'window_expires_at' => now()->addHours(24),
                ]
            );

            // Update customer name if provided and not yet set
            if ($customerName !== null && empty($conversation->customer_name)) {
                $conversation->customer_name = $customerName;
            }

            // Inbound opens / refreshes the 24-hour customer service window
            $conversation->window_expires_at = now()->addHours(24);
            $conversation->last_message_at = now();
            $conversation->last_message_preview = mb_substr($body, 0, 150);
            $conversation->last_message_sender = 'customer';
            $conversation->unread_count += 1;

            // If conversation was archived, revive it
            if ($conversation->status === 'archived' || $conversation->status === 'converted') {
                $conversation->status = 'needs_action';
            }

            $conversation->save();

            // Record message
            $msg = WhatsAppMessage::create([
                'conversation_id'     => $conversation->id,
                'direction'           => 'inbound',
                'sender_type'         => 'customer',
                'message_type'        => 'text',
                'body'                => $body,
                'provider_message_id' => $providerMessageId,
                'delivery_status'     => 'delivered',
                'raw_payload'         => $rawPayload,
            ]);

            // Run AI / Heuristic Intent Engine
            try {
                $analysis = $this->intentEngine->analyze($venue, $body, $conversation->customer_name);

                WhatsAppIntentExtraction::create([
                    'conversation_id'           => $conversation->id,
                    'message_id'                => $msg->id,
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
                // Log and don't fail intake
                \Illuminate\Support\Facades\Log::warning("Intent extraction failed: " . $e->getMessage());
            }

            return $conversation;
        });
    }

    /**
     * Send an outbound message from partner staff.
     */
    public function sendOutboundMessage(
        WhatsAppConversation $conversation,
        string $body,
        User $sender,
        string $messageType = 'text',
        ?string $mediaUrl = null
    ): WhatsAppMessage {
        // Enforce 24-hour service window policy
        if (! $conversation->isWindowActive() && $messageType !== 'template') {
            throw ValidationException::withMessages([
                'message' => ['The 24-hour WhatsApp customer service window has expired. You must use an approved template to re-engage this customer.'],
            ]);
        }

        // Send via WhatsApp transport
        $ok = $mediaUrl !== null
            ? $this->whatsappService->sendMedia($conversation->phone_number, $body, $mediaUrl)
            : $this->whatsappService->sendMessage($conversation->phone_number, $body);

        $msg = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'direction'       => 'outbound',
            'sender_type'     => 'partner',
            'sender_id'       => $sender->id,
            'message_type'    => $messageType,
            'body'            => $body,
            'media_url'       => $mediaUrl,
            'delivery_status' => $ok ? 'sent' : 'failed',
        ]);

        $conversation->update([
            'last_message_at'      => now(),
            'last_message_preview' => mb_substr($body, 0, 150),
            'last_message_sender'  => 'partner',
        ]);

        return $msg;
    }

    /**
     * Get paginated conversations for a venue.
     */
    public function getConversations(
        Venue $venue,
        ?string $status = null,
        ?string $query = null,
        int $page = 1,
        int $perPage = 20
    ): LengthAwarePaginator {
        $q = WhatsAppConversation::where('venue_id', $venue->id)
            ->with(['assignedStaff', 'tags', 'activeBooking.venueCourt', 'latestIntent.resolvedCourt'])
            ->orderBy('last_message_at', 'desc');

        if ($status !== null && $status !== 'all') {
            if ($status === 'needs_action') {
                $q->where('status', 'needs_action');
            } elseif ($status === 'holds') {
                $q->where('status', 'hold_active');
            } elseif ($status === 'converted') {
                $q->where('status', 'converted');
            } elseif ($status === 'archived') {
                $q->where('status', 'archived');
            } else {
                $q->where('status', $status);
            }
        }

        if (! empty($query)) {
            $q->where(function ($sub) use ($query) {
                $sub->where('phone_number', 'LIKE', "%{$query}%")
                    ->orWhere('customer_name', 'LIKE', "%{$query}%")
                    ->orWhere('last_message_preview', 'LIKE', "%{$query}%");
            });
        }

        return $q->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Get dashboard summary metrics for the WhatsApp Desk.
     */
    public function getDashboardMetrics(Venue $venue): array
    {
        $now = now();
        $startOfDay = $now->copy()->startOfDay();

        $unreadCount = WhatsAppConversation::where('venue_id', $venue->id)
            ->sum('unread_count');

        $activeHolds = Booking::where('venue_id', $venue->id)
            ->where('channel', 'whatsapp')
            ->where('status', 'hold')
            ->where('reserved_until', '>', $now)
            ->count();

        $convertedToday = Booking::where('venue_id', $venue->id)
            ->where('channel', 'whatsapp')
            ->where('status', 'confirmed')
            ->where('created_at', '>=', $startOfDay)
            ->count();

        $revenueToday = (float) Booking::where('venue_id', $venue->id)
            ->where('channel', 'whatsapp')
            ->where('status', 'confirmed')
            ->where('created_at', '>=', $startOfDay)
            ->sum('total_amount');

        $totalConversationsToday = WhatsAppConversation::where('venue_id', $venue->id)
            ->where('last_message_at', '>=', $startOfDay)
            ->count();

        $conversionRate = $totalConversationsToday > 0
            ? round(($convertedToday / $totalConversationsToday) * 100, 1)
            : 0.0;

        return [
            'unread_conversations'      => (int) $unreadCount,
            'active_holds_count'        => (int) $activeHolds,
            'converted_today_count'     => (int) $convertedToday,
            'total_desk_revenue_today'  => $revenueToday,
            'conversion_rate_percent'   => $conversionRate,
            'active_window_open_count'  => WhatsAppConversation::where('venue_id', $venue->id)
                ->where('window_expires_at', '>', $now)
                ->count(),
        ];
    }
}