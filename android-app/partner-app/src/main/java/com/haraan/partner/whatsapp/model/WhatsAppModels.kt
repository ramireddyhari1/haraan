package com.haraan.partner.whatsapp.model

import java.io.Serializable

data class WhatsAppMetrics(
    val unreadConversations: Int = 0,
    val needsActionCount: Int = 0,
    val activeHoldsCount: Int = 0,
    val convertedTodayCount: Int = 0,
    val totalDeskRevenueToday: Double = 0.0,
    val conversionRatePercent: Double = 0.0,
    val activeWindowOpenCount: Int = 0,
    /** /control → Platform rules → WhatsApp reservation hold. */
    val holdMinutes: Int = 0
) : Serializable

data class ConversationSummary(
    val id: Long,
    val venueId: Long,
    val phoneNumber: String,
    val customerName: String?,
    val status: String, // active, needs_action, hold_active, converted, archived
    val assignedStaffId: Long?,
    val assignedStaffName: String?,
    val lastMessageAt: String?,
    val lastMessagePreview: String?,
    val lastMessageSender: String, // customer, partner, system
    val unreadCount: Int,
    val windowExpiresAt: String?,
    val isWindowActive: Boolean,
    val activeBookingId: Long?,
    val latestIntent: IntentSummary? = null
) : Serializable

data class IntentSummary(
    val intentType: String,
    val sport: String?,
    val date: String?,
    val startTime: String?,
    val endTime: String?,
    val courtId: Long?,
    val courtName: String?,
    val calculatedRate: Double?,
    val confidence: Float,
    val actionState: String // suggested, hold_created, converted, dismissed
) : Serializable

data class ChatMessage(
    val id: Long,
    val conversationId: Long,
    val direction: String, // inbound, outbound
    val senderType: String, // customer, partner, system
    val messageType: String, // text, image, payment_link, ticket_card
    val body: String,
    val mediaUrl: String? = null,
    val deliveryStatus: String = "sent", // sent, delivered, read, failed, internal
    val createdAt: String = ""
) : Serializable

/**
 * What the customer's message asked for, as far as the server could read it. Any field
 * the customer didn't say is null — the desk fills it in with the slot picker.
 */
data class BookingSuggestion(
    val sport: String?,
    val courtId: Long?,
    val courtName: String?,
    val date: String?,      // yyyy-MM-dd
    val startTime: String?, // HH:mm:ss
    val endTime: String?,
    val price: Double?
) : Serializable {
    val isComplete: Boolean
        get() = courtId != null && date != null && startTime != null && endTime != null
}

/** The chat's live hold: a real PENDING booking on the court, with its own clock. */
data class ActiveHold(
    val bookingId: Long,
    val courtId: Long?,
    val courtName: String?,
    val date: String,
    val startTime: String,
    val endTime: String,
    val amount: Double,
    val linkStatus: String?, // issued, paid, expired, cancelled, refunded
    val linkUrl: String?
) : Serializable {
    val linkSent: Boolean get() = linkStatus == "issued"
}

data class ConversationDetail(
    val messages: List<ChatMessage>,
    val suggestion: BookingSuggestion?,
    val hold: ActiveHold?,
    val holdSecondsLeft: Int,
    val holdTotalSeconds: Int,
    val isWindowActive: Boolean
)

data class QuickReplyItem(
    val id: Long,
    val shortcut: String,
    val category: String,
    val title: String,
    val body: String
) : Serializable

data class CourtSlotAvailability(
    val startTime: String,
    val endTime: String,
    val timeLabel: String,
    val isAvailable: Boolean,
    val isPast: Boolean,
    val price: Double
) : Serializable

data class CourtAvailabilityItem(
    val courtId: Long,
    val courtName: String,
    val slots: List<CourtSlotAvailability> = emptyList()
) : Serializable

data class DayAvailability(
    val date: String,
    val slotMinutes: Int,
    val hoursMissing: Boolean,
    val courts: List<CourtAvailabilityItem>
) : Serializable

data class HoldResult(
    val hold: ActiveHold,
    val secondsRemaining: Int,
    val holdTotalSeconds: Int
)

/** A desk request the server refused, carrying the server's own explanation. */
class DeskApiException(message: String, val httpCode: Int = 0) : Exception(message)
