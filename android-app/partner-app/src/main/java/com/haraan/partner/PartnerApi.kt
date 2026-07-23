package com.haraan.partner

import android.content.Context
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL

/** Single source of truth for the backend base URL (from the build flavor). */
object ApiConfig {
    val BASE_URL: String = BuildConfig.API_BASE_URL.trimEnd('/')
}

/** Persists the partner's JWT + name across launches. */
class Session(context: Context) {
    private val prefs = context.getSharedPreferences("haraan_partner", Context.MODE_PRIVATE)

    var token: String?
        get() = prefs.getString("token", null)
        set(value) = prefs.edit().apply {
            if (value == null) remove("token") else putString("token", value)
        }.apply()

    var name: String?
        get() = prefs.getString("name", null)
        set(value) = prefs.edit().apply {
            if (value == null) remove("name") else putString("name", value)
        }.apply()

    /** 'event' (organiser) | 'venue' (owner) | null (combined fallback). */
    var partnerType: String?
        get() = prefs.getString("partner_type", null)
        set(value) = prefs.edit().apply {
            if (value == null) remove("partner_type") else putString("partner_type", value)
        }.apply()

    /** True when the signed-in user is a desk person (sub-user), not the owner. */
    var isDesk: Boolean
        get() = prefs.getBoolean("is_desk", false)
        set(value) = prefs.edit().putBoolean("is_desk", value).apply()

    /** Comma-joined granted capabilities for a desk person (owners ignore this). */
    var permissionsCsv: String?
        get() = prefs.getString("perms", null)
        set(value) = prefs.edit().apply {
            if (value == null) remove("perms") else putString("perms", value)
        }.apply()

    /** Owners may do everything; desk persons only their granted capabilities. */
    fun can(permission: String): Boolean {
        if (!isDesk) return true
        val set = permissionsCsv?.split(",")?.map { it.trim() }?.toSet() ?: emptySet()
        return permission in set
    }

    val isSignedIn: Boolean get() = !token.isNullOrBlank()

    fun clear() {
        token = null; name = null; partnerType = null
        isDesk = false; permissionsCsv = null
    }
}

/** Result of a successful sign-in. */
data class LoginResult(
    val token: String,
    val name: String,
    val partnerType: String?,
    val isDesk: Boolean,
    val permissions: List<String>,
)

// ---- API response models ------------------------------------------------

data class Overview(
    val name: String,
    val type: String?,
    val eventsTotal: Int,
    val eventsUpcoming: Int,
    val venuesTotal: Int,
    val revenue: Double,
    val ticketsSold: Int,
    val bookingsTotal: Int,
    val bookingsToday: Int,
    val online: Int,
    val offline: Int,
    val cancelled: Int,
    val trend: List<Double>,
)

data class EventSummary(
    val id: Long,
    val title: String,
    val category: String?,
    val date: String?,
    val time: String?,
    val status: String?,
    val totalSlots: Int,
    val seatsLeft: Int,
    val ticketsSold: Int,
    val revenue: Double,
)

data class VenueSummary(
    val id: Long,
    val name: String,
    val location: String?,
    val bookings: Int,
    val revenue: Double,
)

data class BookingSummary(
    val id: Long,
    val ticketCode: String?,
    val quantity: Int,
    val amount: Double,
    val status: String?,
    val checkedIn: Int,
    val label: String?,
)

/** Result of a scan-and-check-in. */
data class CheckInResult(val status: String, val message: String)

data class StatItem(val label: String, val value: String)

/** One day in a 14-day trend: revenue plus a secondary count (tickets or bookings). */
data class SalesPoint(val label: String, val revenue: Double, val secondary: Int)

data class TierRow(val name: String, val orders: Int, val tickets: Int, val revenue: Double, val pct: Int)

data class DayBooking(
    val id: Long,
    val customer: String,
    val phone: String?,
    val channel: String,
    val status: String,
    val checkedIn: Int,
    val amount: Double,
)

data class DaySlot(
    val slotId: Long,
    val label: String,
    val time: String?,
    val price: Double,
    val capacity: Int,
    val booked: Int,
    val available: Int,
    val isOpen: Boolean,
    val bookings: List<DayBooking>,
)

data class DayGrid(
    val date: String,
    val venueName: String,
    val isBlocked: Boolean,
    val slots: List<DaySlot>,
)

