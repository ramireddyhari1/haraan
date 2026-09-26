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
import java.net.URLEncoder

class WhatsAppRemoteDataSource(
    // The app's one server address. This was "http://10.0.2.2:8000" — the emulator's
    // alias for a developer laptop — so on every real phone this screen could
    // never load; see ApiConfig.
    private val baseUrl: String = com.haraan.partner.ApiConfig.BASE_URL
) {

    private fun venuePath(venueId: Long) = "$baseUrl/api/partner/venues/$venueId/whatsapp"

    suspend fun fetchDashboard(token: String, venueId: Long): WhatsAppMetrics = withContext(Dispatchers.IO) {
        val data = JSONObject(request("GET", "${venuePath(venueId)}/dashboard", token)).optJSONObject("data") ?: JSONObject()

        WhatsAppMetrics(
            unreadConversations = data.optInt("unread_conversations", 0),
            needsActionCount = data.optInt("needs_action_count", 0),
            activeHoldsCount = data.optInt("active_holds_count", 0),
            convertedTodayCount = data.optInt("converted_today_count", 0),
            totalDeskRevenueToday = data.optDouble("total_desk_revenue_today", 0.0),
            conversionRatePercent = data.optDouble("conversion_rate_percent", 0.0),
            activeWindowOpenCount = data.optInt("active_window_open_count", 0),
            holdMinutes = data.optInt("hold_minutes", 0)
        )
    }

    suspend fun fetchConversations(
        token: String,
        venueId: Long,
        status: String? = null,
        searchQuery: String? = null,
        page: Int = 1
    ): List<ConversationSummary> = withContext(Dispatchers.IO) {
        var endpoint = "${venuePath(venueId)}/conversations?page=$page"
        if (!status.isNullOrEmpty() && status != "all") {
            endpoint += "&status=" + URLEncoder.encode(status, "UTF-8")
        }
        if (!searchQuery.isNullOrBlank()) {
            endpoint += "&q=" + URLEncoder.encode(searchQuery.trim(), "UTF-8")
        }

        val arr = JSONObject(request("GET", endpoint, token)).optJSONArray("data") ?: JSONArray()

        (0 until arr.length()).map { i ->
            val obj = arr.getJSONObject(i)
            ConversationSummary(
                id = obj.getLong("id"),
                venueId = obj.getLong("venue_id"),
                phoneNumber = obj.getString("phone_number"),
                customerName = obj.str("customer_name"),
                status = obj.optString("status", "active"),
                assignedStaffId = obj.long("assigned_staff_id"),
                assignedStaffName = obj.optJSONObject("assigned_staff")?.str("name"),
                lastMessageAt = obj.str("last_message_at"),
                lastMessagePreview = obj.str("last_message_preview"),
                lastMessageSender = obj.optString("last_message_sender", "customer"),
                unreadCount = obj.optInt("unread_count", 0),
                windowExpiresAt = obj.str("window_expires_at"),
                isWindowActive = obj.optBoolean("is_window_active", false),
                activeBookingId = obj.long("active_booking_id"),
                latestIntent = obj.optJSONObject("latest_intent")?.let { intent ->
                    IntentSummary(
                        intentType = intent.optString("intent_type", "general_faq"),
                        sport = intent.str("detected_sport"),
                        date = intent.str("detected_date")?.take(10),
                        startTime = intent.str("detected_start_time"),
                        endTime = intent.str("detected_end_time"),
                        courtId = intent.long("resolved_court_id"),
                        courtName = intent.optJSONObject("resolved_court")?.str("name"),
                        calculatedRate = intent.dbl("calculated_rate"),
                        confidence = intent.optDouble("confidence_score", 0.0).toFloat(),
                        actionState = intent.optString("action_state", "suggested")
                    )
                }
            )
        }
    }

    suspend fun fetchConversationDetail(token: String, venueId: Long, conversationId: Long): ConversationDetail =
        withContext(Dispatchers.IO) {
            val data = JSONObject(request("GET", "${venuePath(venueId)}/conversations/$conversationId", token))
                .optJSONObject("data") ?: JSONObject()

            val msgsArr = data.optJSONArray("messages") ?: JSONArray()
            val messages = (0 until msgsArr.length()).map { parseMessage(msgsArr.getJSONObject(it), conversationId) }

            val intent = data.optJSONObject("conversation")?.optJSONObject("latest_intent")
            val suggestion = intent
                // A suggestion already acted on (held, booked, dismissed) isn't offered again.
                ?.takeIf { it.optString("action_state", "suggested") == "suggested" }
                ?.takeIf { it.optString("intent_type") == "booking_enquiry" }
                ?.let {
                    BookingSuggestion(
                        sport = it.str("detected_sport"),
                        courtId = it.long("resolved_court_id"),
                        courtName = it.optJSONObject("resolved_court")?.str("name"),
                        date = it.str("detected_date")?.take(10),
                        startTime = it.str("detected_start_time"),
                        endTime = it.str("detected_end_time"),
                        price = it.dbl("calculated_rate")
                    )
                }

            ConversationDetail(
                messages = messages,
                suggestion = suggestion,
                hold = data.optJSONObject("hold")?.takeIf { it.optString("status") == "PENDING" }?.let(::parseHold),
                holdSecondsLeft = data.optInt("active_hold_seconds_left", 0),
                holdTotalSeconds = data.optInt("hold_total_seconds", 0),
                isWindowActive = data.optBoolean("is_window_active", false)
            )
        }

    suspend fun sendMessage(token: String, venueId: Long, conversationId: Long, body: String): ChatMessage =
        withContext(Dispatchers.IO) {
            val payload = JSONObject().put("body", body)
            val data = JSONObject(request("POST", "${venuePath(venueId)}/conversations/$conversationId/messages", token, payload))
                .optJSONObject("data") ?: JSONObject()
            parseMessage(data, conversationId)
        }

    suspend fun holdSlot(
        token: String,
        venueId: Long,
        conversationId: Long,
        courtId: Long,
        date: String,
        startTime: String,
        endTime: String
    ): HoldResult = withContext(Dispatchers.IO) {
        val payload = JSONObject()
            .put("court_id", courtId)
            .put("slot_date", date)
            .put("start_time", startTime)
            .put("end_time", endTime)
        val data = JSONObject(request("POST", "${venuePath(venueId)}/conversations/$conversationId/hold-slot", token, payload))
            .optJSONObject("data") ?: JSONObject()

        HoldResult(
            hold = parseHold(data),
            secondsRemaining = data.optInt("seconds_remaining", 0),
            holdTotalSeconds = data.optInt("hold_total_seconds", 0)
        )
    }

    /** "released", or "paid" when the customer had already paid the link. */
    suspend fun releaseHold(token: String, venueId: Long, conversationId: Long): String = withContext(Dispatchers.IO) {
        JSONObject(request("POST", "${venuePath(venueId)}/conversations/$conversationId/release-hold", token, JSONObject()))
            .optString("outcome", "released")
    }

    /** Seconds left on the hold, which sending the link restarts. */
    suspend fun sendPaymentLink(token: String, venueId: Long, conversationId: Long): Int = withContext(Dispatchers.IO) {
        JSONObject(request("POST", "${venuePath(venueId)}/conversations/$conversationId/send-payment-link", token, JSONObject()))
            .optJSONObject("data")?.optInt("seconds_remaining", 0) ?: 0
    }

    /** "paid", "unpaid", "unknown" (Razorpay unreachable) or "no_link". */
    suspend fun paymentStatus(token: String, venueId: Long, conversationId: Long): String = withContext(Dispatchers.IO) {
        JSONObject(request("POST", "${venuePath(venueId)}/conversations/$conversationId/payment-status", token, JSONObject()))
            .optString("state", "unknown")
    }

    /** The booking code the customer was sent. */
    suspend fun markPaid(token: String, venueId: Long, conversationId: Long, method: String): String = withContext(Dispatchers.IO) {
        val payload = JSONObject().put("method", method)
        JSONObject(request("POST", "${venuePath(venueId)}/conversations/$conversationId/mark-paid", token, payload))
            .optJSONObject("data")?.optString("ticket_code", "") ?: ""
    }

    suspend fun fetchQuickReplies(token: String, venueId: Long): List<QuickReplyItem> = withContext(Dispatchers.IO) {
        val arr = JSONObject(request("GET", "${venuePath(venueId)}/quick-replies", token)).optJSONArray("data") ?: JSONArray()
        (0 until arr.length()).map { i ->
            val obj = arr.getJSONObject(i)
            QuickReplyItem(
                id = obj.getLong("id"),
                shortcut = obj.optString("shortcut"),
                category = obj.optString("category"),
                title = obj.optString("title"),
                body = obj.optString("body")
            )
        }
    }

    suspend fun fetchAvailability(token: String, venueId: Long, date: String): DayAvailability = withContext(Dispatchers.IO) {
        val data = JSONObject(request("GET", "${venuePath(venueId)}/availability?date=$date", token))
            .optJSONObject("data") ?: JSONObject()
        val courtsArr = data.optJSONArray("courts") ?: JSONArray()

        DayAvailability(
            date = data.optString("date", date),
            slotMinutes = data.optInt("slot_minutes", 60),
            hoursMissing = data.optBoolean("hours_missing", false),
            courts = (0 until courtsArr.length()).map { i ->
                val c = courtsArr.getJSONObject(i)
                val slotsArr = c.optJSONArray("slots") ?: JSONArray()
                CourtAvailabilityItem(
                    courtId = c.getLong("court_id"),
                    courtName = c.optString("court_name"),
                    slots = (0 until slotsArr.length()).map { j ->
                        val s = slotsArr.getJSONObject(j)
                        CourtSlotAvailability(
                            startTime = s.optString("start_time"),
                            endTime = s.optString("end_time"),
                            timeLabel = s.optString("time_label"),
                            isAvailable = s.optBoolean("is_available", false),
                            isPast = s.optBoolean("is_past", false),
                            price = s.optDouble("price", 0.0)
                        )
                    }
                )
            }
        )
    }

    private fun parseMessage(obj: JSONObject, conversationId: Long) = ChatMessage(
        id = obj.optLong("id", 0L),
        conversationId = obj.optLong("conversation_id", conversationId),
        direction = obj.optString("direction", "outbound"),
        senderType = obj.optString("sender_type", "partner"),
        messageType = obj.optString("message_type", "text"),
        body = obj.optString("body", ""),
        mediaUrl = obj.str("media_url"),
        deliveryStatus = obj.optString("delivery_status", "sent"),
        createdAt = obj.optString("created_at", "")
    )

    private fun parseHold(obj: JSONObject) = ActiveHold(
        bookingId = obj.optLong("booking_id"),
        courtId = obj.long("court_id"),
        courtName = obj.str("court_name"),
        date = obj.optString("slot_date").take(10),
        startTime = obj.optString("start_time"),
        endTime = obj.optString("end_time"),
        amount = obj.optDouble("amount", 0.0),
        linkStatus = obj.str("link_status"),
        linkUrl = obj.str("link_url")
    )

    private fun request(method: String, urlStr: String, token: String, body: JSONObject? = null): String {
        val conn = URL(urlStr).openConnection() as HttpURLConnection
        conn.requestMethod = method
        conn.setRequestProperty("Accept", "application/json")
        conn.setRequestProperty("Authorization", "Bearer $token")
        conn.connectTimeout = 8000
        // Creating a payment link waits on Razorpay (up to 20s server-side).
        conn.readTimeout = 25000

        try {
            if (body != null) {
                conn.doOutput = true
                conn.setRequestProperty("Content-Type", "application/json")
                conn.outputStream.use { it.write(body.toString().toByteArray(Charsets.UTF_8)) }
            }

            val code = conn.responseCode
            val stream = (if (code in 200..299) conn.inputStream else conn.errorStream)
                ?: throw DeskApiException("Server error ($code)", code)
            val text = BufferedReader(InputStreamReader(stream, Charsets.UTF_8)).use { it.readText() }

            if (code !in 200..299) {
                throw DeskApiException(serverMessage(text) ?: "Server error ($code)", code)
            }
            return text
        } finally {
            conn.disconnect()
        }
    }

    /** Laravel's own words: the first validation error, else `message`. */
    private fun serverMessage(text: String): String? = runCatching {
        val root = JSONObject(text)
        root.optJSONObject("errors")?.let { errors ->
            errors.keys().asSequence().firstOrNull()?.let { key -> errors.optJSONArray(key)?.optString(0) }
        } ?: root.str("message") ?: root.str("error")
    }.getOrNull()

    private fun JSONObject.str(key: String): String? =
        if (isNull(key)) null else optString(key).takeIf { it.isNotBlank() }

    private fun JSONObject.long(key: String): Long? =
        if (isNull(key) || !has(key)) null else optLong(key)

    private fun JSONObject.dbl(key: String): Double? =
        if (isNull(key) || !has(key)) null else optDouble(key).takeUnless { it.isNaN() }
}
