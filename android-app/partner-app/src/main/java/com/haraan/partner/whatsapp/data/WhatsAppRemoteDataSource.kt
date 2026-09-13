package com.haraan.partner.whatsapp.data

import com.haraan.partner.whatsapp.model.*
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL

class WhatsAppRemoteDataSource(
    private val baseUrl: String = "http://10.0.2.2:8000"
) {

    suspend fun fetchDashboard(token: String, venueId: Long): WhatsAppMetrics = withContext(Dispatchers.IO) {
        val endpoint = "$baseUrl/api/partner/venues/$venueId/whatsapp/dashboard"
        val responseStr = makeHttpRequest("GET", endpoint, token)
        val root = JSONObject(responseStr)
        val data = root.optJSONObject("data") ?: JSONObject()

        WhatsAppMetrics(
            unreadConversations = data.optInt("unread_conversations", 0),
            activeHoldsCount = data.optInt("active_holds_count", 0),
            convertedTodayCount = data.optInt("converted_today_count", 0),
            totalDeskRevenueToday = data.optDouble("total_desk_revenue_today", 0.0),
            conversionRatePercent = data.optDouble("conversion_rate_percent", 0.0),
            activeWindowOpenCount = data.optInt("active_window_open_count", 0)
        )
    }

    suspend fun fetchConversations(
        token: String,
        venueId: Long,
        status: String? = null,
        searchQuery: String? = null,
        page: Int = 1
    ): List<ConversationSummary> = withContext(Dispatchers.IO) {
        var endpoint = "$baseUrl/api/partner/venues/$venueId/whatsapp/conversations?page=$page"
        if (!status.isNullOrEmpty() && status != "all") {
            endpoint += "&status=$status"
        }
        if (!searchQuery.isNullOrEmpty()) {
            endpoint += "&q=" + java.net.URLEncoder.encode(searchQuery, "UTF-8")
        }

        val responseStr = makeHttpRequest("GET", endpoint, token)
        val root = JSONObject(responseStr)
        val arr = root.optJSONArray("data") ?: JSONArray()

        val list = mutableListOf<ConversationSummary>()
        for (i in 0 until arr.length()) {
            val obj = arr.getJSONObject(i)
            val intentObj = obj.optJSONObject("latest_intent")
            var intentSummary: IntentSummary? = null
            if (intentObj != null) {
                intentSummary = IntentSummary(
                    intentType = intentObj.optString("intent_type", "booking_enquiry"),
                    sport = intentObj.optString("detected_sport").takeIf { it.isNotEmpty() },
                    date = intentObj.optString("detected_date").takeIf { it.isNotEmpty() },
                    startTime = intentObj.optString("detected_start_time").takeIf { it.isNotEmpty() },
                    endTime = intentObj.optString("detected_end_time").takeIf { it.isNotEmpty() },
                    courtId = if (intentObj.isNull("resolved_court_id")) null else intentObj.getLong("resolved_court_id"),
                    courtName = intentObj.optJSONObject("resolved_court")?.optString("name") ?: intentObj.optString("detected_court_name").takeIf { it.isNotEmpty() },
                    calculatedRate = if (intentObj.isNull("calculated_rate")) null else intentObj.getDouble("calculated_rate"),
                    confidence = intentObj.optDouble("confidence_score", 0.0).toFloat(),
                    actionState = intentObj.optString("action_state", "suggested")
                )
            }

            list.add(
                ConversationSummary(
                    id = obj.getLong("id"),
                    venueId = obj.getLong("venue_id"),
                    phoneNumber = obj.getString("phone_number"),
                    customerName = obj.optString("customer_name").takeIf { it.isNotEmpty() },
                    status = obj.getString("status"),
                    assignedStaffId = if (obj.isNull("assigned_staff_id")) null else obj.getLong("assigned_staff_id"),
                    assignedStaffName = obj.optJSONObject("assigned_staff")?.optString("name"),
                    lastMessageAt = obj.optString("last_message_at").takeIf { it.isNotEmpty() },
                    lastMessagePreview = obj.optString("last_message_preview").takeIf { it.isNotEmpty() },
                    lastMessageSender = obj.optString("last_message_sender", "customer"),
                    unreadCount = obj.optInt("unread_count", 0),
                    windowExpiresAt = obj.optString("window_expires_at").takeIf { it.isNotEmpty() },
                    isWindowActive = obj.optBoolean("is_window_active", true),
                    activeBookingId = if (obj.isNull("active_booking_id")) null else obj.getLong("active_booking_id"),
                    latestIntent = intentSummary
                )
            )
        }
        list
    }

    suspend fun fetchConversationDetail(
        token: String,
        venueId: Long,
        conversationId: Long
    ): Pair<List<ChatMessage>, BookingSuggestion?> = withContext(Dispatchers.IO) {
        val endpoint = "$baseUrl/api/partner/venues/$venueId/whatsapp/conversations/$conversationId"
        val responseStr = makeHttpRequest("GET", endpoint, token)
        val root = JSONObject(responseStr)
        val data = root.optJSONObject("data") ?: JSONObject()

        val msgsArr = data.optJSONArray("messages") ?: JSONArray()
        val messages = mutableListOf<ChatMessage>()
        for (i in 0 until msgsArr.length()) {
            val obj = msgsArr.getJSONObject(i)
            val payload = obj.optJSONObject("raw_payload")
            messages.add(
                ChatMessage(
                    id = obj.getLong("id"),
                    conversationId = obj.getLong("conversation_id"),
                    direction = obj.getString("direction"),
                    senderType = obj.getString("sender_type"),
                    messageType = obj.getString("message_type"),
                    body = obj.getString("body"),
                    mediaUrl = obj.optString("media_url").takeIf { it.isNotEmpty() },
                    deliveryStatus = obj.optString("delivery_status", "sent"),
                    createdAt = obj.optString("created_at", ""),
                    ticketCode = payload?.optString("ticket_code"),
                    ticketUrl = payload?.optString("ticket_url"),
                    paymentUrl = payload?.optString("short_url")
                )
            )
        }

        // Active hold & intent suggestions
        val holdSecondsLeft = data.optInt("active_hold_seconds_left", 0)
        val convObj = data.optJSONObject("conversation")
        val activeBooking = convObj?.optJSONObject("active_booking")
        val latestIntent = convObj?.optJSONObject("latest_intent")

        var suggestion: BookingSuggestion? = null
        if (activeBooking != null) {
            val courtObj = activeBooking.optJSONObject("venue_court")
            suggestion = BookingSuggestion(
                conversationId = conversationId,
                sport = "Cricket",
                courtId = activeBooking.optLong("venue_court_id", 1L),
                courtName = courtObj?.optString("name") ?: "Court 1",
                date = activeBooking.optString("slot_date", ""),
                startTime = activeBooking.optString("start_time", "18:00:00"),
                endTime = activeBooking.optString("end_time", "19:00:00"),
                price = activeBooking.optDouble("total_amount", 1000.0),
                isAvailable = true,
                activeHoldSecondsRemaining = holdSecondsLeft
            )
        } else if (latestIntent != null) {
            val courtObj = latestIntent.optJSONObject("resolved_court")
            suggestion = BookingSuggestion(
                conversationId = conversationId,
                sport = latestIntent.optString("detected_sport", "Cricket"),
                courtId = latestIntent.optLong("resolved_court_id", 1L),
                courtName = courtObj?.optString("name") ?: latestIntent.optString("detected_court_name", "Court 1"),
                date = latestIntent.optString("detected_date", ""),
                startTime = latestIntent.optString("detected_start_time", "18:00:00"),
                endTime = latestIntent.optString("detected_end_time", "19:00:00"),
                durationMinutes = latestIntent.optInt("detected_duration_minutes", 60),
                price = latestIntent.optDouble("calculated_rate", 1000.0),
                isAvailable = true,
                activeHoldSecondsRemaining = 0
            )
        }

        Pair(messages, suggestion)
    }

    suspend fun sendMessage(
        token: String,
        venueId: Long,
        conversationId: Long,
        body: String,
        messageType: String = "text"
    ): ChatMessage = withContext(Dispatchers.IO) {
        val endpoint = "$baseUrl/api/partner/venues/$venueId/whatsapp/conversations/$conversationId/messages"
        val payload = JSONObject().apply {
            put("body", body)
            put("message_type", messageType)
        }
        val responseStr = makeHttpRequest("POST", endpoint, token, payload.toString())
        val root = JSONObject(responseStr)
        val data = root.optJSONObject("data") ?: JSONObject()

        ChatMessage(
            id = data.optLong("id", System.currentTimeMillis()),
            conversationId = conversationId,
            direction = "outbound",
            senderType = "partner",
            messageType = messageType,
            body = body,
            deliveryStatus = "sent",
            createdAt = "Just now"
        )
    }

    suspend fun holdSlot(
        token: String,
        venueId: Long,
        conversationId: Long,
        req: HoldSlotRequest
    ): Triple<Long, String, Int> = withContext(Dispatchers.IO) {
        val endpoint = "$baseUrl/api/partner/venues/$venueId/whatsapp/conversations/$conversationId/hold-slot"
        val payload = JSONObject().apply {
            put("court_id", req.courtId)
            put("slot_date", req.slotDate)
            put("start_time", req.startTime)
            put("end_time", req.endTime)
            put("price", req.price)
        }
        val responseStr = makeHttpRequest("POST", endpoint, token, payload.toString())
        val root = JSONObject(responseStr)
        val data = root.optJSONObject("data") ?: JSONObject()

        Triple(
            data.getLong("booking_id"),
            data.getString("reserved_until"),
            data.optInt("seconds_remaining", com.haraan.partner.ui.theme.HaraanTheme.DEFAULT_HOLD_DURATION_SECONDS)
        )
    }

    suspend fun releaseHold(token: String, venueId: Long, conversationId: Long): Boolean = withContext(Dispatchers.IO) {
        val endpoint = "$baseUrl/api/partner/venues/$venueId/whatsapp/conversations/$conversationId/release-hold"
        val responseStr = makeHttpRequest("POST", endpoint, token, "{}")
        JSONObject(responseStr).optString("status") == "success"
    }

    suspend fun sendPaymentLink(
        token: String,
        venueId: Long,
        conversationId: Long,
        amount: Double? = null
    ): Boolean = withContext(Dispatchers.IO) {
        val endpoint = "$baseUrl/api/partner/venues/$venueId/whatsapp/conversations/$conversationId/send-payment-link"
        val payload = JSONObject()
        if (amount != null) payload.put("amount", amount)

        val responseStr = makeHttpRequest("POST", endpoint, token, payload.toString())
        JSONObject(responseStr).optString("status") == "success"
    }

    suspend fun markPaidManual(
        token: String,
        venueId: Long,
        conversationId: Long,
        method: String = "cash"
    ): String = withContext(Dispatchers.IO) {
        val endpoint = "$baseUrl/api/partner/venues/$venueId/whatsapp/conversations/$conversationId/mark-paid"
        val payload = JSONObject().apply {
            put("method", method)
        }
        val responseStr = makeHttpRequest("POST", endpoint, token, payload.toString())
        val root = JSONObject(responseStr)
        val data = root.optJSONObject("data") ?: JSONObject()
        data.optString("ticket_code", "CONFIRMED")
    }

    suspend fun fetchQuickReplies(token: String, venueId: Long): List<QuickReplyItem> = withContext(Dispatchers.IO) {
        val endpoint = "$baseUrl/api/partner/venues/$venueId/whatsapp/quick-replies"
        val responseStr = makeHttpRequest("GET", endpoint, token)
        val root = JSONObject(responseStr)
        val arr = root.optJSONArray("data") ?: JSONArray()

        val list = mutableListOf<QuickReplyItem>()
        for (i in 0 until arr.length()) {
            val obj = arr.getJSONObject(i)
            list.add(
                QuickReplyItem(
                    id = obj.getLong("id"),
                    shortcut = obj.getString("shortcut"),
                    category = obj.getString("category"),
                    title = obj.getString("title"),
                    body = obj.getString("body")
                )
            )
        }
        list
    }

    private fun makeHttpRequest(method: String, urlStr: String, token: String, bodyJson: String? = null): String {
        val url = URL(urlStr)
        val conn = url.openConnection() as HttpURLConnection
        conn.requestMethod = method
        conn.setRequestProperty("Accept", "application/json")
        conn.setRequestProperty("Authorization", "Bearer $token")
        conn.connectTimeout = 6000
        conn.readTimeout = 10000

        if (bodyJson != null && (method == "POST" || method == "PUT" || method == "PATCH")) {
            conn.doOutput = true
            conn.setRequestProperty("Content-Type", "application/json")
            conn.outputStream.use { os ->
                os.write(bodyJson.toByteArray(Charsets.UTF_8))
            }
        }

        val code = conn.responseCode
        val stream = if (code in 200..299) conn.inputStream else conn.errorStream
            ?: throw Exception("HTTP $code without stream")

        val responseStr = BufferedReader(InputStreamReader(stream, Charsets.UTF_8)).use { it.readText() }
        if (code !in 200..299) {
            throw Exception("HTTP $code: $responseStr")
        }
        return responseStr
    }
}