/** A desk person under a partner owner. */
data class StaffMember(val id: Long, val name: String, val email: String, val permissions: List<String>)

/** All capabilities an owner can grant a desk person. */
val STAFF_PERMISSIONS = listOf("bookings", "checkin", "pricing", "reports")

/** An editable price/slot row for the pricing screen. */
data class SlotEdit(
    val id: Long,
    val day: String?,
    val time: String,
    val price: Double,
    val capacity: Int,
    val isOpen: Boolean,
)

/** Unified analytics payload for either an event or a venue. */
data class Analytics(
    val title: String,
    val stats: List<StatItem>,
    val sales: List<SalesPoint>,
    val secondaryLabel: String,
    val tiers: List<TierRow>,
)

/** Raised for any non-2xx response, carrying a user-facing message. */
class ApiException(val code: Int, message: String) : Exception(message)

/**
 * Thin HttpURLConnection client for the partner endpoints. Mirrors the consumer
 * app's networking style (org.json parsing, no Retrofit). All calls are IO-bound
 * suspend functions.
 */
class PartnerApi(private val baseUrl: String = ApiConfig.BASE_URL) {

    /** POST /api/auth/login → returns the JWT + display name. */
    suspend fun login(email: String, password: String): LoginResult = withContext(Dispatchers.IO) {
        val payload = JSONObject().put("email", email).put("password", password)
        val body = post("/api/auth/login", payload.toString(), token = null)
        val o = JSONObject(body)
        val token = o.optString("token").ifBlank { throw ApiException(200, "Login response had no token") }
        val user = o.optJSONObject("user")
        val name = user?.optStringOrNull("name") ?: "Partner"
        val isDesk = user != null && !user.isNull("parentPartnerId")
        val permsArr = user?.optJSONArray("staffPermissions")
        val perms = if (permsArr == null) emptyList() else (0 until permsArr.length()).map { permsArr.optString(it) }
        LoginResult(token, name, user?.optStringOrNull("partnerType"), isDesk, perms)
    }

    suspend fun overview(token: String): Overview = withContext(Dispatchers.IO) {
        val o = JSONObject(get("/api/partner/overview", token))
        val events = o.getJSONObject("events")
        val venues = o.getJSONObject("venues")
        val sales = o.getJSONObject("sales")
        val trendArr = o.optJSONArray("trend")
        val trend = if (trendArr == null) emptyList() else (0 until trendArr.length()).map { trendArr.optDouble(it, 0.0) }
        val partner = o.optJSONObject("partner")
        Overview(
            name = partner?.optStringOrNull("name") ?: "Partner",
            type = partner?.optStringOrNull("type"),
            eventsTotal = events.optInt("total"),
            eventsUpcoming = events.optInt("upcoming"),
            venuesTotal = venues.optInt("total"),
            revenue = sales.optDouble("revenue", 0.0),
            ticketsSold = sales.optInt("tickets_sold"),
            bookingsTotal = sales.optInt("bookings_total"),
            bookingsToday = sales.optInt("bookings_today"),
            online = sales.optInt("online"),
            offline = sales.optInt("offline"),
            cancelled = sales.optInt("cancelled"),
            trend = trend,
        )
    }

    suspend fun events(token: String): List<EventSummary> = withContext(Dispatchers.IO) {
        parseArray(get("/api/partner/events", token)) { o ->
            EventSummary(
                id = o.optLong("id"),
                title = o.optString("title"),
                category = o.optStringOrNull("category"),
                date = o.optStringOrNull("date"),
                time = o.optStringOrNull("time"),
                status = o.optStringOrNull("status"),
                totalSlots = o.optInt("total_slots"),
                seatsLeft = o.optInt("seats_left"),
                ticketsSold = o.optInt("tickets_sold"),
                revenue = o.optDouble("revenue", 0.0),
            )
        }
    }

    suspend fun venues(token: String): List<VenueSummary> = withContext(Dispatchers.IO) {
        parseArray(get("/api/partner/venues", token)) { o ->
            VenueSummary(
                id = o.optLong("id"),
                name = o.optString("name"),
                location = o.optStringOrNull("location"),
                bookings = o.optInt("bookings"),
                revenue = o.optDouble("revenue", 0.0),
            )
        }
    }

