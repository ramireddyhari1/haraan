package com.haraan.partner.whatsapp.data

import com.haraan.partner.whatsapp.model.*

class WhatsAppRepository(
    private val remote: WhatsAppRemoteDataSource,
    private val local: WhatsAppLocalDataSource
) {

    suspend fun getDashboardMetrics(token: String, venueId: Long): WhatsAppMetrics {
        return try {
            remote.fetchDashboard(token, venueId)
        } catch (e: Exception) {
            WhatsAppMetrics()
        }
    }

    suspend fun getConversations(
        token: String,
        venueId: Long,
        status: String? = null,
        searchQuery: String? = null,
        page: Int = 1
    ): List<ConversationSummary> {
        val filterKey = status ?: "all"
        return try {
            val remoteList = remote.fetchConversations(token, venueId, status, searchQuery, page)
            local.cacheConversations(venueId, filterKey, remoteList)
            remoteList
        } catch (e: Exception) {
            local.getCachedConversations(venueId, filterKey) ?: emptyList()
        }
    }

    suspend fun getConversationDetail(
        token: String,
        venueId: Long,
        conversationId: Long
    ): Pair<List<ChatMessage>, BookingSuggestion?> {
        return try {
            val result = remote.fetchConversationDetail(token, venueId, conversationId)
            local.cacheMessages(conversationId, result.first)
            result
        } catch (e: Exception) {
            val cachedMsgs = local.getCachedMessages(conversationId) ?: emptyList()
            Pair(cachedMsgs, null)
        }
    }

    suspend fun sendMessage(
        token: String,
        venueId: Long,
        conversationId: Long,
        body: String,
        messageType: String = "text"
    ): ChatMessage {
        return try {
            remote.sendMessage(token, venueId, conversationId, body, messageType)
        } catch (e: Exception) {
            local.enqueuePendingAction("send_message", conversationId, """{"body":"$body","message_type":"$messageType"}""")
            ChatMessage(
                id = System.currentTimeMillis(),
                conversationId = conversationId,
                direction = "outbound",
                senderType = "partner",
                messageType = messageType,
                body = body,
                deliveryStatus = "pending",
                createdAt = "Pending offline"
            )
        }
    }

    suspend fun holdSlot(
        token: String,
        venueId: Long,
        conversationId: Long,
        req: HoldSlotRequest
    ): Triple<Long, String, Int> {
        return remote.holdSlot(token, venueId, conversationId, req)
    }

    suspend fun releaseHold(token: String, venueId: Long, conversationId: Long): Boolean {
        return remote.releaseHold(token, venueId, conversationId)
    }

    suspend fun sendPaymentLink(
        token: String,
        venueId: Long,
        conversationId: Long,
        amount: Double? = null
    ): Boolean {
        return remote.sendPaymentLink(token, venueId, conversationId, amount)
    }

    suspend fun markPaidManual(
        token: String,
        venueId: Long,
        conversationId: Long,
        method: String = "cash"
    ): String {
        return remote.markPaidManual(token, venueId, conversationId, method)
    }

    suspend fun getQuickReplies(token: String, venueId: Long): List<QuickReplyItem> {
        return try {
            remote.fetchQuickReplies(token, venueId)
        } catch (e: Exception) {
            listOf(
                QuickReplyItem(1, "/pricing", "Pricing", "Rate Card", "Our court rate is ₹1,000/hr."),
                QuickReplyItem(2, "/rules", "Rules", "Venue Rules", "Non-marking sports shoes only."),
                QuickReplyItem(3, "/location", "Directions", "Venue Map", "Located at Main Turf Complex.")
            )
        }
    }
}