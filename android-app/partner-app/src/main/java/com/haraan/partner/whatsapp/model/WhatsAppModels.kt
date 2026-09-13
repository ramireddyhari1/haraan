package com.haraan.partner.whatsapp.model

import java.io.Serializable

data class WhatsAppMetrics(
    val unreadConversations: Int = 0,
    val activeHoldsCount: Int = 0,
    val convertedTodayCount: Int = 0,
    val totalDeskRevenueToday: Double = 0.0,
    val conversionRatePercent: Double = 0.0,
    val activeWindowOpenCount: Int = 0
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
    val senderType: String, // customer, partner, system, bot
    val messageType: String, // text, image, template, payment_link, ticket_card
    val body: String,
    val mediaUrl: String? = null,
    val deliveryStatus: String = "sent", // pending, sent, delivered, read, failed
    val createdAt: String = "",
    val ticketCode: String? = null,
    val ticketUrl: String? = null,
    val paymentUrl: String? = null
) : Serializable

data class BookingSuggestion(
    val conversationId: Long,
    val sport: String? = "Cricket",
    val courtId: Long,
    val courtName: String,
    val date: String,
    val startTime: String,
    val endTime: String,
    val durationMinutes: Int = 60,
    val price: Double,
    val isAvailable: Boolean = true,
    val pricingRuleName: String? = null,
    val suggestedReplyText: String = "",
    val activeHoldSecondsRemaining: Int = 0 // Centrally configurable hold seconds (default 300s / 5 min)
) : Serializable

data class InternalNoteItem(
    val id: Long,
    val conversationId: Long,
    val authorName: String,
    val note: String,
    val createdAt: String
) : Serializable

data class QuickReplyItem(
    val id: Long,
    val shortcut: String,
    val category: String,
    val title: String,
    val body: String
) : Serializable

data class CourtSlotAvailability(
    val hour: Int,
    val timeLabel: String,
    val startTime: String,
    val endTime: String,
    val isAvailable: Boolean
) : Serializable

data class CourtAvailabilityItem(
    val courtId: Long,
    val courtName: String,
    val price: Int,
    val slots: List<CourtSlotAvailability> = emptyList()
) : Serializable

data class HoldSlotRequest(
    val courtId: Long,
    val slotDate: String,
    val startTime: String,
    val endTime: String,
    val price: Double
) : Serializable

data class SendMessageRequest(
    val body: String,
    val messageType: String = "text"
) : Serializable