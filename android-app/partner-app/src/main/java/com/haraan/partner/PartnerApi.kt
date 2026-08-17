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

    /**
     * The branch the console is currently looking at, or null for "all branches".
     *
     * Stored as 0 rather than removed when cleared, so "all branches" is a real
     * remembered choice and not indistinguishable from a fresh install. The
     * selection is only ever a filter — the server decides what this account may
     * actually reach, so a stale id here can leak nothing.
     */
    var branchId: Long?
        get() = prefs.getLong("branch_id", 0L).takeIf { it > 0L }
        set(value) = prefs.edit().putLong("branch_id", value ?: 0L).apply()

    /** Highest booking id we've already surfaced as a "new booking" alert. */
    var lastNotifiedBookingId: Long
        get() = prefs.getLong("last_notified_booking", 0L)
        set(value) = prefs.edit().putLong("last_notified_booking", value).apply()

    val isSignedIn: Boolean get() = !token.isNullOrBlank()

    fun clear() {
        token = null; name = null; partnerType = null
        isDesk = false; permissionsCsv = null
        branchId = null
        lastNotifiedBookingId = 0L
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

/** Result of starting a phone-OTP: which channel carried the code, and its token. */
data class PhoneOtpStart(val channel: String, val token: String?)

// ---- API response models ------------------------------------------------

/**
 * One outlet of the business. `branch` is what to SHOW — a chain's venues all
 * share the brand name ("Big Bean Coffee"), so a list of `name` reads as the
 * same word three times.
 */
data class Branch(
    val id: Long,
    val name: String,
    val branch: String,
    val code: String?,
    val kind: String,
    val city: String?,
    val isActive: Boolean,
    val capabilities: List<String>,
)

/**
 * The shell: who this is, what they run, and which branches they may act on.
 * The first call after sign-in — the app never infers its own shape.
 *
 * `altitude` (owner | manager | desk) decides layout only. A client that ignores
 * it and calls a branch endpoint it shouldn't still gets a 404 from the server.
 */
data class PartnerContext(
    val businessName: String,
    /** Stored partner type: 'event' | 'venue' | 'cafe'. One dimension, not two. */
    val businessType: String,
    /** Server-rendered label for it ("Café venue"), so the app holds no mapping. */
    val typeLabel: String?,
    /** The console this partner mounts: 'events' | 'gamehub' | 'cafe'. */
    val lane: String?,
    val capabilities: List<String>,
    val altitude: String,
    val permissions: List<String>,
    val branches: List<Branch>,
) {
    /** A switcher with one option is chrome that does nothing. */
    val isMultiBranch: Boolean get() = branches.size > 1

    fun branchName(id: Long?): String =
        branches.firstOrNull { it.id == id }?.branch ?: "All branches"
}

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
    /** First venue photo, already resolved to an absolute URL. Null = no photo yet. */
    val image: String? = null,
    val sports: List<String> = emptyList(),
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
    /** Which outlet took it. Null for event bookings, which have no branch. */
    val branch: String? = null,
    val customer: String = "Guest",
    /** online | offline (walk-in). */
    val channel: String = "online",
    val paymentStatus: String = "paid",
    val slotDate: String? = null,
    val slotLabel: String? = null,
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
    val amountPaid: Double = 0.0,
    /** unpaid | part | paid */
    val paymentStatus: String = "unpaid",
)

/** How the desk took the money for a walk-in. */
enum class PayMethod(val api: String, val label: String) {
    CASH("cash", "Cash"),
    UPI("upi", "UPI"),
    CARD("card", "Card"),
    LINK("link", "Payment link"),
    PACKAGE("package", "Use a session"),
}

/** Outcome of creating a walk-in: the booking, plus a Razorpay link when asked for. */
data class WalkInResult(
    val bookingId: Long,
    val amount: Double,
    val paymentMethod: String,
    val paymentLink: String?,
    val paymentLinkId: String?,
)

/** Live payment state of a walk-in's link, straight from Razorpay. */
data class PayState(val paid: Boolean, val status: String)