    suspend fun bookings(token: String): List<BookingSummary> = withContext(Dispatchers.IO) {
        parseArray(get("/api/partner/bookings", token)) { o ->
            val label = o.optStringOrNull("event") ?: o.optStringOrNull("venue")
            BookingSummary(
                id = o.optLong("id"),
                ticketCode = o.optStringOrNull("ticket_code"),
                quantity = o.optInt("quantity"),
                amount = o.optDouble("amount", 0.0),
                status = o.optStringOrNull("status"),
                checkedIn = o.optInt("checked_in"),
                label = label,
            )
        }
    }

    suspend fun eventAnalytics(token: String, id: Long): Analytics = withContext(Dispatchers.IO) {
        val o = JSONObject(get("/api/partner/events/$id/analytics", token))
        val s = o.getJSONObject("stats")
        val stats = listOf(
            StatItem("Revenue", "₹" + fmtMoney(s.optDouble("revenue"))),
            StatItem("Paid orders", s.optInt("orders").toString()),
            StatItem("Attendees", s.optInt("attendees").toString()),
            StatItem("Avg / attendee", "₹" + fmtMoney(s.optDouble("avg_per_attendee"))),
            StatItem("Checked in", s.optInt("checked_in").toString()),
            StatItem("Show-up", s.optInt("show_up_pct").toString() + "%"),
            StatItem("No-shows", s.optInt("no_shows").toString()),
            StatItem("Fill", s.optInt("fill_pct").toString() + "%"),
            StatItem("Seats left", s.optInt("seats_left").toString()),
            StatItem("Views", s.optInt("views").toString()),
            StatItem("Conversion", s.optDouble("conversion_pct").toString() + "%"),
        )
        Analytics(
            title = o.optString("title"),
            stats = stats,
            sales = parseSales(o, "tickets"),
            secondaryLabel = "Tickets",
            tiers = parseTiers(o),
        )
    }

    suspend fun venueAnalytics(token: String, id: Long): Analytics = withContext(Dispatchers.IO) {
        val o = JSONObject(get("/api/partner/venues/$id/analytics", token))
        val s = o.getJSONObject("stats")
        val rating = if (s.isNull("rating")) "—" else s.optDouble("rating").toString()
        val stats = listOf(
            StatItem("Revenue", "₹" + fmtMoney(s.optDouble("revenue"))),
            StatItem("Bookings", s.optInt("bookings").toString()),
            StatItem("Avg booking", "₹" + fmtMoney(s.optDouble("avg_booking"))),
            StatItem("Utilization", s.optInt("utilization_pct").toString() + "%"),
            StatItem("Upcoming", s.optInt("upcoming").toString()),
            StatItem("Checked in", s.optInt("checked_in").toString()),
            StatItem("Show-up", s.optInt("show_up_pct").toString() + "%"),
            StatItem("Repeat", s.optInt("repeat_pct").toString() + "%"),
            StatItem("Slots", s.optInt("slots_offered").toString()),
            StatItem("Rating", rating),
            StatItem("Reviews", s.optInt("reviews").toString()),
        )
        Analytics(
            title = o.optString("name"),
            stats = stats,
            sales = parseSales(o, "bookings"),
            secondaryLabel = "Bookings",
            tiers = emptyList(),
        )
    }

    suspend fun venueDay(token: String, venueId: Long, date: String): DayGrid = withContext(Dispatchers.IO) {
        val o = JSONObject(get("/api/partner/venues/$venueId/day?date=$date", token))
        val slotsArr = o.optJSONArray("slots")
        val slots = if (slotsArr == null) emptyList() else (0 until slotsArr.length()).map { i ->
            val s = slotsArr.getJSONObject(i)
            val bArr = s.optJSONArray("bookings")
            val bookings = if (bArr == null) emptyList() else (0 until bArr.length()).map { j ->
                val b = bArr.getJSONObject(j)
                DayBooking(
                    id = b.optLong("id"),
                    customer = b.optString("customer"),
                    phone = b.optStringOrNull("phone"),
                    channel = b.optString("channel", "online"),
                    status = b.optString("status"),
                    checkedIn = b.optInt("checked_in"),
                    amount = b.optDouble("amount", 0.0),
                )
            }
            DaySlot(
                slotId = s.optLong("slot_id"),
                label = s.optString("label"),
                time = s.optStringOrNull("time"),
                price = s.optDouble("price", 0.0),
                capacity = s.optInt("capacity"),
                booked = s.optInt("booked"),
                available = s.optInt("available"),
                isOpen = s.optBoolean("is_open", true),
                bookings = bookings,
            )
        }
        DayGrid(
            date = o.optString("date"),
            venueName = o.optJSONObject("venue")?.optStringOrNull("name") ?: "Venue",
            isBlocked = o.optBoolean("is_blocked", false),
            slots = slots,
        )
    }

