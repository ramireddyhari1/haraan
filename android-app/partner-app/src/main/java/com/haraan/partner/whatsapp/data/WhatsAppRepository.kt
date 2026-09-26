package com.haraan.partner.whatsapp.data

import com.haraan.partner.whatsapp.model.*
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

/**
 * The desk's data. Reads fall back to the last cached copy when the network is down so
 * the inbox isn't blank on a flaky counter connection; every WRITE goes straight to the
 * server and fails loudly — a reply or a hold that only exists on this phone is worse
 * than one that visibly didn't go.
 */
class WhatsAppRepository(
    private val remote: WhatsAppRemoteDataSource,
    private val local: WhatsAppLocalDataSource
) {

    suspend fun getDashboardMetrics(token: String, venueId: Long): WhatsAppMetrics? =
        runCatching { remote.fetchDashboard(token, venueId) }.getOrNull()

    suspend fun getConversations(
        token: String,
        venueId: Long,
        status: String? = null,
        searchQuery: String? = null
    ): List<ConversationSummary> {
        val filterKey = status ?: "all"
        return try {
            val list = remote.fetchConversations(token, venueId, status, searchQuery)
            // Only the unfiltered, unsearched list is worth keeping for offline.
            if (searchQuery.isNullOrBlank()) {
                withContext(Dispatchers.IO) { local.cacheConversations(venueId, filterKey, list) }
            }
            list
        } catch (e: Exception) {
            withContext(Dispatchers.IO) { local.getCachedConversations(venueId, filterKey) } ?: throw e
        }
    }

    suspend fun getConversationDetail(token: String, venueId: Long, conversationId: Long): ConversationDetail {
        return try {
            val detail = remote.fetchConversationDetail(token, venueId, conversationId)
            withContext(Dispatchers.IO) { local.cacheMessages(conversationId, detail.messages) }
            detail
        } catch (e: Exception) {
            val cached = withContext(Dispatchers.IO) { local.getCachedMessages(conversationId) } ?: throw e
            // Offline: show the thread, but claim no hold and no open window we can't vouch for.
            ConversationDetail(cached, null, null, 0, 0, isWindowActive = false)
        }
    }

    suspend fun sendMessage(token: String, venueId: Long, conversationId: Long, body: String): ChatMessage =
        remote.sendMessage(token, venueId, conversationId, body)

    suspend fun holdSlot(token: String, venueId: Long, conversationId: Long, courtId: Long, date: String, start: String, end: String): HoldResult =
        remote.holdSlot(token, venueId, conversationId, courtId, date, start, end)

    suspend fun releaseHold(token: String, venueId: Long, conversationId: Long): String =
        remote.releaseHold(token, venueId, conversationId)

    suspend fun sendPaymentLink(token: String, venueId: Long, conversationId: Long): Int =
        remote.sendPaymentLink(token, venueId, conversationId)

    suspend fun paymentStatus(token: String, venueId: Long, conversationId: Long): String =
        remote.paymentStatus(token, venueId, conversationId)

    suspend fun markPaid(token: String, venueId: Long, conversationId: Long, method: String): String =
        remote.markPaid(token, venueId, conversationId, method)

    /** The replies set up in /control. None is an honest empty list, not placeholder copy. */
    suspend fun getQuickReplies(token: String, venueId: Long): List<QuickReplyItem> =
        runCatching { remote.fetchQuickReplies(token, venueId) }.getOrDefault(emptyList())

    suspend fun getAvailability(token: String, venueId: Long, date: String): DayAvailability =
        remote.fetchAvailability(token, venueId, date)
}
