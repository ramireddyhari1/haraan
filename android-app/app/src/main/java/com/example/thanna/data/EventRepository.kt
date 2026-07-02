package com.example.thanna.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.net.URL

/** One priced ticket tier for an event (GET /api/events/{id} → ticketTypes). */
data class EventTicketType(
    val id: Int,
    val name: String,
    val kind: String,       // standard | addon | donation
    val price: Double,
    val admits: Int,        // people admitted per ticket (bundles); 1 = normal
    val minPrice: Double?,  // pay-what-you-want floor for donation tiers
    val remaining: Int?,    // null = unlimited (bounded by event slots)
    val onSale: Boolean,    // false when outside its sales window
)

/** One "Good to Know" row (icon key + label + value), assembled by the API. */
data class GoodToKnowItem(
    val icon: String,   // stable key the UI maps to a vector: language|age|kids|pets|layout|seating|duration|entry|info
    val label: String,
    val value: String,
)

/** Detail payload for one event: sellable tiers plus the admin-authored "know before you go" content. */
data class EventDetailInfo(
    val ticketTypes: List<EventTicketType> = emptyList(),
    val infoNotes: List<String> = emptyList(),
    val goodToKnow: List<GoodToKnowItem> = emptyList(),
)

/** Browse-card shape for an event, sourced from the real API rather than sample data. */
data class EventApiItem(
    val id: String,
    val title: String,
    val date: String,        // display-formatted, e.g. "Sat, 13 Jun • 7:00 PM"
    val venue: String,
    val price: String,       // display-formatted, e.g. "₹599 onwards"
    val category: String,
    val imageUrl: String,
    val isFillingFast: Boolean,
)

/**
 * Reads events + their ticket tiers from the Laravel API. Mirrors [VenueRepository]:
 * list fields for the browse cards, plus a detail call the booking bar uses to load
 * the sellable tiers.
 */
class EventRepository(
    private val baseUrl: String = ApiConfig.BASE_URL,
) {
    /** Public events for the Events tab. Empty on failure so callers can fall back to samples. */
    suspend fun getEvents(): List<EventApiItem> = withContext(Dispatchers.IO) {
        val body = runCatching { URL("${baseUrl}/api/events").readText() }.getOrNull()
            ?: return@withContext emptyList()
        val arr = runCatching { JSONObject(body).optJSONArray("data") }.getOrNull()
            ?: runCatching { JSONArray(body) }.getOrNull()
            ?: return@withContext emptyList()

        (0 until arr.length()).map { i ->
            val o = arr.getJSONObject(i)
            val priceNum = o.optDouble("price", 0.0)
            EventApiItem(
                id = o.optString("id"),
                title = o.optString("title"),
                date = formatWhen(o.optString("date"), o.optString("time")),
                venue = o.optString("venue").ifBlank { o.optString("location") },
                price = formatPrice(priceNum),
                category = o.optString("category"),
                imageUrl = resolveImage(firstImage(o)),
                // Filling-fast heuristic: fewer than 15% of slots left.
                isFillingFast = run {
                    val total = o.optInt("totalSlots", 0)
                    val avail = o.optInt("availableSlots", total)
                    total > 0 && avail.toDouble() / total <= 0.15
                },
            )
        }
    }

    /**
     * Full detail for one event in a single call: sellable tiers plus the
     * admin-authored "Good to Know" attributes and T&C notes. Blank on failure
     * so the detail screen can fall back to whatever it already has.
     */
    suspend fun getEventDetail(eventId: String): EventDetailInfo = withContext(Dispatchers.IO) {
        val body = runCatching { URL("${baseUrl}/api/events/$eventId").readText() }.getOrNull()
            ?: return@withContext EventDetailInfo()
        val d = runCatching { JSONObject(body).optJSONObject("data") }.getOrNull()
            ?: return@withContext EventDetailInfo()

        val tickets = d.optJSONArray("ticketTypes")?.let { arr ->
            (0 until arr.length()).map { i ->
                val o = arr.getJSONObject(i)
                EventTicketType(
                    id = o.optInt("id"),
                    name = o.optString("name"),
                    kind = o.optString("kind", "standard"),
                    price = o.optDouble("price", 0.0),
                    admits = o.optInt("admits", 1).coerceAtLeast(1),
                    minPrice = if (o.isNull("minPrice")) null else o.optDouble("minPrice"),
                    remaining = if (o.isNull("remaining")) null else o.optInt("remaining"),
                    onSale = o.optBoolean("onSale", true),
                )
            }.filter { it.onSale } // hide tiers outside their sales window
        }.orEmpty()

        val notes = d.optJSONArray("infoNotes")?.let { arr ->
            (0 until arr.length()).mapNotNull { i -> arr.optString(i).takeIf { it.isNotBlank() } }
        }.orEmpty()

        val goodToKnow = d.optJSONArray("goodToKnow")?.let { arr ->
            (0 until arr.length()).mapNotNull { i ->
                val o = arr.optJSONObject(i) ?: return@mapNotNull null
                val value = o.optString("value")
                if (value.isBlank()) return@mapNotNull null
                GoodToKnowItem(
                    icon = o.optString("icon", "info"),
                    label = o.optString("label"),
                    value = value,
                )
            }
        }.orEmpty()

        EventDetailInfo(ticketTypes = tickets, infoNotes = notes, goodToKnow = goodToKnow)
    }

    /** Sellable ticket tiers for one event. Empty when the event has none (flat-price event). */
    suspend fun getEventTickets(eventId: String): List<EventTicketType> =
        getEventDetail(eventId).ticketTypes

    private fun firstImage(o: JSONObject): String {
        o.optJSONArray("images")?.let { a ->
            for (i in 0 until a.length()) {
                val s = a.optString(i)
                if (s.isNotBlank() && s != "null") return s
            }
        }
        return o.optString("imageUrl")
    }

    /** Storage-relative paths get the API host prefixed; absolute URLs pass through. */
    private fun resolveImage(path: String): String = when {
        path.isBlank() || path == "null" -> ""
        path.startsWith("http") -> path
        path.startsWith("/") -> "$baseUrl$path"
        else -> "$baseUrl/storage/$path"
    }

    private fun formatPrice(price: Double): String {
        val whole = price.toLong()
        val formatted = "%,d".format(whole)
        return if (price <= 0.0) "Free" else "₹$formatted onwards"
    }

    /**
     * Combine the ISO date and free-text time into a compact "13 Jun • 7:00 PM".
     * Hand-rolled (no java.time) so it stays safe on minSdk 24 without desugaring.
     */
    private fun formatWhen(isoDate: String, time: String): String {
        val datePart = runCatching {
            // Expect "yyyy-MM-dd..." — take the first 10 chars.
            val parts = isoDate.take(10).split("-")
            val month = MONTHS.getOrNull(parts[1].toInt() - 1)
            val day = parts[2].toInt()
            if (month != null) "$day $month" else null
        }.getOrNull()
        return listOfNotNull(datePart, time.takeIf { it.isNotBlank() })
            .joinToString(" • ")
            .ifBlank { isoDate }
    }

    private companion object {
        val MONTHS = listOf(
            "Jan", "Feb", "Mar", "Apr", "May", "Jun",
            "Jul", "Aug", "Sep", "Oct", "Nov", "Dec",
        )
    }
}