/** One court column in the day grid. */
data class CourtCol(val id: Long, val name: String, val sports: List<String>)

/** One court × slot cell: is this court free or booked for this time. */
data class CourtCell(
    val courtId: Long,
    val booked: Int,
    val isBooked: Boolean,
    /** The rate this cell would actually charge — peak included. */
    val price: Double,
    val isPeak: Boolean = false,
    val bookings: List<DayBooking>,
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
    val courts: List<CourtCell> = emptyList(),
)

data class DayGrid(
    val date: String,
    val venueName: String,
    val isBlocked: Boolean,
    val slots: List<DaySlot>,
    val courts: List<CourtCol> = emptyList(),
)

/** One bell-inbox notification from the Haraan team. */
data class NotificationRow(
    val id: Long,
    val title: String,
    val body: String?,
    val read: Boolean,
    val createdAt: String?,
)

data class NotificationsPage(val unread: Int, val items: List<NotificationRow>)

/** One message in the partner↔admin support thread. */
data class SupportMessage(val id: Long, val body: String, val fromAdmin: Boolean, val createdAt: String?)

/** A coaching batch: coach, weekdays, time, monthly fee, roster health. */
data class BatchRow(
    val id: Long,
    val name: String,
    val coach: String?,
    val sport: String?,
    val days: List<String>,
    val startTime: String?,
    val endTime: String?,
    val monthlyFee: Int,
    val capacity: Int?,
    val students: Int,
    val overdue: Int,
    val runsToday: Boolean,
    val isActive: Boolean,
)

/** One student on a batch, for the roster + attendance sheet. */
data class StudentRow(
    val id: Long,
    val name: String,
    val phone: String,
    val paidUntil: String?,
    val overdue: Boolean,
    val present: Boolean,
    val attended: Int,
)

data class RosterPage(
    val batchName: String,
    val coach: String?,
    val date: String,
    val runsToday: Boolean,
    val students: List<StudentRow>,
)

/** An offer the venue sells: "10 sessions for ₹4,000". */
data class VenuePackageRow(
    val id: Long,
    val name: String,
    val price: Int,
    val sessions: Int,
    val perSession: Int,
    val validityDays: Int?,
    val isActive: Boolean,
)

/** A customer who holds a pass, and what's left on it. */
data class PackageHolder(
    val id: Long,
    val name: String,
    val phone: String,
    val packageName: String,
    val total: Int,
    val used: Int,
    val remaining: Int,
    val expiresAt: String?,
    val expired: Boolean,
    val usable: Boolean,
)

data class PackagesPage(val packages: List<VenuePackageRow>, val holders: List<PackageHolder>)

/** One customer of this venue, identified by phone across online + walk-in bookings. */
data class CustomerRow(
    val name: String,
    val phone: String,
    val bookings: Int,
    val spent: Double,
    val isRepeat: Boolean,
    val lastVisit: String?,
)

data class CustomersPage(
    val total: Int,
    val repeat: Int,
    val anonymous: Int,
    val data: List<CustomerRow>,
)

/** Where settlements are sent. The destination itself is only ever masked. */
data class PayoutAccount(
    val method: String,
    val accountHolder: String,
    val bankName: String?,
    val masked: String,
    val verified: Boolean,
)

/** One settlement transfer to the partner. */
data class PayoutBatchRow(
    val id: Long,
    val amount: Double,
    val status: String,
    val isPaid: Boolean,
    val reference: String?,
    val period: String?,
    val date: String?,
)

/** The settlement home: what's owed, where it goes, and what's already gone. */
data class PayoutsPage(
    val available: Double,
    val inFlight: Double,
    val settled: Double,
    val collected: Double,
    val account: PayoutAccount?,
    val batches: List<PayoutBatchRow>,
)

/** A desk person under a partner owner. */
data class StaffMember(val id: Long, val name: String, val email: String, val permissions: List<String>)

/** All capabilities an owner can grant a desk person. */
val STAFF_PERMISSIONS = listOf("bookings", "checkin", "pricing", "reports")

