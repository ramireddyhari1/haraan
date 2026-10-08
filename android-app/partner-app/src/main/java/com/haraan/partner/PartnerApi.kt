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

    /** "On duty": new bookings reach the partner with the app closed (BookingWatchService). */
    var onDuty: Boolean
        get() = prefs.getBoolean("on_duty", false)
        set(value) = prefs.edit().putBoolean("on_duty", value).apply()

    val isSignedIn: Boolean get() = !token.isNullOrBlank()

    fun clear() {
        token = null; name = null; partnerType = null
        isDesk = false; permissionsCsv = null
        branchId = null
        lastNotifiedBookingId = 0L
        onDuty = false
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

/**
 * The charts under Home (`GET /api/partner/insights`). Court-hours and rupees of live
 * bookings only, placed by the same rule as [ShiftBoard] and the desk grid.
 */
data class HomeInsights(
    val enabled: Boolean,
    val week: InsightWeek,
    val channelsToday: ChannelSplit,
    val channelsWeek: ChannelSplit,
    val channelsLastWeek: ChannelSplit,
    val heatmap: BusyHours,
    val tomorrow: TomorrowOpen,
    val haraan: HaraanBrought,
    val growth: Growth,
    val milestones: Milestones,
    val customers: CustomerSummary,
)

/** New vs returning customers for one period, against the one before. */
data class PeriodCustomers(val label: String, val new: Int, val returning: Int, val lastNew: Int, val lastReturning: Int) {
    val total: Int get() = new + returning
}

data class CustomerSummary(val week: PeriodCustomers, val month: PeriodCustomers, val total: Int, val cameBack: Int)

/** One person behind the card: recognised by phone, so walk-in and app visits are one customer. */
data class InsightCustomer(
    val name: String,
    val phone: String?,
    /** "new" or "returning". */
    val type: String,
    val visits: Int,
    val visitsPeriod: Int,
    val firstVisit: String,
    val lastVisit: String,
    val spent: Double,
    /** walk_in | app | whatsapp — how they booked last. */
    val via: String,
    val recent: List<CustomerVisit>,
)

data class CustomerVisit(val date: String, val time: String, val amount: Double, val channel: String)

data class CustomerList(val label: String, val customers: List<InsightCustomer>)

/** Bookings that came through Haraan (app, website, WhatsApp) rather than the counter. */
data class HaraanBrought(
    val allTime: ChannelShare,
    val thisMonth: ChannelShare,
    val lastMonth: ChannelShare,
    /** Distinct players who booked in the app. */
    val players: Int,
    /** Share of all bookings that came through Haraan, 0..1; null with no bookings. */
    val share: Double?,
)

/** Running totals since the first booking; only ever climbs. */
data class Growth(
    val since: String?,
    /** "week" or "month". */
    val unit: String,
    val points: List<GrowthPoint>,
    val bookings: Int,
    val revenue: Double,
)

data class GrowthPoint(val label: String, val bookings: Int, val revenue: Double)

data class Milestones(
    val total: Int,
    val reached: List<Milestone>,
    val firstOnline: String?,
    val next: NextMilestone?,
)

data class Milestone(val count: Int, val label: String, val date: String)

data class NextMilestone(val count: Int, val remaining: Int, val progress: Float)

data class InsightWeek(
    /** Monday, yyyy-MM-dd. */
    val start: String,
    val label: String,
    val prev: String,
    /** Null on the current week: there is no future to page to. */
    val next: String?,
    val days: List<InsightDay>,
    val revenue: Double,
    val bookedHours: Double,
    val totalHours: Double,
    val lastRevenue: Double,
)

data class InsightDay(
    val date: String,
    val label: String,
    val day: Int,
    val today: Boolean,
    val future: Boolean,
    val revenue: Double,
    val bookedHours: Double,
    val totalHours: Double,
    val lastRevenue: Double,
    val lastBookedHours: Double,
)

data class ChannelShare(val amount: Double, val hours: Double, val count: Int)

data class ChannelSplit(val walkIn: ChannelShare, val app: ChannelShare, val whatsapp: ChannelShare) {
    val amount: Double get() = walkIn.amount + app.amount + whatsapp.amount
    val count: Int get() = walkIn.count + app.count + whatsapp.count
    /** Share of the money that came through Haraan (app + WhatsApp), 0..1; null with no money. */
    val onlineShare: Double? get() = if (amount > 0) (app.amount + whatsapp.amount) / amount else null
}

data class BusyHours(
    /** False until the venue has enough bookings for "quiet" to mean something. */
    val ready: Boolean,
    val weeks: Int,
    val hours: List<String>,
    /** Seven rows, Mon..Sun; each cell 0..1, or null where the venue doesn't run that hour. */
    val rows: List<Pair<String, List<Float?>>>,
    val quiet: List<QuietWindow>,
)

data class QuietWindow(val days: String, val hours: String, val fill: Int)

data class TomorrowOpen(val label: String, val venues: List<TomorrowVenue>)

data class TomorrowVenue(
    val id: Long,
    val name: String,
    val closed: Boolean,
    val openHours: Double,
    val totalHours: Double,
    val windows: List<Pair<String, Int>>,
    val shareUrl: String,
    /** The admin's share message, filled in; blank from an older server. */
    val shareText: String,
)

/**
 * Today, on this partner's courts — what `GET /api/partner/today` answers.
 *
 * Capacity counts CELLS (courts x slots), the same unit the desk grid draws, so
 * "6 of 54" on Home and the grid can never tell different stories.
 */
data class ShiftBoard(
    val dayLabel: String,
    val slotsTotal: Int,
    val slotsBooked: Int,
    val slotsDone: Int,
    val expected: Double,
    val collected: Double,
    val due: Double,
    /** Owed across every date, not just today — the chase list doesn't reset at midnight. */
    val chaseCount: Int,
    val chaseAmount: Double,
    val closed: List<String>,
    val next: List<ShiftBooking>,
    /** The venues have slots on some day (not necessarily today). */
    val hasAnySlots: Boolean = slotsTotal > 0,
) {
    val occupancy: Float get() = if (slotsTotal <= 0) 0f else slotsBooked.toFloat() / slotsTotal

    /** A day with no courts configured can't be reported as an empty one. */
    val hasCapacity: Boolean get() = slotsTotal > 0
}

data class ShiftBooking(
    val time: String,
    val customer: String,
    val venue: String,
    val court: String,
    val amount: Double,
    val paid: Boolean,
    val walkIn: Boolean,
    /** On court right now — the row the desk cares about most. */
    val running: Boolean,
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

/** See [PartnerApi.manager]. [name] null = nobody assigned. */
data class HaraanManager(
    val name: String?,
    val photoUrl: String?,
    val title: String?,
    val intro: String?,
    val hours: String?,
    val phone: String?,
    val showCall: Boolean,
    val showWhatsapp: Boolean,
    val showChat: Boolean,
    val supportWhatsapp: String?,
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
    /** What has actually been collected — a partly-settled booking is not owed in full. */
    val amountPaid: Double = 0.0,
    /** cash | upi | card | online — null when no money was ever recorded. */
    val paymentMethod: String? = null,
    val slotDate: String? = null,
    val slotLabel: String? = null,
    /** When the booking came in (ISO-8601) — the time a payment feed shows. */
    val createdAt: String? = null,
    /** The customer's number: a walk-in's guest phone, else their own account's. */
    val phone: String? = null,
    val email: String? = null,
    /** False on a server that doesn't send contact yet — "none" and "not sent" read differently. */
    val contactSent: Boolean = false,
)

/**
 * A game on one of the partner's courts.
 *
 * [source] is the honesty flag: `booking` means the match sits on a confirmed
 * booking at this venue, `nearby` means only that its GPS landed here. The UI
 * must keep the two apart — never sum them into one count.
 */
data class VenueMatch(
    val id: Long,
    val sport: String,
    val title: String,
    val home: String,
    val away: String,
    val score1: String,
    val score2: String,
    val overs: String,
    /** Points inside the set being played — the set sports' answer to overs. */
    val rally1: String,
    val rally2: String,
    val status: String,
    val isLive: Boolean,
    val isFinished: Boolean,
    val startsAt: String?,
    val time: String,
    val typedVenue: String,
    val venueName: String,
    val branch: String,
    val source: String,
    /** Metres from the venue. Null for booking-linked matches, which need no guess. */
    val distanceM: Int?,
    /** Cricket: 1 = home batting, 2 = away. The overs belong to this side. */
    val battingTeam: Int = 1,
)

data class VenueMatches(val confirmed: List<VenueMatch>, val nearby: List<VenueMatch>) {
    val isEmpty: Boolean get() = confirmed.isEmpty() && nearby.isEmpty()
    val liveCount: Int get() = confirmed.count { it.isLive }
}

/** Result of a scan-and-check-in. */
data class CheckInResult(
    val status: String,
    val message: String,
    /** Who the ticket is for, so the gate can greet them by name. Null when unknown. */
    val guest: String? = null,
    /** People on the ticket; the gate lets this many through. */
    val quantity: Int = 0,
    /** The slot or session the ticket is for, as the server labels it. */
    val slotLabel: String? = null,
)

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
    /** A Razorpay UPI QR on the desk's screen, confirmed automatically when paid. */
    UPI_QR("upi_qr", "UPI QR"),
    CARD("card", "Card"),
    LINK("link", "Payment link"),
    PACKAGE("package", "Use a session"),
}

/**
 * What the desk shows while a walk-in pays online: a UPI QR (`kind = upi_qr`, [qr] is a
 * `upi://pay` string any UPI app scans) or a payment link (`kind = link`, [qr] is the
 * link's URL for the phone camera). [error] is set when neither could be made.
 */
data class DeskPayment(
    val kind: String,
    /** How to show it: `qr` draw the UPI QR, `page` open Razorpay's page in the app, `share` a link to send. */
    val present: String,
    val id: String?,
    val qr: String?,
    val url: String?,
    val imageUrl: String?,
    val amount: Double,
    val expiresInSeconds: Int,
    val error: String?,
    /** Razorpay's own checkout on the desk phone (`kind = order`), already holding the customer's number. */
    val checkout: DeskCheckout? = null,
) {
    val isQr: Boolean get() = kind == "upi_qr"
    val isPage: Boolean get() = present == "page" && url != null
    val isCheckout: Boolean get() = kind == "order" && checkout != null
    /** Something the desk can actually put in front of the customer. */
    val isUsable: Boolean get() = error == null && id != null && (qr != null || imageUrl != null || url != null || checkout != null)
}

/** What Razorpay's checkout opens with: the order, and the walk-in's number so it isn't asked again. */
data class DeskCheckout(
    val key: String,
    val orderId: String,
    val name: String,
    val description: String,
    val contact: String?,
    val customer: String?,
)

/** Outcome of creating a walk-in: the booking, plus a Razorpay link when asked for. */
data class WalkInResult(
    val bookingId: Long,
    val amount: Double,
    val paymentMethod: String,
    val paymentLink: String?,
    val paymentLinkId: String?,
    val payment: DeskPayment? = null,
)

/** Reply to "make a fresh QR": either it was paid meanwhile, or here is the new one. */
data class PaymentRequestResult(val paid: Boolean, val payment: DeskPayment?)

/** What "Generate slots" did. */
data class GenerateResult(val created: Int, val kept: Int, val removed: Int)

/** One booking in the partner's report. */
data class ReportRow(
    val id: String,
    val bookedAt: String,
    val type: String,
    val item: String,
    val slot: String,
    val slotDate: String,
    val customer: String,
    val phone: String,
    val channel: String,
    val quantity: Int,
    val amount: Double,
    val amountPaid: Double,
    val status: String,
    val paymentStatus: String,
    val checkedIn: Int,
    val ticket: String,
) {
    /** Cancelled, refunded, failed, expired, or a checkout still unpaid: not a sale. */
    val isSale: Boolean
        get() = status.lowercase() !in setOf("cancelled", "refunded", "failed", "expired", "pending")
    val isWalkIn: Boolean get() = channel.equals("offline", true)
}

data class ReportData(val from: String, val to: String, val partner: String, val rows: List<ReportRow>)

/** Live payment state of a walk-in's link, straight from Razorpay. */
data class PayState(val paid: Boolean, val status: String)

/** One court column in the day grid. */
data class CourtCol(val id: Long, val name: String, val sports: List<String>)

/** One court × slot cell: is this court free or booked for this time. */
data class CourtCell(
    val courtId: Long,
    val booked: Int,
    val isBooked: Boolean,
    /**
     * A player is on the payment screen for this court-hour right now. Not sold, and
     * not free either — the server refuses a desk booking on top of it, so the cell
     * has to say so rather than look Open and then throw an error at the tap.
     */
    val isHeld: Boolean = false,
    /** The rate this cell would actually charge — peak included. */
    val price: Double,
    val isPeak: Boolean = false,
    /**
     * Whether this court may be sold at this time at all: the slot runs for sports
     * this court doesn't host, or vice versa. The server decides it (same rule the
     * booking call enforces) so the grid can never offer a cell the API would 409.
     * Defaults true so a build talking to an older server behaves as before.
     */
    val allowed: Boolean = true,
    /** Why this court-hour is off sale when a block took it (maintenance, private hire…). */
    val block: CourtBlock? = null,
    val bookings: List<DayBooking>,
)

/**
 * A court-hour taken by something that isn't a booking. [removable] = a one-off the desk
 * made and may lift; recurring or whole-venue blocks belong to whoever set them up.
 */
data class CourtBlock(
    val id: Long,
    val kind: String,
    val label: String,
    val reason: String,
    val note: String?,
    val start: String?,
    val end: String?,
    val allDay: Boolean,
    val removable: Boolean,
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
    /** Sports this time runs for; empty = all of them. */
    val sports: List<String> = emptyList(),
    val bookings: List<DayBooking>,
    val courts: List<CourtCell> = emptyList(),
)

/**
 * Minutes after midnight at which a slot starts, read off however the server
 * spells it: "7:00 AM", "07:00", "7 AM", "18:00 - 19:00", "7:00 AM – 8:00 AM".
 * Only the first time in the string counts. Unreadable sorts last.
 */
internal fun slotStartMinutes(raw: String?): Int {
    val m = Regex("""(\d{1,2})(?::(\d{2}))?\s*([AaPp][Mm])?""").find(raw ?: return Int.MAX_VALUE)
        ?: return Int.MAX_VALUE
    var hour = m.groupValues[1].toIntOrNull() ?: return Int.MAX_VALUE
    val minute = m.groupValues[2].toIntOrNull() ?: 0
    when (m.groupValues[3].lowercase()) {
        "am" -> if (hour == 12) hour = 0
        "pm" -> if (hour != 12) hour += 12
    }
    return if (hour in 0..23 && minute in 0..59) hour * 60 + minute else Int.MAX_VALUE
}

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

/**
 * When a venue is open, day by day: Mon…Sun → (open, close) in minutes after midnight, or
 * null when closed. open == close is open 24 hours; close earlier than open runs past midnight.
 */
data class VenueHours(
    val set: Boolean,
    val days: Map<String, Pair<Int, Int>?>,
    val slotMinutes: Int,
) {
    companion object {
        val KEYS = listOf("Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun")
        val NAMES = listOf("Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday")
    }
}

/** A venue's public details, exactly as players see them on its page. */
data class VenueDetails(
    val address: String?,
    val latitude: Double?,
    val longitude: Double?,
    val mapLink: String?,
    val about: String?,
    val tagline: String?,
    val amenities: List<String>,
    val rules: List<String>,
    val cancellation: String?,
    /** "Convenience fee 4%", one line per fee players pay on top. */
    val fees: List<String>,
    val images: List<String>,
    val rating: Double?,
    val ratingsCount: Int,
    val category: String?,
)

/** An editable price/slot row for the pricing screen. */
data class SlotEdit(
    val id: Long,
    val day: String?,
    val time: String,
    val price: Double,
    val capacity: Int,
    val isOpen: Boolean,
    /** Sports this time runs for; empty = all of them. */
    val sports: List<String> = emptyList(),
    /** Court id → the price this slot charges on that court. Courts not listed use [price], else their own rate. */
    val courtPrices: Map<Long, Double> = emptyMap(),
) {
    /** What this slot sets for one court, or null to leave it to the court's own rate. */
    fun priceFor(courtId: Long): Double? = courtPrices[courtId]?.takeIf { it > 0 } ?: price.takeIf { it > 0 }
}

/** Unified analytics payload for either an event or a venue. */
data class Analytics(
    val title: String,
    val stats: List<StatItem>,
    val sales: List<SalesPoint>,
    val secondaryLabel: String,
    val tiers: List<TierRow>,
)

/** Raised for any non-2xx response, carrying a user-facing message. */
class ApiException(
    val code: Int,
    message: String,
    /** The raw error body, for the few callers whose error responses carry data. */
    val body: String? = null,
) : Exception(message)

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

    suspend fun today(token: String, venueId: Long? = null): ShiftBoard = withContext(Dispatchers.IO) {
        val o = JSONObject(get("/api/partner/today" + branchParam(venueId), token)).getJSONObject("data")
        val capacity = o.getJSONObject("capacity")
        val money = o.getJSONObject("money")
        val chase = o.getJSONObject("chase")
        val closedArr = o.optJSONArray("closed")
        val nextArr = o.optJSONArray("next")

        ShiftBoard(
            dayLabel = o.optString("day_label", ""),
            // Older servers don't send it: fall back to today's capacity, as before.
            hasAnySlots = o.optJSONObject("setup")?.optBoolean("has_slots", capacity.optInt("total") > 0)
                ?: (capacity.optInt("total") > 0),
            slotsTotal = capacity.optInt("total"),
            slotsBooked = capacity.optInt("booked"),
            slotsDone = capacity.optInt("done"),
            expected = money.optDouble("expected", 0.0),
            collected = money.optDouble("collected", 0.0),
            due = money.optDouble("due", 0.0),
            chaseCount = chase.optInt("count"),
            chaseAmount = chase.optDouble("amount", 0.0),
            closed = if (closedArr == null) emptyList() else (0 until closedArr.length()).mapNotNull {
                closedArr.optJSONObject(it)?.optString("name")?.takeIf { n -> n.isNotBlank() }
            },
            next = if (nextArr == null) emptyList() else (0 until nextArr.length()).mapNotNull { i ->
                nextArr.optJSONObject(i)?.let { b ->
                    ShiftBooking(
                        time = b.optString("time", ""),
                        customer = b.optString("customer", "Guest"),
                        venue = b.optString("venue", ""),
                        court = b.optString("court", ""),
                        amount = b.optDouble("amount", 0.0),
                        paid = b.optBoolean("paid", false),
                        walkIn = b.optBoolean("walk_in", false),
                        running = b.optBoolean("running", false),
                    )
                }
            },
        )
    }

    suspend fun insights(token: String, venueId: Long? = null, week: String? = null): HomeInsights = withContext(Dispatchers.IO) {
        val query = listOfNotNull(
            venueId?.takeIf { it > 0L }?.let { "venue_id=$it" },
            week?.let { "week=$it" },
        ).joinToString("&")
        val o = JSONObject(get("/api/partner/insights" + if (query.isEmpty()) "" else "?$query", token)).getJSONObject("data")
        parseInsights(o)
    }

    /** The people behind the new vs returning card, for "week" or "month". */
    suspend fun insightCustomers(token: String, venueId: Long?, period: String): CustomerList = withContext(Dispatchers.IO) {
        val query = listOfNotNull("period=$period", venueId?.takeIf { it > 0L }?.let { "venue_id=$it" }).joinToString("&")
        val o = JSONObject(get("/api/partner/insights/customers?$query", token)).getJSONObject("data")
        val arr = o.optJSONArray("customers")
        CustomerList(
            label = o.optString("label", ""),
            customers = if (arr == null) emptyList() else (0 until arr.length()).mapNotNull { i ->
                arr.optJSONObject(i)?.let { r ->
                    val rec = r.optJSONArray("recent")
                    InsightCustomer(
                        name = r.optString("name", "Guest"),
                        phone = r.optStringOrNull("phone"),
                        type = r.optString("type", "new"),
                        visits = r.optInt("visits"),
                        visitsPeriod = r.optInt("visits_period"),
                        firstVisit = r.optString("first_visit"),
                        lastVisit = r.optString("last_visit"),
                        spent = r.optDouble("spent", 0.0),
                        via = r.optString("via", "walk_in"),
                        recent = if (rec == null) emptyList() else (0 until rec.length()).mapNotNull { j ->
                            rec.optJSONObject(j)?.let { v ->
                                CustomerVisit(v.optString("date"), v.optString("time"), v.optDouble("amount", 0.0), v.optString("channel"))
                            }
                        },
                    )
                }
            },
        )
    }

    private fun parseInsights(o: JSONObject): HomeInsights {
        fun share(c: JSONObject?) = ChannelShare(
            amount = c?.optDouble("amount", 0.0) ?: 0.0,
            hours = c?.optDouble("hours", 0.0) ?: 0.0,
            count = c?.optInt("count") ?: 0,
        )
        fun split(c: JSONObject?) = ChannelSplit(share(c?.optJSONObject("walk_in")), share(c?.optJSONObject("app")), share(c?.optJSONObject("whatsapp")))
        fun <T> list(a: JSONArray?, map: (JSONObject) -> T): List<T> =
            if (a == null) emptyList() else (0 until a.length()).mapNotNull { a.optJSONObject(it)?.let(map) }

        val week = o.optJSONObject("week") ?: JSONObject()
        val totals = week.optJSONObject("totals") ?: JSONObject()
        val channels = o.optJSONObject("channels")
        val heat = o.optJSONObject("heatmap") ?: JSONObject()
        val tomorrow = o.optJSONObject("tomorrow") ?: JSONObject()
        val hours = heat.optJSONArray("hours")

        return HomeInsights(
            enabled = o.optBoolean("enabled", false),
            week = InsightWeek(
                start = week.optString("start", ""),
                label = week.optString("label", "This week"),
                prev = week.optString("prev", ""),
                next = week.optStringOrNull("next"),
                days = list(week.optJSONArray("days")) { d ->
                    InsightDay(
                        date = d.optString("date"),
                        label = d.optString("label"),
                        day = d.optInt("day"),
                        today = d.optBoolean("today"),
                        future = d.optBoolean("future"),
                        revenue = d.optDouble("revenue", 0.0),
                        bookedHours = d.optDouble("booked_hours", 0.0),
                        totalHours = d.optDouble("total_hours", 0.0),
                        lastRevenue = d.optDouble("last_revenue", 0.0),
                        lastBookedHours = d.optDouble("last_booked_hours", 0.0),
                    )
                },
                revenue = totals.optDouble("revenue", 0.0),
                bookedHours = totals.optDouble("booked_hours", 0.0),
                totalHours = totals.optDouble("total_hours", 0.0),
                lastRevenue = week.optJSONObject("last")?.optDouble("revenue", 0.0) ?: 0.0,
            ),
            channelsToday = split(channels?.optJSONObject("today")),
            channelsWeek = split(channels?.optJSONObject("week")),
            channelsLastWeek = split(channels?.optJSONObject("last_week")),
            heatmap = BusyHours(
                ready = heat.optBoolean("ready", false),
                weeks = heat.optInt("weeks", 4),
                hours = if (hours == null) emptyList() else (0 until hours.length()).map { hours.optString(it) },
                rows = list(heat.optJSONArray("rows")) { r ->
                    val fill = r.optJSONArray("fill")
                    r.optString("label") to (if (fill == null) emptyList() else (0 until fill.length()).map {
                        if (fill.isNull(it)) null else fill.optDouble(it, 0.0).toFloat()
                    })
                },
                quiet = list(heat.optJSONArray("quiet")) { q -> QuietWindow(q.optString("days"), q.optString("hours"), q.optInt("fill")) },
            ),
            haraan = (o.optJSONObject("haraan") ?: JSONObject()).let { h ->
                HaraanBrought(
                    allTime = share(h.optJSONObject("all_time")),
                    thisMonth = share(h.optJSONObject("this_month")),
                    lastMonth = share(h.optJSONObject("last_month")),
                    players = h.optInt("players"),
                    share = if (h.isNull("share") || !h.has("share")) null else h.optDouble("share"),
                )
            },
            growth = (o.optJSONObject("growth") ?: JSONObject()).let { g ->
                Growth(
                    since = g.optStringOrNull("since"),
                    unit = g.optString("unit", "week"),
                    points = list(g.optJSONArray("points")) { p ->
                        GrowthPoint(p.optString("label"), p.optInt("bookings"), p.optDouble("revenue", 0.0))
                    },
                    bookings = g.optInt("bookings"),
                    revenue = g.optDouble("revenue", 0.0),
                )
            },
            milestones = (o.optJSONObject("milestones") ?: JSONObject()).let { m ->
                Milestones(
                    total = m.optInt("total"),
                    reached = list(m.optJSONArray("reached")) { r -> Milestone(r.optInt("count"), r.optString("label"), r.optString("date")) },
                    firstOnline = m.optStringOrNull("first_online"),
                    next = m.optJSONObject("next")?.let { n ->
                        NextMilestone(n.optInt("count"), n.optInt("remaining"), n.optDouble("progress", 0.0).toFloat())
                    },
                )
            },
            customers = (o.optJSONObject("customers") ?: JSONObject()).let { c ->
                fun period(x: JSONObject?, fallback: String) = PeriodCustomers(
                    label = x?.optString("label", fallback) ?: fallback,
                    new = x?.optInt("new") ?: 0,
                    returning = x?.optInt("returning") ?: 0,
                    lastNew = x?.optJSONObject("last")?.optInt("new") ?: 0,
                    lastReturning = x?.optJSONObject("last")?.optInt("returning") ?: 0,
                )
                CustomerSummary(
                    week = period(c.optJSONObject("week"), "This week"),
                    month = period(c.optJSONObject("month"), "This month"),
                    total = c.optInt("total"),
                    cameBack = c.optInt("came_back"),
                )
            },
            tomorrow = TomorrowOpen(
                label = tomorrow.optString("label", "Tomorrow"),
                venues = list(tomorrow.optJSONArray("venues")) { v ->
                    TomorrowVenue(
                        id = v.optLong("id"),
                        name = v.optString("name"),
                        closed = v.optBoolean("closed"),
                        openHours = v.optDouble("open_hours", 0.0),
                        totalHours = v.optDouble("total_hours", 0.0),
                        windows = list(v.optJSONArray("windows")) { w -> w.optString("label") to w.optInt("free_courts") },
                        shareUrl = v.optString("share_url", ""),
                        shareText = v.optString("share_text", ""),
                    )
                },
            ),
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

    /**
     * The Haraan employee assigned to this partner in /control, for the card on top of
     * Venues. `manager` is null when nobody is assigned; the card then offers plain
     * Haraan support on [HaraanManager.supportWhatsapp].
     */
    suspend fun manager(token: String): HaraanManager = withContext(Dispatchers.IO) {
        val o = JSONObject(get("/api/partner/manager", token))
        val d = o.optJSONObject("data")
        HaraanManager(
            name = d?.optStringOrNull("name"),
            photoUrl = d?.optStringOrNull("photo_url"),
            title = d?.optStringOrNull("title"),
            intro = d?.optStringOrNull("intro"),
            hours = d?.optStringOrNull("hours"),
            phone = d?.optStringOrNull("phone"),
            showCall = d?.optBoolean("show_call") ?: false,
            showWhatsapp = d?.optBoolean("show_whatsapp") ?: false,
            showChat = d?.optBoolean("show_chat", true) ?: true,
            supportWhatsapp = o.optStringOrNull("support_whatsapp"),
        )
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

    /** The bookings feed; with [date] ("yyyy-MM-dd") it's every venue booking for that day. */
    suspend fun bookings(token: String, venueId: Long? = null, date: String? = null): List<BookingSummary> = withContext(Dispatchers.IO) {
        val base = "/api/partner/bookings" + branchParam(venueId)
        val url = if (date == null) base else base + (if ('?' in base) "&" else "?") + "date=$date"
        parseArray(get(url, token)) { o ->
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
                amountPaid = o.optDouble("amount_paid", 0.0),
                paymentMethod = o.optStringOrNull("payment_method"),
                slotDate = o.optStringOrNull("slot_date"),
                slotLabel = o.optStringOrNull("slot_label"),
                createdAt = o.optStringOrNull("created_at"),
                phone = o.optStringOrNull("phone"),
                email = o.optStringOrNull("email"),
                contactSent = o.has("phone"),
            )
        }
    }

    suspend fun matches(token: String, venueId: Long? = null): VenueMatches = withContext(Dispatchers.IO) {
        val data = JSONObject(get("/api/partner/matches" + branchParam(venueId), token)).optJSONObject("data")
        VenueMatches(
            confirmed = parseMatches(data?.optJSONArray("confirmed")),
            nearby = parseMatches(data?.optJSONArray("nearby")),
        )
    }

    private fun parseMatches(arr: JSONArray?): List<VenueMatch> {
        if (arr == null) return emptyList()
        return (0 until arr.length()).map { i ->
            val o = arr.getJSONObject(i)
            VenueMatch(
                id = o.optLong("id"),
                sport = o.optString("sport", "cricket"),
                title = o.optString("title", ""),
                home = o.optString("home", ""),
                away = o.optString("away", ""),
                score1 = o.optString("score1", "0"),
                score2 = o.optString("score2", "0"),
                overs = o.optString("overs", ""),
                rally1 = o.optString("rally1", ""),
                rally2 = o.optString("rally2", ""),
                status = o.optString("status", ""),
                isLive = o.optBoolean("isLive"),
                isFinished = o.optBoolean("isFinished"),
                startsAt = o.optStringOrNull("startsAt"),
                time = o.optString("time", ""),
                typedVenue = o.optString("typedVenue", ""),
                venueName = o.optString("venueName", ""),
                branch = o.optString("branch", ""),
                source = o.optString("source", "nearby"),
                distanceM = if (o.isNull("distanceM")) null else o.optInt("distanceM"),
                battingTeam = o.optInt("battingTeam", 1),
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
                    isHeld = c.optBoolean("is_held", false),
                    price = c.optDouble("price", 0.0),
                    isPeak = c.optBoolean("is_peak", false),
                    allowed = c.optBoolean("allowed", true),
                    block = c.optJSONObject("block")?.let { b ->
                        CourtBlock(
                            id = b.optLong("id"),
                            kind = b.optString("kind"),
                            label = b.optString("label"),
                            reason = b.optString("reason"),
                            note = b.optStringOrNull("note"),
                            start = b.optStringOrNull("start"),
                            end = b.optStringOrNull("end"),
                            allDay = b.optBoolean("all_day"),
                            removable = b.optBoolean("removable"),
                        )
                    },
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
                sports = s.optJSONArray("sports").toStringList(),
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
            // The server lists slots in the admin's manual sort_order, so a 7 AM slot
            // added last sat under 11 AM. The desk reads the day top to bottom, so
            // order it by the clock; a time we can't read keeps its place at the end.
            slots = slots.sortedBy { slotStartMinutes(it.time ?: it.label) },
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
    suspend fun reportCsv(token: String, from: String, to: String, by: String = "booked"): String = withContext(Dispatchers.IO) {
        get("/api/partner/reports/bookings?from=$from&to=$to&format=csv&by=$by", token)
    }

    /** The same report as rows, for the in-app preview and the PDF. */
    suspend fun reportRows(token: String, from: String, to: String, by: String = "booked"): ReportData = withContext(Dispatchers.IO) {
        val o = JSONObject(get("/api/partner/reports/bookings?from=$from&to=$to&format=json&by=$by", token))
        val arr = o.optJSONArray("rows") ?: JSONArray()
        ReportData(
            from = o.optString("from", from),
            to = o.optString("to", to),
            partner = o.optString("partner", ""),
            rows = (0 until arr.length()).map { i ->
                val r = arr.getJSONObject(i)
                val amount = r.optString("amount").toDoubleOrNull() ?: 0.0
                val payStatus = r.optString("payment_status", "")
                ReportRow(
                    id = r.optString("id"),
                    bookedAt = r.optString("booked_at"),
                    type = r.optString("type"),
                    item = r.optString("item"),
                    slot = r.optString("slot"),
                    slotDate = r.optString("slot_date"),
                    customer = r.optString("customer"),
                    phone = r.optString("phone"),
                    channel = r.optString("channel"),
                    quantity = r.optString("quantity").toIntOrNull() ?: 1,
                    amount = amount,
                    // Older servers don't send amount_paid; a "paid" booking then counts in full.
                    amountPaid = r.optString("amount_paid").toDoubleOrNull()
                        ?: if (payStatus.equals("paid", true)) amount else 0.0,
                    status = r.optString("status"),
                    paymentStatus = payStatus,
                    checkedIn = r.optString("checked_in").toIntOrNull() ?: 0,
                    ticket = r.optString("ticket"),
                )
            },
        )
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
                sports = o.optJSONArray("sports").toStringList(),
                courtPrices = o.optJSONObject("court_prices")?.let { m ->
                    m.keys().asSequence().mapNotNull { k -> k.toLongOrNull()?.let { id -> id to m.optDouble(k, 0.0) } }
                        .filter { it.second > 0 }.toMap()
                }.orEmpty(),
            )
        }
    }

    /**
     * Build every slot between [open] and [close] in one go. [days] is ["Every day"] or
     * weekday names; [mode] "add" keeps existing slots, "replace" starts over.
     */
    suspend fun generateSlots(
        token: String,
        venueId: Long,
        open: String,
        close: String,
        stepMinutes: Int,
        days: List<String>,
        price: Double?,
        mode: String,
    ): GenerateResult = withContext(Dispatchers.IO) {
        val body = JSONObject()
            .put("open", open).put("close", close).put("step", stepMinutes)
            .put("days", JSONArray(days)).put("mode", mode)
        if (price != null) body.put("price", price)
        val o = JSONObject(post("/api/partner/venues/$venueId/slots/generate", body.toString(), token))
        GenerateResult(created = o.optInt("created"), kept = o.optInt("kept"), removed = o.optInt("removed"))
    }

    /**
     * The venue as players see it, from the public venue API (no token): address, map pin,
     * about, amenities, rules, policy and fees. Only published venues answer; others 404.
     */
    suspend fun publicVenue(venueId: Long): VenueDetails = withContext(Dispatchers.IO) {
        val o = JSONObject(request("GET", "/api/venues/$venueId", null, null)).getJSONObject("data")
        fun list(k: String) = o.optJSONArray(k).toStringList()
        val fees = o.optJSONArray("fees")?.let { a ->
            (0 until a.length()).mapNotNull { i ->
                val f = a.optJSONObject(i) ?: return@mapNotNull null
                val v = f.optDouble("value", 0.0)
                if (v <= 0) return@mapNotNull null
                val amount = if (f.optString("type") == "percent") formatInr(v) + "%" else "₹" + formatInr(v)
                f.optString("label").ifBlank { "Fee" }.replaceFirstChar { it.uppercase() } + " " + amount
            }
        }.orEmpty()
        VenueDetails(
            address = o.optStringOrNull("address"),
            latitude = if (o.isNull("latitude")) null else o.optDouble("latitude"),
            longitude = if (o.isNull("longitude")) null else o.optDouble("longitude"),
            mapLink = o.optStringOrNull("map_link"),
            about = o.optStringOrNull("about"),
            tagline = o.optStringOrNull("tagline")?.trim()?.trimStart('-', '–', ' ')?.takeIf { it.isNotBlank() },
            amenities = list("amenities"),
            rules = list("rules"),
            cancellation = o.optStringOrNull("cancellation"),
            fees = fees,
            images = list("images"),
            rating = o.optStringOrNull("rating")?.toDoubleOrNull()?.takeIf { it > 0 },
            // Counted off real reviews only: old rows still carry the column's seeded
            // "4.2 from 120 ratings" with no review behind them.
            ratingsCount = o.optInt("reviews_count", 0),
            category = o.optStringOrNull("category"),
        )
    }

    /** The venue's opening hours. Throws ApiException(404) on a server that predates hours editing. */
    suspend fun venueHours(token: String, venueId: Long): VenueHours = withContext(Dispatchers.IO) {
        parseHours(JSONObject(get("/api/partner/venues/$venueId/hours", token)))
    }

    /** Save opening hours; the server rebuilds the slot template from them. */
    suspend fun saveVenueHours(token: String, venueId: Long, hours: VenueHours): VenueHours = withContext(Dispatchers.IO) {
        fun hm(m: Int) = "%02d:%02d".format((m / 60) % 24, m % 60)
        val days = JSONObject()
        VenueHours.KEYS.forEach { k ->
            val d = hours.days[k]
            days.put(k, if (d == null) JSONObject.NULL else JSONObject().put("open", hm(d.first)).put("close", hm(d.second)))
        }
        val body = JSONObject().put("days", days).put("slot_minutes", hours.slotMinutes)
        parseHours(JSONObject(post("/api/partner/venues/$venueId/hours", body.toString(), token)))
    }

    private fun parseHours(o: JSONObject): VenueHours {
        val d = o.optJSONObject("days")
        fun mins(t: String?): Int? = t?.split(":")?.let { p -> p.getOrNull(0)?.toIntOrNull()?.let { h -> h * 60 + (p.getOrNull(1)?.toIntOrNull() ?: 0) } }
        return VenueHours(
            set = o.optBoolean("set", false),
            days = VenueHours.KEYS.associateWith { k ->
                d?.optJSONObject(k)?.let { h ->
                    val a = mins(h.optStringOrNull("open")); val b = mins(h.optStringOrNull("close"))
                    if (a != null && b != null) a to b else null
                }
            },
            slotMinutes = o.optInt("slot_minutes", 60),
        )
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
    suspend fun saveSlot(
        token: String,
        venueId: Long,
        slotId: Long?,
        day: String?,
        time: String,
        price: Double,
        capacity: Int,
        isOpen: Boolean,
        sports: List<String> = emptyList(),
        /** Court id → price; null leaves the slot's per-court prices as they are. */
        courtPrices: Map<Long, Double>? = null,
    ) = withContext(Dispatchers.IO) {
        val payload = JSONObject()
            .put("time", time).put("price", price).put("capacity", capacity).put("isOpen", isOpen)
            // Always sent, because an empty list is a real answer — "this time runs
            // for every sport" — and omitting the key means "leave it unchanged".
            .put("sports", JSONArray(sports))
        // Every court is sent (0 = no own price), so clearing a box really clears it.
        courtPrices?.let { m -> payload.put("courtPrices", JSONObject().apply { m.forEach { (id, p) -> put(id.toString(), p) } }) }
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
            // This build opens Razorpay's own checkout, prefilled, instead of its web page.
            .put("nativeCheckout", true)
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
            payment = parseDeskPayment(o.optJSONObject("payment")),
        )
    }

    private fun parseDeskPayment(p: JSONObject?): DeskPayment? = p?.let {
        val kind = it.optString("kind", "link")
        DeskPayment(
            kind = kind,
            present = it.optStringOrNull("present") ?: if (kind == "upi_qr") "qr" else "share",
            id = it.optStringOrNull("id"),
            qr = it.optStringOrNull("qr"),
            url = it.optStringOrNull("url"),
            imageUrl = it.optStringOrNull("image_url"),
            amount = it.optDouble("amount", 0.0),
            expiresInSeconds = it.optInt("expires_in", 300),
            error = it.optStringOrNull("error"),
            checkout = it.optJSONObject("checkout")?.let { c ->
                val key = c.optStringOrNull("key")
                val order = c.optStringOrNull("order_id")
                if (key == null || order == null) null else DeskCheckout(
                    key = key,
                    orderId = order,
                    name = c.optString("name", "Haraan"),
                    description = c.optString("description", ""),
                    contact = c.optStringOrNull("contact"),
                    customer = c.optStringOrNull("customer"),
                )
            },
        )
    }

    /**
     * Has the desk's UPI QR or link been paid? [close] stops it taking money first (the
     * timer ran out) and still reports a payment that landed just before.
     */
    suspend fun deskPaymentStatus(token: String, bookingId: Long, payment: DeskPayment, close: Boolean = false): PayState =
        withContext(Dispatchers.IO) {
            val field = when (payment.kind) { "upi_qr" -> "qrId"; "order" -> "orderId"; else -> "linkId" }
            val payload = JSONObject().put(field, payment.id).put("close", close)
            val o = JSONObject(post("/api/partner/bookings/$bookingId/payment-status", payload.toString(), token))
            PayState(paid = o.optBoolean("paid", false), status = o.optString("status", "unknown"))
        }

    /** A fresh QR/link for the balance; the one it replaces is closed first. */
    suspend fun deskPaymentRequest(token: String, bookingId: Long, kind: String, replacing: DeskPayment?): PaymentRequestResult =
        withContext(Dispatchers.IO) {
            val payload = JSONObject().put("kind", kind).put("nativeCheckout", true)
            if (replacing?.id != null) payload.put("replaceKind", replacing.kind).put("replaceId", replacing.id)
            val o = JSONObject(post("/api/partner/bookings/$bookingId/payment-request", payload.toString(), token))
            PaymentRequestResult(paid = o.optBoolean("paid", false), payment = parseDeskPayment(o.optJSONObject("payment")))
        }

    /**
     * The customer paid the balance at the desk after all. Closes the open QR/link first;
     * returns "online" when it turns out they had already paid it.
     */
    suspend fun collectAtDesk(token: String, bookingId: Long, method: PayMethod, open: DeskPayment?): String =
        withContext(Dispatchers.IO) {
            val payload = JSONObject().put("method", method.api)
            if (open?.id != null) payload.put("closeKind", open.kind).put("closeId", open.id)
            JSONObject(post("/api/partner/bookings/$bookingId/collect", payload.toString(), token)).optString("via", method.api)
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

    /**
     * Take one court off sale from [start] to [end] ("HH:mm", end may be "24:00") on
     * [date]. The server refuses it over any booking or hold, with a message to show.
     */
    suspend fun blockCourt(
        token: String, venueId: Long, courtId: Long, date: String,
        start: String, end: String, kind: String, note: String?,
    ) = withContext(Dispatchers.IO) {
        post(
            "/api/partner/venues/$venueId/court-blocks",
            JSONObject().put("court_id", courtId).put("date", date).put("start", start).put("end", end)
                .put("kind", kind).put("note", note?.takeIf { it.isNotBlank() } ?: JSONObject.NULL).toString(),
            token,
        )
        Unit
    }

    suspend fun unblockCourt(token: String, venueId: Long, blockId: Long) = withContext(Dispatchers.IO) {
        request("DELETE", "/api/partner/venues/$venueId/court-blocks/$blockId", null, token)
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
        // A cancelled or refunded ticket comes back as 409 with the booking in the
        // body. It used to surface as "Something went wrong (HTTP 409)", which told
        // the gate nothing; it is an answer about the ticket, so read it as one.
        val body = try {
            post("/api/partner/check-in", payload.toString(), token)
        } catch (e: ApiException) {
            if (e.code == 409 && e.body != null) e.body else throw e
        }
        val o = JSONObject(body)
        val status = o.optString("status", "ok")
        val message = when (status) {
            "ok" -> "Checked in"
            "already" -> "Already checked in"
            "invalid" -> "Ticket is cancelled/invalid"
            else -> "Done"
        }
        val booking = o.optJSONObject("booking")
        CheckInResult(
            status = status,
            message = message,
            guest = booking?.optString("customer")?.trim()?.takeIf { it.isNotEmpty() && it != "null" },
            quantity = booking?.optInt("quantity", 0) ?: 0,
            slotLabel = booking?.optString("slot_label")?.trim()?.takeIf { it.isNotEmpty() && it != "null" },
        )
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
            if (code !in 200..299) throw ApiException(code, parseError(body, code), body)
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
