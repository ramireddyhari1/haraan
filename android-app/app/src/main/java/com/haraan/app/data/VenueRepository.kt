package com.haraan.app.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL

data class VenueApiItem(
    val id: String,
    val name: String,
    val location: String,
    val rating: String,
    val category: String,
    /** All sports playable at this venue (category first). Never empty. */
    val sports: List<String>,
    val price: Int,
    val image: String?,
    /** Every venue photo, first = [image]. Empty on servers that only send `image`. */
    val images: List<String> = emptyList(),
    val tagline: String,
    val distance: String,
    val isBookable: Boolean = false,
    // Coordinates for real GPS-distance ranking + radius filtering. Null when the
    // admin hasn't pinned the venue yet (older rows) — such venues fall back to the
    // static [distance] string and are never radius-filtered out.
    val latitude: Double? = null,
    val longitude: Double? = null,
)

/** One bookable slot template for a venue (GET /api/venues/{id} → slots). */
data class VenueSlotItem(
    val id: Int,
    val day: String,
    val time: String,
    val available: Boolean,
    val fillingFast: Boolean,
    val price: Int = 0,
    val capacity: Int = 1,
    /** Sports this time runs for; empty = all of them. */
    val sports: List<String> = emptyList(),
)

/**
 * Whether one slot can really be booked on a given date (GET /api/venues/{id}/availability).
 *
 * Unlike [VenueSlotItem.available] — the venue's own template switch — this is computed
 * server-side against occupying bookings, live checkout holds and court blocks, with the same
 * rules checkout enforces.
 */
data class SlotAvailability(
    val slotId: Int,
    val state: State,
    /** Courts that can still take this hour (1 for venues that don't model courts). */
    val courtsFree: Int,
    val courtsTotal: Int,
) {
    enum class State { OPEN, BOOKED, CLOSED }
}

/** A single user review on a venue detail page. */
data class VenueReviewItem(
    val name: String,
    val rating: Int,
    val text: String,
    val ago: String = "",
)

/** One price row in the admin-authored price chart (a time band → hourly rate). */
data class VenuePriceBand(val time: String, val price: Int)

/** A day grouping inside a price-chart variant (e.g. "Mon–Fri" → bands). */
data class VenuePriceGroup(val days: String, val rows: List<VenuePriceBand>)

/** A price-chart variant (e.g. a sport / court type) → day groups. */
data class VenuePriceVariant(val label: String, val groups: List<VenuePriceGroup>)

/**
 * A bookable physical unit inside a venue (court / pitch / lane). [sports] are the games it
 * can host — the booking form filters courts by the chosen sport. [price] is its own hourly
 * rate (already resolved by the backend to the venue price when the court sets none).
 */
data class VenueCourt(
    val id: Int,
    val name: String,
    val sports: List<String>,
    val price: Int,
    // Optional peak pricing. [peakPrice] null = none; [peakDays] are 3-letter names (empty = every
    // day); [peakStart]/[peakEnd] are "HH:MM" (null = all day). Clients apply peak when day+time match.
    val peakPrice: Int? = null,
    val peakDays: List<String> = emptyList(),
    val peakStart: String? = null,
    val peakEnd: String? = null,
)

/**
 * Rich venue detail backing the "view → trust → book" page. Assembled from
 * GET /api/venues/{id}. Any field the backend omits defaults to empty so the UI
 * degrades gracefully.
 */
