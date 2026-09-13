<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\ShiftSession;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Models\WhatsAppAuditLog;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppIntentExtraction;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppPaymentLink;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class WhatsAppReservationService
{
    /** Temporary Hold duration in minutes — centrally configurable (5 minutes / 300s TTL) */
    public const HOLD_DURATION_MINUTES = 5;

    public function __construct(
        private readonly WhatsAppIntentEngine $intentEngine,
        private readonly WhatsAppService $whatsappService,
    ) {}

    /**
     * Create a 2-minute temporary booking hold on the court.
     */
    public function holdSlot(
        Venue $venue,
        WhatsAppConversation $conversation,
        VenueCourt $court,
        Carbon $date,
        string $startTime,
        string $endTime,
        float $price,
        ?User $actor = null
    ): Booking {
        return DB::transaction(function () use ($venue, $conversation, $court, $date, $startTime, $endTime, $price, $actor) {
            // 1. Double check availability
            $available = $this->intentEngine->checkCourtAvailability(
                $venue,
                $court,
                $date,
                $startTime,
                $endTime
            );

            if (! $available) {
                throw ValidationException::withMessages([
                    'court' => ['The selected court slot is no longer available.'],
                ]);
            }

            // If conversation already has an active hold, cancel it first
            if ($conversation->active_booking_id !== null) {
                $oldBooking = Booking::find($conversation->active_booking_id);
                if ($oldBooking && $oldBooking->status === 'hold') {
                    $oldBooking->update(['status' => 'cancelled']);
                }
            }

            // 2. Create the Booking hold row with 2-minute TTL
            $reservedUntil = now()->addMinutes(self::HOLD_DURATION_MINUTES);

            $booking = Booking::create([
                'user_id'         => $actor?->id ?? $conversation->partner_id ?? $venue->user_id ?? 1,
                'venue_id'        => $venue->id,
                'venue_court_id'  => $court->id,
                'slot_date'       => $date->toDateString(),
                'start_time'      => $startTime,
                'end_time'        => $endTime,
                'booking_type'    => 'venue_slot',
                'channel'         => 'whatsapp',
                'guest_name'      => $conversation->customer_name ?: 'WhatsApp Guest',
                'guest_phone'     => $conversation->phone_number,
                'total_amount'    => $price,
                'status'          => 'hold',
                'reserved_until'  => $reservedUntil,
                'quantity'        => 1,
            ]);

            // 3. Update Conversation State
            $preview = "⚡ 2-Min Hold: {$court->name} ({$date->format('d M')} " . Carbon::parse($startTime)->format('h:i A') . ")";
            $conversation->update([
                'active_booking_id'    => $booking->id,
                'status'               => 'hold_active',
                'last_message_at'      => now(),
                'last_message_preview' => $preview,
                'last_message_sender'  => 'system',
            ]);

            // 4. Record System Message in Conversation
            WhatsAppMessage::create([
                'conversation_id' => $conversation->id,
                'direction'       => 'outbound',
                'sender_type'     => 'system',
                'sender_id'       => $actor?->id,
                'message_type'    => 'text',
                'body'            => "⚡ [System Hold] Held {$court->name} for {$date->format('D, d M')} " . Carbon::parse($startTime)->format('h:i A') . " - " . Carbon::parse($endTime)->format('h:i A') . " (2 minutes remaining). Rate: ₹" . number_format($price),
                'delivery_status' => 'delivered',
            ]);

            // Update intent record state
            WhatsAppIntentExtraction::where('conversation_id', $conversation->id)
                ->where('action_state', 'suggested')
                ->update(['action_state' => 'hold_created']);

            // 5. Audit log
            WhatsAppAuditLog::log(
                $venue->id,
                'hold_created',
                $conversation->id,
                $actor?->id,
                $actor?->name ?? 'System',
                [
                    'booking_id'     => $booking->id,
                    'court_id'       => $court->id,
                    'court_name'     => $court->name,
                    'date'           => $date->toDateString(),
                    'start_time'     => $startTime,
                    'end_time'       => $endTime,
                    'price'          => $price,
                    'reserved_until' => $reservedUntil->toIso8601String(),
                ]
            );

            return $booking;
        });
    }

    /**
     * Release an active temporary hold.
     */
    public function releaseHold(
        Venue $venue,
        WhatsAppConversation $conversation,
        ?User $actor = null
    ): void {
        DB::transaction(function () use ($venue, $conversation, $actor) {
            if ($conversation->active_booking_id !== null) {
                $booking = Booking::find($conversation->active_booking_id);
                if ($booking && $booking->status === 'hold') {
                    $booking->update(['status' => 'cancelled']);
                }
            }

            $conversation->update([
                'active_booking_id'    => null,
                'status'               => 'active',
                'last_message_at'      => now(),
                'last_message_preview' => 'Hold released',
                'last_message_sender'  => 'system',
            ]);

            WhatsAppMessage::create([
                'conversation_id' => $conversation->id,
                'direction'       => 'outbound',
                'sender_type'     => 'system',
                'sender_id'       => $actor?->id,
                'message_type'    => 'text',
                'body'            => '❌ [System Hold] Temporary court hold has been released.',
                'delivery_status' => 'delivered',
            ]);

            WhatsAppAuditLog::log(
                $venue->id,
                'hold_released',
                $conversation->id,
                $actor?->id,
                $actor?->name ?? 'System'
            );
        });
    }

    /**
     * Generate Razorpay Payment Link and send via WhatsApp.
     */
    public function sendPaymentLink(
        Venue $venue,
        WhatsAppConversation $conversation,
        Booking $booking,
        float $amount,
        ?User $actor = null
    ): WhatsAppPaymentLink {
        return DB::transaction(function () use ($venue, $conversation, $booking, $amount, $actor) {
            $token = Str::random(12);
            $shortUrl = "https://haraan.app/pay/{$token}";
            $expiresAt = $booking->reserved_until ?? now()->addMinutes(self::HOLD_DURATION_MINUTES);

            $paymentLink = WhatsAppPaymentLink::create([
                'conversation_id'          => $conversation->id,
                'booking_id'               => $booking->id,
                'venue_id'                 => $venue->id,
                'razorpay_payment_link_id' => 'plink_' . Str::random(16),
                'short_url'                => $shortUrl,
                'amount'                   => $amount,
                'status'                   => 'issued',
                'expires_at'               => $expiresAt,
            ]);

            $courtName = $booking->venueCourt?->name ?? 'Court';
            $slotDate = Carbon::parse($booking->slot_date)->format('d M');
            $startTime = Carbon::parse($booking->start_time)->format('h:i A');

            $messageText = "⚡ Slot Held! {$courtName} on {$slotDate} at {$startTime}. Amount: ₹" . number_format($amount) . ".\n\nComplete your payment within 2 minutes to confirm:\n{$shortUrl}";

            // Send over WhatsApp service (best-effort)
            $this->whatsappService->sendMessage($conversation->phone_number, $messageText);

            // Record message in thread
            WhatsAppMessage::create([
                'conversation_id' => $conversation->id,
                'direction'       => 'outbound',
                'sender_type'     => 'partner',
                'sender_id'       => $actor?->id,
                'message_type'    => 'payment_link',
                'body'            => $messageText,
                'delivery_status' => 'sent',
                'raw_payload'     => [
                    'payment_link_id' => $paymentLink->id,
                    'short_url'       => $shortUrl,
                    'amount'          => $amount,
                ],
            ]);

            $conversation->update([
                'last_message_at'      => now(),
                'last_message_preview' => "Payment Link sent: ₹" . number_format($amount),
                'last_message_sender'  => 'partner',
            ]);

            WhatsAppAuditLog::log(
                $venue->id,
                'payment_link_sent',
                $conversation->id,
                $actor?->id,
                $actor?->name ?? 'Partner Staff',
                ['amount' => $amount, 'short_url' => $shortUrl]
            );

            return $paymentLink;
        });
    }

    /**
     * Convert booking hold to confirmed upon payment (Razorpay webhook, online success, or manual desk confirmation).
     */
    public function confirmBookingAndConvert(
        Venue $venue,
        WhatsAppConversation $conversation,
        Booking $booking,
        string $paymentMethod = 'razorpay',
        ?string $transactionReference = null,
        ?User $actor = null,
        ?int $shiftSessionId = null
    ): void {
        DB::transaction(function () use ($venue, $conversation, $booking, $paymentMethod, $transactionReference, $actor, $shiftSessionId) {
            // 1. Confirm Booking
            $booking->update([
                'status'         => 'confirmed',
                'payment_status' => 'paid',
                'amount_paid'    => $booking->total_amount,
                'reserved_until' => null,
            ]);

            // 2. Record Payment Ledger row
            BookingPayment::create([
                'booking_id'       => $booking->id,
                'amount'           => $booking->total_amount,
                'method'           => $paymentMethod,
                'reference'        => $transactionReference ?? ('wa_conv_' . Str::random(10)),
                'collected_by'     => $actor?->id,
                'shift_session_id' => $shiftSessionId,
                'collected_at'     => now(),
            ]);

            // 3. Mark payment links as paid
            WhatsAppPaymentLink::where('booking_id', $booking->id)
                ->where('status', 'issued')
                ->update([
                    'status'  => 'paid',
                    'paid_at' => now(),
                ]);

            // 4. Send WhatsApp Ticket Confirmation
            $ticketCode = $booking->ticket_code;
            $ticketUrl = "https://haraan.app/t/{$ticketCode}";
            $courtName = $booking->venueCourt?->name ?? 'Court';
            $slotDate = Carbon::parse($booking->slot_date)->format('D, d M');
            $startTime = Carbon::parse($booking->start_time)->format('h:i A');

            $confirmMessage = "🎉 Booking Confirmed!\n\nVenue: {$venue->name}\nCourt: {$courtName}\nSlot: {$slotDate} at {$startTime}\nPass Code: #{$ticketCode}\n\nShow your digital pass & QR code upon arrival:\n{$ticketUrl}\n\nSee you on the turf!";

            $this->whatsappService->sendMessage($conversation->phone_number, $confirmMessage);

            WhatsAppMessage::create([
                'conversation_id' => $conversation->id,
                'direction'       => 'outbound',
                'sender_type'     => 'system',
                'sender_id'       => $actor?->id,
                'message_type'    => 'ticket_card',
                'body'            => $confirmMessage,
                'delivery_status' => 'delivered',
                'raw_payload'     => [
                    'ticket_code' => $ticketCode,
                    'ticket_url'  => $ticketUrl,
                ],
            ]);

            // 5. Update Conversation State to Converted
            $conversation->update([
                'active_booking_id'    => null,
                'status'               => 'converted',
                'last_message_at'      => now(),
                'last_message_preview' => "🎉 Booking Confirmed (#{$ticketCode})",
                'last_message_sender'  => 'system',
            ]);

            WhatsAppIntentExtraction::where('conversation_id', $conversation->id)
                ->update(['action_state' => 'converted']);

            WhatsAppAuditLog::log(
                $venue->id,
                'booking_converted',
                $conversation->id,
                $actor?->id,
                $actor?->name ?? 'System',
                [
                    'booking_id'     => $booking->id,
                    'ticket_code'    => $ticketCode,
                    'amount'         => $booking->total_amount,
                    'payment_method' => $paymentMethod,
                ]
            );
        });
    }

    /**
     * Manual Counter Cash or UPI conversion with Shift Register reconciliation.
     */
    public function markPaidManual(
        Venue $venue,
        WhatsAppConversation $conversation,
        Booking $booking,
        string $method = 'cash',
        ?User $actor = null
    ): void {
        DB::transaction(function () use ($venue, $conversation, $booking, $method, $actor) {
            $openShift = ShiftSession::where('venue_id', $venue->id)
                ->whereNull('closed_at')
                ->latest()
                ->first();

            $this->confirmBookingAndConvert(
                $venue,
                $conversation,
                $booking,
                $method,
                'manual_' . strtoupper($method) . '_' . Str::random(8),
                $actor,
                $openShift?->id
            );

            WhatsAppAuditLog::log(
                $venue->id,
                'manual_paid',
                $conversation->id,
                $actor?->id,
                $actor?->name ?? 'Partner Staff',
                ['method' => $method, 'amount' => $booking->total_amount]
            );
        });
    }
}