    suspend fun staff(token: String): List<StaffMember> = withContext(Dispatchers.IO) {
        parseArray(get("/api/partner/staff", token)) { o ->
            val permsArr = o.optJSONArray("permissions")
            val perms = if (permsArr == null) emptyList() else (0 until permsArr.length()).map { permsArr.optString(it) }
            StaffMember(o.optLong("id"), o.optString("name"), o.optString("email"), perms)
        }
    }

    suspend fun createStaff(token: String, name: String, email: String, password: String, permissions: List<String>) = withContext(Dispatchers.IO) {
        val payload = JSONObject().put("name", name).put("email", email).put("password", password)
            .put("permissions", org.json.JSONArray(permissions))
        post("/api/partner/staff", payload.toString(), token)
        Unit
    }

    suspend fun updateStaff(token: String, id: Long, permissions: List<String>) = withContext(Dispatchers.IO) {
        val payload = JSONObject().put("permissions", org.json.JSONArray(permissions))
        post("/api/partner/staff/$id", payload.toString(), token)
        Unit
    }

    suspend fun deleteStaff(token: String, id: Long) = withContext(Dispatchers.IO) {
        request("DELETE", "/api/partner/staff/$id", null, token)
        Unit
    }

    /** Fetch the booking report as raw CSV text for a date range. */
    suspend fun reportCsv(token: String, from: String, to: String): String = withContext(Dispatchers.IO) {
        get("/api/partner/reports/bookings?from=$from&to=$to&format=csv", token)
    }

    suspend fun venueSlots(token: String, venueId: Long): List<SlotEdit> = withContext(Dispatchers.IO) {
        parseArray(get("/api/partner/venues/$venueId/slots", token)) { o ->
            SlotEdit(
                id = o.optLong("id"),
                day = o.optStringOrNull("day"),
                time = o.optString("time"),
                price = o.optDouble("price", 0.0),
                capacity = o.optInt("capacity", 1),
                isOpen = o.optBoolean("is_open", true),
            )
        }
    }

    /** Create (slotId null) or update a slot. */
    suspend fun saveSlot(token: String, venueId: Long, slotId: Long?, day: String?, time: String, price: Double, capacity: Int, isOpen: Boolean) = withContext(Dispatchers.IO) {
        val payload = JSONObject()
            .put("time", time).put("price", price).put("capacity", capacity).put("isOpen", isOpen)
        if (!day.isNullOrBlank()) payload.put("day", day)
        val path = if (slotId == null) "/api/partner/venues/$venueId/slots" else "/api/partner/venues/$venueId/slots/$slotId"
        post(path, payload.toString(), token)
        Unit
    }

    suspend fun deleteSlot(token: String, venueId: Long, slotId: Long) = withContext(Dispatchers.IO) {
        request("DELETE", "/api/partner/venues/$venueId/slots/$slotId", null, token)
        Unit
    }

    suspend fun createWalkIn(token: String, venueId: Long, slotId: Long, date: String, name: String, phone: String) = withContext(Dispatchers.IO) {
        val payload = JSONObject()
            .put("slotId", slotId).put("date", date)
            .put("guestName", name).put("guestPhone", phone)
        post("/api/partner/venues/$venueId/bookings", payload.toString(), token)
        Unit
    }

    suspend fun cancelBooking(token: String, bookingId: Long) = withContext(Dispatchers.IO) {
        post("/api/partner/bookings/$bookingId/cancel", "{}", token)
        Unit
    }

    suspend fun setDateClosed(token: String, venueId: Long, date: String, closed: Boolean) = withContext(Dispatchers.IO) {
        if (closed) {
            post("/api/partner/venues/$venueId/block", JSONObject().put("date", date).toString(), token)
        } else {
            request("DELETE", "/api/partner/venues/$venueId/block?date=$date", null, token)
        }
        Unit
    }