data class VenueDetailData(
    val id: String,
    val name: String,
    val category: String,
    val location: String,
    val address: String,
    val distance: String,
    val rating: String,
    val price: Int,
    val ratingsCount: Int,
    val reviewsCount: Int,
    val isBookable: Boolean,
    val isFeatured: Boolean,
    val images: List<String>,
    val amenities: List<String>,
    val courts: List<VenueCourt>,
    val sports: List<String>,
    val about: String,
    val hours: String,
    val rules: List<String>,
    val cancellation: String,
    val priceNote: String,
    val latitude: Double?,
    val longitude: Double?,
    val mapLink: String,
    val slots: List<VenueSlotItem>,
    val reviews: List<VenueReviewItem>,
    val priceChart: List<VenuePriceVariant>,
    // Booking fee, mirroring the backend's two columns: "none" | "flat" | "percent".
    // Read through [convenienceFeeOn] rather than by hand — the order summary has to
    // quote the fee the server will actually add, or the total on screen isn't the
    // total charged.
    val convenienceFeeType: String = "none",
    val convenienceFeeValue: Double = 0.0,
) {
    /** The fee this venue adds to a [subtotal], rounded to whole rupees like the summary. */
    fun convenienceFeeOn(subtotal: Int): Int {
        if (subtotal <= 0 || convenienceFeeValue <= 0.0) return 0
        return when (convenienceFeeType.lowercase()) {
            "flat" -> kotlin.math.round(convenienceFeeValue).toInt()
            "percent" -> kotlin.math.round(subtotal * convenienceFeeValue / 100.0).toInt()
            else -> 0
        }
    }
}

/** Outcome of submitting a venue review. */
sealed interface ReviewResult {
    data object Success : ReviewResult
    data class Error(val message: String) : ReviewResult
}

class VenueRepository {
    suspend fun getVenues(): List<VenueApiItem> = withContext(Dispatchers.IO) {
        // Conditional GET: the 20s home poll gets a 304 (served from cache) while the
        // venue list is unchanged. Null (nothing cached + failure) → empty list.
        val body = ConditionalHttp.getText("${ApiConfig.BASE_URL}/api/venues")
            ?: return@withContext emptyList()
        // Endpoint returns {"data":[...]}; tolerate a bare array too for safety.
        val arr = runCatching { JSONObject(body).optJSONArray("data") }.getOrNull()
            ?: runCatching { JSONArray(body) }.getOrNull()
            ?: JSONArray()
        (0 until arr.length()).map { i ->
            val o = arr.getJSONObject(i)
            val category = o.optString("category")
            VenueApiItem(
                id = o.optString("id"),
                name = o.optString("name"),
                location = o.optString("location"),
                rating = o.optString("rating"),
                category = category,
                // Backend sends the full list (category-first); fall back to [category] for
                // older servers that don't emit `sports` yet, so the card always has one icon.
                sports = o.optJSONArray("sports").toStringList().ifEmpty { listOfNotNull(category.takeIf { it.isNotBlank() }) },
                price = o.optInt("price", 0),
                image = o.optString("image").takeIf { it.isNotBlank() && it != "null" },
                images = o.optJSONArray("images").toStringList()
                    .filter { it.isNotBlank() && it != "null" }.distinct(),
                tagline = o.optString("tagline"),
                distance = o.optString("distance"),
                isBookable = o.optBoolean("is_bookable", false),
                latitude = if (o.isNull("latitude")) null else o.optDouble("latitude").takeIf { !it.isNaN() },
                longitude = if (o.isNull("longitude")) null else o.optDouble("longitude").takeIf { !it.isNaN() },
            )
        }
    }

    /** Slots for a venue, used by the venue-booking flow. Empty on failure. */
    suspend fun getVenueSlots(venueId: String): List<VenueSlotItem> = withContext(Dispatchers.IO) {
        val body = URL("${ApiConfig.BASE_URL}/api/venues/$venueId").readText()
        val data = JSONObject(body).optJSONObject("data") ?: return@withContext emptyList()
        val arr = data.optJSONArray("slots") ?: return@withContext emptyList()
        (0 until arr.length()).map { i ->
            val o = arr.getJSONObject(i)
            VenueSlotItem(
                id = o.optInt("id"),
                day = o.optString("day"),
                time = o.optString("time"),
                available = o.optBoolean("available", true),
                fillingFast = o.optBoolean("filling_fast", false),
                price = o.optInt("price", 0),
                capacity = o.optInt("capacity", 1),
                sports = o.optJSONArray("sports").toStringList(),
            )
        }
    }