/**
 * A court's pricing: a base hourly rate plus optional peak pricing. Peak only
 * applies when it has a price AND a schedule (days and/or a time window) — the
 * server ignores a bare peak price rather than charging it for every hour.
 */
data class CourtPricing(
    val id: Long,
    val name: String,
    val sports: List<String>,
    val price: Int,
    val hasOwnPrice: Boolean,
    val peakPrice: Int?,
    val peakDays: List<String>,
    val peakStart: String?,
    val peakEnd: String?,
) {
    val peakOn: Boolean get() = peakPrice != null && peakPrice > 0
}

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
        parseLoginEnvelope(post("/api/auth/login", payload.toString(), token = null))
    }

    /** POST /api/auth/google { id_token } → same envelope as email login. */
    suspend fun google(idToken: String): LoginResult = withContext(Dispatchers.IO) {
        val payload = JSONObject().put("id_token", idToken)
        parseLoginEnvelope(post("/api/auth/google", payload.toString(), token = null))
    }

    /**
     * POST /api/auth/phone-otp/start { phone } — sends a WhatsApp login code.
     * Returns channel "whatsapp" + a token when the code went out, or channel "sms"
     * for every reason it couldn't (the app has no Firebase SMS fallback, so that
     * surfaces as "try another way").
     */
    suspend fun startPhoneOtp(phone: String): PhoneOtpStart = withContext(Dispatchers.IO) {
        val payload = JSONObject().put("phone", phone)
        val o = JSONObject(post("/api/auth/phone-otp/start", payload.toString(), token = null))
        PhoneOtpStart(o.optString("channel", "sms"), o.optStringOrNull("token"))
    }

    /** POST /api/auth/phone-otp/verify { token, code } → same envelope as email login. */
    suspend fun verifyPhoneOtp(otpToken: String, code: String): LoginResult = withContext(Dispatchers.IO) {
        val payload = JSONObject().put("token", otpToken).put("code", code)
        parseLoginEnvelope(post("/api/auth/phone-otp/verify", payload.toString(), token = null))
    }

    /** Shared parser for the `{ token, user }` envelope every sign-in path returns. */
    private fun parseLoginEnvelope(body: String): LoginResult {
        val o = JSONObject(body)
        val token = o.optString("token").ifBlank { throw ApiException(200, "Login response had no token") }
        val user = o.optJSONObject("user")
        val name = user?.optStringOrNull("name") ?: "Partner"
        val isDesk = user != null && !user.isNull("parentPartnerId")
        val permsArr = user?.optJSONArray("staffPermissions")
        val perms = if (permsArr == null) emptyList() else (0 until permsArr.length()).map { permsArr.optString(it) }
        return LoginResult(token, name, user?.optStringOrNull("partnerType"), isDesk, perms)
    }

    /**
     * GET /api/partner/context — the shell. Branches come back already scoped, so
     * a desk person is simply never told the other outlets exist.
     */
    suspend fun context(token: String): PartnerContext = withContext(Dispatchers.IO) {
        val o = JSONObject(get("/api/partner/context", token))
        val business = o.optJSONObject("business")
        val user = o.optJSONObject("user")
        val arr = o.optJSONArray("branches")

        PartnerContext(
            businessName = business?.optStringOrNull("name") ?: "Partner",
            businessType = business?.optStringOrNull("type") ?: "venue",
            typeLabel = business?.optStringOrNull("type_label"),
            lane = business?.optStringOrNull("lane"),
            capabilities = business?.optJSONArray("capabilities").toStringList(),
            altitude = user?.optStringOrNull("altitude") ?: "owner",
            permissions = user?.optJSONArray("permissions").toStringList(),
            branches = if (arr == null) emptyList() else (0 until arr.length()).map { i ->
                val b = arr.getJSONObject(i)
                Branch(
                    id = b.optLong("id"),
                    name = b.optString("name"),
                    branch = b.optStringOrNull("branch") ?: b.optString("name"),
                    code = b.optStringOrNull("code"),
                    kind = b.optStringOrNull("kind") ?: "sports",
                    city = b.optStringOrNull("city"),
                    isActive = b.optBoolean("is_active", true),
                    capabilities = b.optJSONArray("capabilities").toStringList(),
                )
            },
        )
    }

    /**
     * `?venue_id=` when a branch is selected, else nothing — "all branches" is the
     * absence of the parameter, matching the server's default.
     */
    private fun branchParam(venueId: Long?, separator: String = "?"): String =
        if (venueId == null || venueId <= 0L) "" else "${separator}venue_id=$venueId"

    suspend fun overview(token: String, venueId: Long? = null): Overview = withContext(Dispatchers.IO) {
        val o = JSONObject(get("/api/partner/overview" + branchParam(venueId), token))
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
                image = o.optStringOrNull("image"),
                sports = o.optJSONArray("sports").let { a ->
                    if (a == null) emptyList() else (0 until a.length()).map { a.optString(it) }
                },
                bookings = o.optInt("bookings"),
                revenue = o.optDouble("revenue", 0.0),
            )
        }
    }

    suspend fun bookings(token: String, venueId: Long? = null): List<BookingSummary> = withContext(Dispatchers.IO) {
        parseArray(get("/api/partner/bookings" + branchParam(venueId), token)) { o ->
            val label = o.optStringOrNull("event") ?: o.optStringOrNull("venue")
            BookingSummary(
                id = o.optLong("id"),
                ticketCode = o.optStringOrNull("ticket_code"),
                quantity = o.optInt("quantity"),
                amount = o.optDouble("amount", 0.0),
                status = o.optStringOrNull("status"),
                checkedIn = o.optInt("checked_in"),
                label = label,
                branch = o.optStringOrNull("branch"),
                customer = o.optString("customer", "Guest").ifBlank { "Guest" },
                channel = o.optString("channel", "online"),
                paymentStatus = o.optString("payment_status", "paid"),
                slotDate = o.optStringOrNull("slot_date"),
                slotLabel = o.optStringOrNull("slot_label"),
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
            val cellsArr = s.optJSONArray("courts")
            val cells = if (cellsArr == null) emptyList() else (0 until cellsArr.length()).map { k ->
                val c = cellsArr.getJSONObject(k)
                CourtCell(
                    courtId = c.optLong("court_id"),
                    booked = c.optInt("booked"),
                    isBooked = c.optBoolean("is_booked", c.optInt("booked") > 0),
                    price = c.optDouble("price", 0.0),
                    isPeak = c.optBoolean("is_peak", false),
                    bookings = parseDayBookings(c.optJSONArray("bookings")),
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
                bookings = parseDayBookings(s.optJSONArray("bookings")),
                courts = cells,
            )
        }
        val courtsArr = o.optJSONArray("courts")
        val courts = if (courtsArr == null) emptyList() else (0 until courtsArr.length()).map { i ->
            val c = courtsArr.getJSONObject(i)
            val sportsArr = c.optJSONArray("sports")
            val sports = if (sportsArr == null) emptyList() else (0 until sportsArr.length()).map { sportsArr.optString(it) }
            CourtCol(id = c.optLong("id"), name = c.optString("name"), sports = sports)
        }
        DayGrid(
            date = o.optString("date"),
            venueName = o.optJSONObject("venue")?.optStringOrNull("name") ?: "Venue",
            isBlocked = o.optBoolean("is_blocked", false),
            slots = slots,
            courts = courts,
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

    private fun parseHolder(o: JSONObject) = PackageHolder(
        id = o.optLong("id"),
        name = o.optString("name"),
        phone = o.optString("phone"),
        packageName = o.optString("package"),
        total = o.optInt("total"),
        used = o.optInt("used"),
        remaining = o.optInt("remaining"),
        expiresAt = o.optStringOrNull("expires_at"),
        expired = o.optBoolean("expired", false),
        usable = o.optBoolean("usable", false),
    )

    private fun parseStudent(o: JSONObject) = StudentRow(
        id = o.optLong("id"),
        name = o.optString("name"),
        phone = o.optString("phone"),
        paidUntil = o.optStringOrNull("paid_until"),
        overdue = o.optBoolean("overdue", false),
        present = o.optBoolean("present", false),
        attended = o.optInt("attended"),
    )

    /**
     * GET /api/notifications — the bell inbox. Not under /api/partner: it's the
     * shared broadcast inbox every signed-in account has, and a partner is one.
     */
    suspend fun notifications(token: String): NotificationsPage = withContext(Dispatchers.IO) {
        val o = JSONObject(get("/api/notifications", token))
        val arr = o.optJSONArray("notifications") ?: o.optJSONArray("data")
        NotificationsPage(
            unread = o.optInt("unread"),
            items = if (arr == null) emptyList() else (0 until arr.length()).map { i ->
                val n = arr.getJSONObject(i)
                NotificationRow(
                    id = n.optLong("id"),
                    title = n.optString("title"),
                    body = n.optStringOrNull("body"),
                    read = n.optBoolean("read", false),
                    createdAt = n.optStringOrNull("created_at"),
                )
            },
        )
    }

    /** Mark every notification read (empty ids = all, per NotificationsController). */
    suspend fun markNotificationsRead(token: String, ids: List<Long> = emptyList()) = withContext(Dispatchers.IO) {
        val payload = JSONObject().put("ids", JSONArray(ids))
        post("/api/notifications/read", payload.toString(), token)
        Unit
    }

    /** GET /api/support/thread — the partner↔admin conversation. */
    suspend fun supportThread(token: String): List<SupportMessage> = withContext(Dispatchers.IO) {
        val o = JSONObject(get("/api/support/thread", token))
        val arr = o.optJSONArray("messages")
        if (arr == null) emptyList() else (0 until arr.length()).map { i ->
            val m = arr.getJSONObject(i)
            SupportMessage(
                id = m.optLong("id"),
                body = m.optString("body"),
                fromAdmin = m.optString("from") == "admin",
                createdAt = m.optStringOrNull("created_at"),
            )
        }
    }

    suspend fun sendSupportMessage(token: String, body: String) = withContext(Dispatchers.IO) {
        post("/api/support/messages", JSONObject().put("body", body).toString(), token)
        Unit
    }

    /** GET /api/partner/academy — coaching batches with roster + fee health. */
    suspend fun academy(token: String): List<BatchRow> = withContext(Dispatchers.IO) {
        parseArray(get("/api/partner/academy", token)) { o ->
            val d = o.optJSONArray("days")
            BatchRow(
                id = o.optLong("id"),
                name = o.optString("name"),
                coach = o.optStringOrNull("coach"),
                sport = o.optStringOrNull("sport"),
                days = if (d == null) emptyList() else (0 until d.length()).map { d.optString(it) },
                startTime = o.optStringOrNull("start_time"),
                endTime = o.optStringOrNull("end_time"),
                monthlyFee = o.optInt("monthly_fee"),
                capacity = if (o.isNull("capacity")) null else o.optInt("capacity"),
                students = o.optInt("students"),
                overdue = o.optInt("overdue"),
                runsToday = o.optBoolean("runs_today", false),
                isActive = o.optBoolean("is_active", true),
            )
        }
    }

    suspend fun saveBatch(
        token: String,
        name: String,
        coach: String?,
        days: List<String>,
        startTime: String?,
        endTime: String?,
        monthlyFee: Int,
        capacity: Int?,
    ) = withContext(Dispatchers.IO) {
        val payload = JSONObject()
            .put("name", name)
            .put("coachName", coach ?: JSONObject.NULL)
            .put("days", JSONArray(days))
            .put("startTime", startTime ?: JSONObject.NULL)
            .put("endTime", endTime ?: JSONObject.NULL)
            .put("monthlyFee", monthlyFee)
            .put("capacity", capacity ?: JSONObject.NULL)
        post("/api/partner/academy", payload.toString(), token)
        Unit
    }

    /** Enrol a student. Re-enrolling the same number extends their seat. */
    suspend fun enrollStudent(token: String, batchId: Long, name: String, phone: String, months: Int) = withContext(Dispatchers.IO) {
        val payload = JSONObject().put("studentName", name).put("studentPhone", phone).put("months", months)
        post("/api/partner/academy/$batchId/enroll", payload.toString(), token)
        Unit
    }

    suspend fun batchRoster(token: String, batchId: Long, date: String): RosterPage = withContext(Dispatchers.IO) {
        val o = JSONObject(get("/api/partner/academy/$batchId/roster?date=$date", token))
        val b = o.optJSONObject("batch")
        val arr = o.optJSONArray("data")
        RosterPage(
            batchName = b?.optString("name") ?: "Batch",
            coach = b?.optStringOrNull("coach"),
            date = o.optString("date"),
            runsToday = o.optBoolean("runs_today", false),
            students = if (arr == null) emptyList() else (0 until arr.length()).map { parseStudent(arr.getJSONObject(it)) },
        )
    }

    /** Mark present/absent. Marking twice is the same fact — the server is idempotent. */
    suspend fun markAttendance(token: String, enrollmentId: Long, date: String, present: Boolean) = withContext(Dispatchers.IO) {
        val payload = JSONObject().put("enrollmentId", enrollmentId).put("date", date).put("present", present)
        post("/api/partner/academy/attendance", payload.toString(), token)
        Unit
    }

    /** GET /api/partner/packages — offers this venue sells + who holds a pass. */
    suspend fun packages(token: String, venueId: Long? = null): PackagesPage = withContext(Dispatchers.IO) {
        val o = JSONObject(get("/api/partner/packages" + branchParam(venueId), token))
        val pk = o.optJSONArray("data")
        val hd = o.optJSONArray("holders")
        PackagesPage(
            packages = if (pk == null) emptyList() else (0 until pk.length()).map { i ->
                val p = pk.getJSONObject(i)
                VenuePackageRow(
                    id = p.optLong("id"),
                    name = p.optString("name"),
                    price = p.optInt("price"),
                    sessions = p.optInt("sessions"),
                    perSession = p.optInt("per_session"),
                    validityDays = if (p.isNull("validity_days")) null else p.optInt("validity_days"),
                    isActive = p.optBoolean("is_active", true),
                )
            },
            holders = if (hd == null) emptyList() else (0 until hd.length()).map { parseHolder(hd.getJSONObject(it)) },
        )
    }

    suspend fun savePackage(token: String, name: String, price: Int, sessions: Int, validityDays: Int?) = withContext(Dispatchers.IO) {
        val payload = JSONObject().put("name", name).put("price", price).put("sessions", sessions)
            .put("validityDays", validityDays ?: JSONObject.NULL)
        post("/api/partner/packages", payload.toString(), token)
        Unit
    }

    suspend fun sellPackage(
        token: String,
        packageId: Long,
        phone: String,
        name: String,
        method: String = "cash",
        venueId: Long? = null,
    ) = withContext(Dispatchers.IO) {
        val payload = JSONObject().put("customerPhone", phone).put("customerName", name).put("paymentMethod", method)
        // Credit the sale to the branch the desk is standing in. Without this the
        // server has no way to attribute it and leaves it null.
        if (venueId != null && venueId > 0L) payload.put("venueId", venueId)
        post("/api/partner/packages/$packageId/sell", payload.toString(), token)
        Unit
    }

    /**
     * What this number has left AND may spend here — drives "use a session" on the
     * walk-in sheet. Passing the branch matters: a pass locked to another outlet
     * must not be offered, or the desk spends a session the offer never covered.
     */
    suspend fun packageHolder(token: String, phone: String, venueId: Long? = null): List<PackageHolder> = withContext(Dispatchers.IO) {
        if (phone.length < 10) return@withContext emptyList()
        val o = JSONObject(get("/api/partner/packages/holder?phone=$phone" + branchParam(venueId, "&"), token))
        val arr = o.optJSONArray("data")
        if (arr == null) emptyList() else (0 until arr.length()).map { parseHolder(arr.getJSONObject(it)) }
    }

    /** GET /api/partner/customers — who books here, keyed on phone. */
    suspend fun customers(token: String, query: String = "", venueId: Long? = null): CustomersPage = withContext(Dispatchers.IO) {
        val q = if (query.isBlank()) "" else "?q=" + java.net.URLEncoder.encode(query.trim(), "UTF-8")
        val suffix = q + branchParam(venueId, if (q.isEmpty()) "?" else "&")
        val o = JSONObject(get("/api/partner/customers$suffix", token))
        val s = o.optJSONObject("summary")
        val arr = o.optJSONArray("data")
        CustomersPage(
            total = s?.optInt("total") ?: 0,
            repeat = s?.optInt("repeat") ?: 0,
            anonymous = s?.optInt("anonymous") ?: 0,
            data = if (arr == null) emptyList() else (0 until arr.length()).map { i ->
                val c = arr.getJSONObject(i)
                CustomerRow(
                    name = c.optString("name"),
                    phone = c.optString("phone"),
                    bookings = c.optInt("bookings"),
                    spent = c.optDouble("spent", 0.0),
                    isRepeat = c.optBoolean("is_repeat", false),
                    lastVisit = c.optStringOrNull("last_visit"),
                )
            },
        )
    }

    /** GET /api/partner/payouts — balance, settlement account, batch history. */
    suspend fun payouts(token: String): PayoutsPage = withContext(Dispatchers.IO) {
        val o = JSONObject(get("/api/partner/payouts", token))
        val b = o.getJSONObject("balance")
        val acc = o.optJSONObject("account")
        val arr = o.optJSONArray("batches")
        PayoutsPage(
            available = b.optDouble("available", 0.0),
            inFlight = b.optDouble("in_flight", 0.0),
            settled = b.optDouble("settled", 0.0),
            collected = b.optDouble("collected", 0.0),
            account = acc?.let {
                PayoutAccount(
                    method = it.optString("method", "bank"),
                    accountHolder = it.optString("account_holder"),
                    bankName = it.optStringOrNull("bank_name"),
                    masked = it.optString("masked"),
                    verified = it.optBoolean("verified", false),
                )
            },
            batches = if (arr == null) emptyList() else (0 until arr.length()).map { i ->
                val x = arr.getJSONObject(i)
                PayoutBatchRow(
                    id = x.optLong("id"),
                    amount = x.optDouble("amount", 0.0),
                    status = x.optString("status"),
                    isPaid = x.optBoolean("is_paid", false),
                    reference = x.optStringOrNull("reference"),
                    period = x.optStringOrNull("period"),
                    date = x.optStringOrNull("date"),
                )
            },
        )
    }

    /** Set where settlements are sent. Saving always clears verification. */
    suspend fun savePayoutAccount(
        token: String,
        method: String,
        accountHolder: String,
        bankName: String?,
        accountNumber: String?,
        ifsc: String?,
        upiVpa: String?,
    ) = withContext(Dispatchers.IO) {
        val payload = JSONObject()
            .put("method", method)
            .put("accountHolder", accountHolder)
        if (method == "bank") {
            payload.put("bankName", bankName ?: "")
                .put("accountNumber", accountNumber ?: "")
                .put("ifsc", ifsc ?: "")
        } else {
            payload.put("upiVpa", upiVpa ?: "")
        }
        post("/api/partner/payouts/account", payload.toString(), token)
        Unit
    }

    /** GET the venue's courts with base + peak pricing. */
    suspend fun venueCourts(token: String, venueId: Long): List<CourtPricing> = withContext(Dispatchers.IO) {
        parseArray(get("/api/partner/venues/$venueId/courts", token)) { o ->
            val daysArr = o.optJSONArray("peak_days")
            CourtPricing(
                id = o.optLong("id"),
                name = o.optString("name"),
                sports = o.optJSONArray("sports").let { a ->
                    if (a == null) emptyList() else (0 until a.length()).map { a.optString(it) }
                },
                price = o.optInt("price"),
                hasOwnPrice = o.optBoolean("has_own_price", false),
                peakPrice = if (o.isNull("peak_price")) null else o.optInt("peak_price"),
                peakDays = if (daysArr == null) emptyList() else (0 until daysArr.length()).map { daysArr.optString(it) },
                peakStart = o.optStringOrNull("peak_start"),
                peakEnd = o.optStringOrNull("peak_end"),
            )
        }
    }

    /** Save a court's base rate and peak rule. Pass peakPrice null to turn peak off. */
    suspend fun saveCourtPricing(
        token: String,
        venueId: Long,
        courtId: Long,
        price: Int,
        peakPrice: Int?,
        peakDays: List<String>,
        peakStart: String?,
        peakEnd: String?,
    ) = withContext(Dispatchers.IO) {
        val payload = JSONObject()
            .put("price", price)
            .put("peakPrice", peakPrice ?: JSONObject.NULL)
            .put("peakDays", JSONArray(peakDays))
            .put("peakStart", peakStart ?: JSONObject.NULL)
            .put("peakEnd", peakEnd ?: JSONObject.NULL)
        post("/api/partner/venues/$venueId/courts/$courtId", payload.toString(), token)
        Unit
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

    suspend fun createWalkIn(
        token: String,
        venueId: Long,
        slotId: Long,
        date: String,
        name: String,
        phone: String,
        courtId: Long? = null,
        method: PayMethod = PayMethod.CASH,
        customerPackageId: Long? = null,
    ): WalkInResult = withContext(Dispatchers.IO) {
        val payload = JSONObject()
            .put("slotId", slotId).put("date", date)
            .put("guestName", name).put("guestPhone", phone)
            .put("paymentMethod", method.api)
        if (courtId != null) payload.put("courtId", courtId)
        if (customerPackageId != null) payload.put("customerPackageId", customerPackageId)
        val o = JSONObject(post("/api/partner/venues/$venueId/bookings", payload.toString(), token))
        val b = o.optJSONObject("booking")
        WalkInResult(
            bookingId = b?.optLong("id") ?: 0L,
            amount = b?.optDouble("amount", 0.0) ?: 0.0,
            paymentMethod = o.optString("payment_method", method.api),
            paymentLink = o.optStringOrNull("payment_link"),
            paymentLinkId = o.optStringOrNull("payment_link_id"),
        )
    }

    /**
     * Ask whether the walk-in's payment link has been paid. The server checks Razorpay
     * directly, so this works with no webhook configured, and settles the money (ledger +
     * customer confirmation) the first time it comes back paid.
     */
    suspend fun paymentStatus(token: String, bookingId: Long, linkId: String): PayState = withContext(Dispatchers.IO) {
        val payload = JSONObject().put("linkId", linkId)
        val o = JSONObject(post("/api/partner/bookings/$bookingId/payment-status", payload.toString(), token))
        PayState(paid = o.optBoolean("paid", false), status = o.optString("status", "unknown"))
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

    private fun parseDayBookings(arr: JSONArray?): List<DayBooking> {
        if (arr == null) return emptyList()
        return (0 until arr.length()).map { j ->
            val b = arr.getJSONObject(j)
            DayBooking(
                id = b.optLong("id"),
                customer = b.optString("customer"),
                phone = b.optStringOrNull("phone"),
                channel = b.optString("channel", "online"),
                status = b.optString("status"),
                checkedIn = b.optInt("checked_in"),
                amount = b.optDouble("amount", 0.0),
                amountPaid = b.optDouble("amount_paid", 0.0),
                paymentStatus = b.optString("payment_status", "unpaid"),
            )
        }
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

/** A JSON array of strings, or an empty list when the key was absent or null. */
private fun JSONArray?.toStringList(): List<String> =
    if (this == null) emptyList() else (0 until length()).map { optString(it) }.filter { it.isNotBlank() }

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