    private fun parseSales(o: JSONObject, secondaryKey: String): List<SalesPoint> {
        val arr = o.optJSONArray("sales") ?: return emptyList()
        return (0 until arr.length()).map { i ->
            val p = arr.getJSONObject(i)
            SalesPoint(p.optString("label"), p.optDouble("revenue", 0.0), p.optInt(secondaryKey))
        }
    }

    private fun parseTiers(o: JSONObject): List<TierRow> {
        val arr = o.optJSONArray("by_tier") ?: return emptyList()
        return (0 until arr.length()).map { i ->
            val t = arr.getJSONObject(i)
            TierRow(t.optString("name"), t.optInt("orders"), t.optInt("tickets"), t.optDouble("revenue", 0.0), t.optInt("pct"))
        }
    }

    private fun fmtMoney(v: Double): String =
        if (v == v.toLong().toDouble()) v.toLong().toString() else String.format("%.2f", v)

    /** POST /api/partner/check-in — resolve + mark arrived by scanned code. */
    suspend fun checkIn(token: String, code: String): CheckInResult = withContext(Dispatchers.IO) {
        val payload = JSONObject().put("code", code)
        val body = post("/api/partner/check-in", payload.toString(), token)
        val o = JSONObject(body)
        val status = o.optString("status", "ok")
        val message = when (status) {
            "ok" -> "Checked in"
            "already" -> "Already checked in"
            "invalid" -> "Ticket is cancelled/invalid"
            else -> "Done"
        }
        CheckInResult(status, message)
    }

    // ---- HTTP plumbing --------------------------------------------------

    private fun get(path: String, token: String): String =
        request("GET", path, null, token)

    private fun post(path: String, json: String, token: String?): String =
        request("POST", path, json, token)

    private fun request(method: String, path: String, json: String?, token: String?): String {
        val conn = (URL(baseUrl + path).openConnection() as HttpURLConnection).apply {
            requestMethod = method
            connectTimeout = 15000
            readTimeout = 15000
            setRequestProperty("Accept", "application/json")
            token?.let { setRequestProperty("Authorization", "Bearer $it") }
            if (json != null) {
                doOutput = true
                setRequestProperty("Content-Type", "application/json")
            }
        }
        try {
            if (json != null) conn.outputStream.use { it.write(json.toByteArray()) }
            val code = conn.responseCode
            val stream = if (code in 200..299) conn.inputStream else conn.errorStream
            val body = stream?.let { BufferedReader(InputStreamReader(it)).use(BufferedReader::readText) } ?: ""
            if (code !in 200..299) throw ApiException(code, parseError(body, code))
            return body
        } finally {
            conn.disconnect()
        }
    }

    private fun parseError(body: String, code: Int): String = try {
        JSONObject(body).let { it.optString("error").ifBlank { it.optString("message") } }
            .ifBlank { defaultError(code) }
    } catch (_: Exception) {
        defaultError(code)
    }

    private fun defaultError(code: Int): String = when (code) {
        400, 401 -> "Invalid email or password"
        403 -> "This account is not a partner account"
        404 -> "Not found"
        else -> "Something went wrong (HTTP $code)"
    }

    private inline fun <T> parseArray(body: String, map: (JSONObject) -> T): List<T> {
        val arr: JSONArray = JSONObject(body).optJSONArray("data") ?: return emptyList()
        return (0 until arr.length()).map { map(arr.getJSONObject(it)) }
    }
}

private fun JSONObject.optStringOrNull(key: String): String? =
    if (isNull(key)) null else optString(key).takeIf { it.isNotBlank() && it != "null" }

/** Formats a rupee amount with Indian digit grouping, e.g. 120000.0 -> "1,20,000". */
fun formatInr(v: Double): String {
    val n = kotlin.math.abs(v).toLong()
    val s = n.toString()
    val grouped = if (s.length <= 3) s else {
        val last3 = s.takeLast(3)
        var rest = s.dropLast(3)
        val parts = mutableListOf<String>()
        while (rest.length > 2) { parts.add(0, rest.takeLast(2)); rest = rest.dropLast(2) }
        parts.add(0, rest)
        parts.joinToString(",") + "," + last3
    }
    return (if (v < 0) "-" else "") + grouped
}
