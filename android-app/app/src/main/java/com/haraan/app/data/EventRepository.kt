package com.haraan.app.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.net.URL

/** One row of an inventory-gated dynamic-pricing schedule (early bird → phase 2). */
data class PricingPhase(
    val label: String,
    val price: Double,
    val from: Int,          // first spot number at this price (1-based)
    val to: Int,            // last spot number at this price
    val current: Boolean,   // the phase a buyer pays now
    val soldOut: Boolean,
)

/** One priced ticket tier for an event (GET /api/events/{id} → ticketTypes). */
data class EventTicketType(
    val id: Int,
    val name: String,
    val kind: String,       // standard | addon | donation
    val price: Double,      // live price now (current phase, else flat)
    val basePrice: Double,  // the tier's headline/flat price
    val admits: Int,        // people admitted per ticket (bundles); 1 = normal
    val minPrice: Double?,  // pay-what-you-want floor for donation tiers
    val remaining: Int?,    // null = unlimited (bounded by event slots)
    val onSale: Boolean,    // false when outside its sales window
    val phases: List<PricingPhase> = emptyList(), // empty = flat price
)

/** One "Good to Know" row (icon key + label + value), assembled by the API. */
data class GoodToKnowItem(
    val icon: String,   // stable key the UI maps to a vector: language|age|kids|pets|layout|seating|duration|entry|info
    val label: String,
    val value: String,
)

/** One row of an event's run-of-show (GET /api/events/{id} → schedule). */
data class ScheduleEntry(
    val time: String,
    val title: String,
    val note: String,
)

/** One performer in the "Who takes the stage" lineup carousel. */
data class LineupArtist(
    val name: String,
    val subtitle: String,
    val imageUrl: String,
)

/** Detail payload for one event: sellable tiers plus the admin-authored "know before you go" content. */
data class EventDetailInfo(
    val ticketTypes: List<EventTicketType> = emptyList(),
    val infoNotes: List<String> = emptyList(),
    val goodToKnow: List<GoodToKnowItem> = emptyList(),
    val schedule: List<ScheduleEntry> = emptyList(),
    val lineup: List<LineupArtist> = emptyList(),
    val fullDate: String = "",   // "Sun, 5 Jul, 8:00 PM" built from date + time
    val description: String = "", // the admin's real event description (Overview)
    val city: String = "",       // e.g. "Hyderabad" — shown beside the date line
    val mapLink: String = "",    // pasted Google Maps link; drives "Directions"
    val feeType: String = "none", // convenience fee: none | flat | percent
    val feeValue: Double = 0.0,   // ₹ amount (flat) or % of subtotal (percent)
    val rating: Double = 0.0,     // aggregate rating; 0 = unrated (hide the star)
    val ratingsCount: Int = 0,    // how many people rated it
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
    val rating: Double,      // 0 = unrated (card hides the star badge)
    val placements: List<String>, // curated rails: for_you | trending | nearby
    val city: String,        // e.g. "Kadapa" — used to float local events first
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
        // Conditional GET: the 30s Events poll gets a 304 (served from cache) while the
        // events list is unchanged.
        val body = ConditionalHttp.getText("${baseUrl}/api/events")
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
                rating = if (o.isNull("rating")) 0.0 else o.optDouble("rating", 0.0),
                placements = o.optJSONArray("placements")?.let { arr ->
                    (0 until arr.length()).mapNotNull { arr.optString(it).takeIf { s -> s.isNotBlank() } }
                }.orEmpty(),
                city = o.optString("city").takeIf { it.isNotBlank() && it != "null" } ?: "",
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
                val price = o.optDouble("price", 0.0)
                EventTicketType(
                    id = o.optInt("id"),
                    name = o.optString("name"),
                    kind = o.optString("kind", "standard"),
                    price = price,
                    basePrice = o.optDouble("basePrice", price),
                    admits = o.optInt("admits", 1).coerceAtLeast(1),
                    minPrice = if (o.isNull("minPrice")) null else o.optDouble("minPrice"),
                    remaining = if (o.isNull("remaining")) null else o.optInt("remaining"),
                    onSale = o.optBoolean("onSale", true),
                    phases = o.optJSONArray("phases")?.let { ph ->
                        (0 until ph.length()).mapNotNull { j ->
                            val po = ph.optJSONObject(j) ?: return@mapNotNull null
                            PricingPhase(
                                label = po.optString("label"),
                                price = po.optDouble("price", 0.0),
                                from = po.optInt("from", 0),
                                to = po.optInt("to", 0),
                                current = po.optBoolean("current", false),
                                soldOut = po.optBoolean("soldOut", false),
                            )
                        }
                    }.orEmpty(),
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

        val schedule = d.optJSONArray("schedule")?.let { arr ->
            (0 until arr.length()).mapNotNull { i ->
                val o = arr.optJSONObject(i) ?: return@mapNotNull null
                val time = o.optString("time")
                if (time.isBlank()) return@mapNotNull null
                ScheduleEntry(
                    time = time,
                    title = o.optString("title"),
                    note = o.optString("note"),
                )
            }
        }.orEmpty()

        val lineup = d.optJSONArray("lineup")?.let { arr ->
            (0 until arr.length()).mapNotNull { i ->
                val o = arr.optJSONObject(i) ?: return@mapNotNull null
                val name = o.optString("name")
                if (name.isBlank()) return@mapNotNull null
                LineupArtist(
                    name = name,
                    subtitle = o.optString("subtitle"),
                    imageUrl = resolveImage(o.optString("image")),
                )
            }
        }.orEmpty()

        val fullDate = formatFullDate(d.optString("date"), d.optString("time"))

        val fee = d.optJSONObject("convenienceFee")

        EventDetailInfo(
            ticketTypes = tickets,
            infoNotes = notes,
            goodToKnow = goodToKnow,
            schedule = schedule,
            lineup = lineup,
            fullDate = fullDate,
            description = d.optString("description").takeIf { it.isNotBlank() && it != "null" } ?: "",
            city = d.optString("city").takeIf { it.isNotBlank() && it != "null" } ?: "",
            mapLink = d.optString("mapLink").takeIf { it.isNotBlank() && it != "null" } ?: "",
            feeType = fee?.optString("type", "none") ?: "none",
            feeValue = fee?.optDouble("value", 0.0) ?: 0.0,
            rating = if (d.isNull("rating")) 0.0 else d.optDouble("rating", 0.0),
            ratingsCount = d.optInt("ratingsCount", 0),
        )
    }

    /**
     * Build a rich header date like "Sun, 5 Jul, 8:00 PM" from the ISO date and
     * the free-text time. SimpleDateFormat is safe on minSdk 24. Falls back to
     * just the time (or blank) when the date can't be parsed.
     */
    private fun formatFullDate(isoDate: String, time: String): String {
        val datePart = runCatching {
            val parser = java.text.SimpleDateFormat("yyyy-MM-dd", java.util.Locale.ENGLISH)
            val d = parser.parse(isoDate.take(10)) ?: return@runCatching null
            java.text.SimpleDateFormat("EEE, d MMM", java.util.Locale.ENGLISH).format(d)
        }.getOrNull()
        return listOfNotNull(datePart, time.takeIf { it.isNotBlank() }).joinToString(", ")
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
