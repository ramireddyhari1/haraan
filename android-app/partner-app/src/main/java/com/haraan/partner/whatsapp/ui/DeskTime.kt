package com.haraan.partner.whatsapp.ui

import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Date
import java.util.Locale
import java.util.TimeZone

/** Server timestamps (UTC ISO-8601) and slot times, as the desk reads them. */
internal object DeskTime {

    private fun iso(): SimpleDateFormat =
        SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss", Locale.US).apply { timeZone = TimeZone.getTimeZone("UTC") }

    fun parse(isoString: String?): Date? {
        if (isoString.isNullOrBlank()) return null
        return runCatching { iso().parse(isoString.take(19)) }.getOrNull()
    }

    /** "4:05 PM" today, "Yesterday", "Mon", or "12 Sep". */
    fun listStamp(isoString: String?): String {
        val date = parse(isoString) ?: return ""
        val now = Calendar.getInstance()
        val then = Calendar.getInstance().apply { time = date }
        val sameYear = now.get(Calendar.YEAR) == then.get(Calendar.YEAR)
        val dayDiff = if (sameYear) now.get(Calendar.DAY_OF_YEAR) - then.get(Calendar.DAY_OF_YEAR) else 99

        return when {
            dayDiff == 0 -> SimpleDateFormat("h:mm a", Locale.getDefault()).format(date)
            dayDiff == 1 -> "Yesterday"
            dayDiff in 2..6 -> SimpleDateFormat("EEE", Locale.getDefault()).format(date)
            else -> SimpleDateFormat("d MMM", Locale.getDefault()).format(date)
        }
    }

    /** "4:05 PM" — a bubble's clock time. */
    fun clock(isoString: String?): String =
        parse(isoString)?.let { SimpleDateFormat("h:mm a", Locale.getDefault()).format(it) } ?: ""

    /** "18:30:00" → "6:30 PM"; "24:00" → "12:00 AM". */
    fun slot(time: String?): String {
        if (time.isNullOrBlank()) return ""
        val parts = time.split(":")
        val h = parts.getOrNull(0)?.toIntOrNull() ?: return time
        val m = parts.getOrNull(1)?.toIntOrNull() ?: 0
        val h12 = when (val x = h % 12) { 0 -> 12; else -> x }
        val ampm = if (h % 24 < 12) "AM" else "PM"
        return if (m == 0) "$h12 $ampm" else "$h12:${m.toString().padStart(2, '0')} $ampm"
    }

    /** "2026-09-27" → "Sun 27 Sep" (or "Today" / "Tomorrow"). */
    fun day(ymd: String?): String {
        if (ymd.isNullOrBlank()) return ""
        val fmt = SimpleDateFormat("yyyy-MM-dd", Locale.US)
        val date = runCatching { fmt.parse(ymd.take(10)) }.getOrNull() ?: return ymd
        val today = fmt.format(Date())
        val tomorrow = fmt.format(Date(System.currentTimeMillis() + 86_400_000L))
        return when (ymd.take(10)) {
            today -> "Today"
            tomorrow -> "Tomorrow"
            else -> SimpleDateFormat("EEE d MMM", Locale.getDefault()).format(date)
        }
    }

    /** The next [count] days as yyyy-MM-dd, starting today. */
    fun nextDays(count: Int): List<String> {
        val fmt = SimpleDateFormat("yyyy-MM-dd", Locale.US)
        val cal = Calendar.getInstance()
        return (0 until count).map {
            fmt.format(cal.time).also { cal.add(Calendar.DAY_OF_YEAR, 1) }
        }
    }

    /** Minutes from midnight for "HH:mm[:ss]". */
    fun minutes(time: String): Int? {
        val p = time.split(":")
        val h = p.getOrNull(0)?.toIntOrNull() ?: return null
        val m = p.getOrNull(1)?.toIntOrNull() ?: 0
        return h * 60 + m
    }

    fun hms(minutes: Int): String = "%02d:%02d:00".format(Locale.US, minutes / 60, minutes % 60)

    fun rupees(amount: Double): String =
        "₹" + java.text.NumberFormat.getIntegerInstance(Locale("en", "IN")).format(Math.round(amount))
}