    /**
     * Real per-slot availability for [date], keyed by slot id. Null when the request or parse
     * fails, so callers can tell "unknown" apart from "known and empty" and fall back to the
     * template flags instead of painting every slot booked.
     */
    suspend fun getSlotAvailability(venueId: String, date: java.time.LocalDate): Map<Int, SlotAvailability>? =
        withContext(Dispatchers.IO) {
            runCatching {
                val body = ConditionalHttp.getText("${ApiConfig.BASE_URL}/api/venues/$venueId/availability?date=$date")
                    ?: return@runCatching null
                val arr = JSONObject(body).optJSONObject("data")?.optJSONArray("slots")
                    ?: return@runCatching null
                (0 until arr.length()).associate { i ->
                    val o = arr.getJSONObject(i)
                    val id = o.optInt("id")
                    id to SlotAvailability(
                        slotId = id,
                        state = when (o.optString("state")) {
                            "booked" -> SlotAvailability.State.BOOKED
                            "closed" -> SlotAvailability.State.CLOSED
                            else -> SlotAvailability.State.OPEN
                        },
                        courtsFree = o.optInt("courts_free", 0),
                        courtsTotal = o.optInt("courts_total", 1),
                    )
                }
            }.getOrNull()
        }

    /** Full venue detail (GET /api/venues/{id}); null on network/parse failure. */
    suspend fun getVenueDetail(id: String): VenueDetailData? = withContext(Dispatchers.IO) {
        runCatching {
            val body = ConditionalHttp.getText("${ApiConfig.BASE_URL}/api/venues/$id")
                ?: return@runCatching null
            val d = JSONObject(body).optJSONObject("data") ?: return@runCatching null
            parseDetail(d)
        }.getOrNull()
    }

    private fun parseDetail(d: JSONObject): VenueDetailData {
        val images = d.optJSONArray("images").toStringList()
        // org.json's optString returns the literal "null" for a JSON null value, which then
        // renders as "null" in the UI. Read display strings through this instead.
        fun s(key: String): String = if (d.isNull(key)) "" else d.optString(key).let { if (it == "null") "" else it }
        return VenueDetailData(
            id = s("id"),
            name = s("name"),
            category = s("category"),
            location = s("location"),
            address = s("address"),
            distance = s("distance"),
            rating = s("rating"),
            price = d.optInt("price", 0),
            ratingsCount = d.optInt("ratings_count", 0),
            reviewsCount = d.optInt("reviews_count", 0),
            isBookable = d.optBoolean("is_bookable", false),
            isFeatured = d.optBoolean("is_featured", false),
            images = images,
            amenities = d.optJSONArray("amenities").toStringList(),
            courts = (d.optJSONArray("courts") ?: JSONArray()).mapObjects { c ->
                VenueCourt(
                    id = c.optInt("id"),
                    name = c.optString("name"),
                    sports = c.optJSONArray("sports").toStringList(),
                    price = c.optInt("price", 0),
                    peakPrice = if (c.isNull("peak_price")) null else c.optInt("peak_price").takeIf { it > 0 },
                    peakDays = c.optJSONArray("peak_days").toStringList(),
                    peakStart = c.optString("peak_start").takeIf { it.isNotBlank() && it != "null" },
                    peakEnd = c.optString("peak_end").takeIf { it.isNotBlank() && it != "null" },
                )
            },
            sports = d.optJSONArray("sports").toStringList().ifEmpty { listOfNotNull(d.optString("category").takeIf { it.isNotBlank() }) },
            about = s("about"),
            hours = s("hours"),
            rules = d.optJSONArray("rules").toStringList(),
            cancellation = s("cancellation"),
            priceNote = s("price_note"),
            latitude = if (d.isNull("latitude")) null else d.optDouble("latitude").takeIf { !it.isNaN() },
            longitude = if (d.isNull("longitude")) null else d.optDouble("longitude").takeIf { !it.isNaN() },
            mapLink = d.optString("map_link").takeIf { it.isNotBlank() && it != "null" } ?: "",
            slots = (d.optJSONArray("slots") ?: JSONArray()).mapObjects { s ->
                VenueSlotItem(
                    id = s.optInt("id"),
                    day = s.optString("day", "Today"),
                    time = s.optString("time"),
                    available = s.optBoolean("available", true),
                    fillingFast = s.optBoolean("filling_fast", false),
                    price = s.optInt("price", 0),
                    capacity = s.optInt("capacity", 1),
                )
            },
            reviews = (d.optJSONArray("reviews") ?: JSONArray()).mapObjects { r ->
                VenueReviewItem(
                    name = r.optString("name"),
                    rating = r.optInt("rating", 0),
                    text = r.optString("text"),
                    ago = r.optString("ago"),
                )
            },
            priceChart = (d.optJSONArray("price_chart") ?: JSONArray()).mapObjects { v ->
                VenuePriceVariant(
                    label = v.optString("label"),
                    groups = (v.optJSONArray("groups") ?: JSONArray()).mapObjects { g ->
                        VenuePriceGroup(
                            days = g.optString("days"),
                            rows = (g.optJSONArray("rows") ?: JSONArray()).mapObjects { b ->
                                VenuePriceBand(time = b.optString("time"), price = b.optInt("price", 0))
                            },
                        )
                    },
                )
            },
            // Absent on older servers, which is the "none" case anyway.
            convenienceFeeType = s("convenience_fee_type").ifBlank { "none" },
            convenienceFeeValue = d.optDouble("convenience_fee_value", 0.0).let { if (it.isNaN()) 0.0 else it },
        )
    }

