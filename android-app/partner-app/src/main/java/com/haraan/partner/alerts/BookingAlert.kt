package com.haraan.partner.alerts

import com.haraan.partner.BookingSummary
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow

/** A booking that just landed, in the words the alert card uses. */
data class BookingAlert(
    val id: Long,
    val customer: String,
    val amount: Double,
    val paid: Double,
    val walkIn: Boolean,
    /** "padi", "Vaddeswaram Masjid" — whatever names the place it's for. */
    val where: String?,
    /** "7:00 PM" */
    val time: String?,
    /** "Today", "Tomorrow", "Sat, 4 Oct" */
    val day: String?,
    /** How many more arrived in the same check, beyond this one. */
    val more: Int = 0,
) {
    val fullyPaid: Boolean get() = paid > 0 && paid + 0.5 >= amount

    companion object {
        fun from(b: BookingSummary, more: Int = 0): BookingAlert {
            // slot_label reads "Wednesday · 7:00 PM", "7:00 PM" or "6:00 AM – 7:00 AM";
            // the card wants the start alone, so the day still fits on the line.
            val time = b.slotLabel?.substringAfterLast("·")?.split("–", "-", " to ")?.firstOrNull()
                ?.trim()?.takeIf { it.isNotBlank() }
            return BookingAlert(
                id = b.id,
                customer = b.customer,
                amount = b.amount,
                paid = b.amountPaid,
                walkIn = b.channel.equals("offline", true),
                where = b.branch ?: b.label,
                time = time,
                day = dayWord(b.slotDate),
                more = more,
            )
        }

        /**
         * The bookings worth an alert since [last], newest first, and where the next
         * check should start from.
         *
         * A hold isn't a booking yet: a walk-in paying by UPI or an app checkout sits
         * PENDING until the money lands, and lapses if it never does. Announcing it said
         * "Walk-in booked" before anyone had paid. So holds are skipped, and the mark
         * stops just below the oldest one still waiting, to announce it the moment it's
         * confirmed. Anything already announced above that mark is remembered, not repeated.
         */
        fun pickFresh(list: List<BookingSummary>, last: Long): Pair<List<BookingSummary>, Long> {
            val newer = list.filter { it.id > last }
            val oldestHold = newer.filter(::isHold).minOfOrNull { it.id }
            val ready = newer
                .filter { !isHold(it) && !isDead(it) && Announced.claim(it.id) }
                .sortedByDescending { it.id }
            val mark = oldestHold?.let { it - 1 } ?: (list.maxOfOrNull { it.id } ?: last)
            return ready to maxOf(last, mark)
        }

        private fun isHold(b: BookingSummary) = b.status.equals("pending", true)

        private fun isDead(b: BookingSummary) =
            b.status?.lowercase() in setOf("cancelled", "expired", "failed", "refunded")

        private fun dayWord(ymd: String?): String? {
            if (ymd.isNullOrBlank()) return null
            val fmt = java.text.SimpleDateFormat("yyyy-MM-dd", java.util.Locale.US)
            val d = runCatching { fmt.parse(ymd.take(10)) }.getOrNull() ?: return null
            val today = fmt.format(java.util.Date())
            val tomorrow = fmt.format(java.util.Date(System.currentTimeMillis() + 86_400_000L))
            return when (ymd.take(10)) {
                today -> "Today"
                tomorrow -> "Tomorrow"
                else -> java.text.SimpleDateFormat("EEE, d MMM", java.util.Locale.ENGLISH).format(d)
            }
        }
    }
}

/** Bookings already announced in this process, so one confirmed above a waiting hold rings once. */
private object Announced {
    private val ids = java.util.Collections.synchronizedSet(LinkedHashSet<Long>())

    /** True the first time an id is seen. */
    fun claim(id: Long): Boolean = ids.add(id).also { if (ids.size > 500) synchronized(ids) { ids.remove(ids.first()) } }
}

/**
 * Where a tap on an alert asks the app to go. The alert can fire with the app closed,
 * so it hands the request over through here and MainActivity/PartnerApp pick it up.
 */
object AlertRouter {
    private val _openBookings = MutableStateFlow(false)
    val openBookings: StateFlow<Boolean> = _openBookings

    fun requestBookings() { _openBookings.value = true }
    fun consumed() { _openBookings.value = false }

    const val EXTRA_OPEN = "haraan.open"
    const val OPEN_BOOKINGS = "bookings"
}