    /** Submit a 1–5 star review (POST /api/venues/{id}/reviews). */
    suspend fun submitReview(token: String, venueId: String, stars: Int, note: String): ReviewResult =
        withContext(Dispatchers.IO) {
            try {
                val payload = JSONObject().apply {
                    put("rating", stars)
                    if (note.isNotBlank()) put("text", note)
                }
                val conn = (URL("${ApiConfig.BASE_URL}/api/venues/$venueId/reviews").openConnection() as HttpURLConnection).apply {
                    requestMethod = "POST"
                    doOutput = true
                    connectTimeout = 15000
                    readTimeout = 15000
                    setRequestProperty("Content-Type", "application/json")
                    setRequestProperty("Accept", "application/json")
                    setRequestProperty("Authorization", "Bearer $token")
                }
                conn.outputStream.use { it.write(payload.toString().toByteArray(Charsets.UTF_8)) }
                val code = conn.responseCode
                val stream = if (code >= 400) conn.errorStream else conn.inputStream
                val respBody = stream?.let { BufferedReader(InputStreamReader(it)).use(BufferedReader::readText) } ?: ""
                conn.disconnect()
                if (code in 200..299) {
                    ReviewResult.Success
                } else {
                    val msg = runCatching { JSONObject(respBody).optString("message") }.getOrNull()
                    ReviewResult.Error(msg?.takeIf { it.isNotBlank() } ?: "Couldn't submit rating (code $code).")
                }
            } catch (e: Exception) {
                ReviewResult.Error(e.message ?: "Failed to connect. Please check your network.")
            }
        }
}

private fun JSONArray?.toStringList(): List<String> =
    if (this == null) emptyList() else (0 until length()).mapNotNull { optString(it).takeIf { s -> s.isNotBlank() } }

private inline fun <T> JSONArray.mapObjects(transform: (JSONObject) -> T): List<T> =
    (0 until length()).mapNotNull { optJSONObject(it)?.let(transform) }
