package com.haraan.app.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL

/**
 * A venue Haraan lists whose owner hasn't joined (GET /api/venues/listed). Shown on Pulse as a
 * place to play — never bookable. Kept apart from [VenueApiItem] so nothing that books can
 * ever be handed one.
 */
data class ListedVenueItem(
    val id: String,
    val name: String,
    val area: String,
    val city: String,
    val sports: List<String>,
    val latitude: Double?,
    val longitude: Double?,
    /** The venue's Google listing photo via our proxy; may 404 when it has none. */
    val image: String?,
    /** "Open now · closes 9 PM" / "Closed now · opens 6 AM", or null when hours are unknown. */
    val openState: String?,
    val openNow: Boolean,
    /** Its page on haraan.app. */
    val url: String,
)

data class ListedVenuePhoto(val url: String, val credit: String)

/** GET /api/venues/listed/{id}: the card plus what a player needs before going there. */
data class ListedVenueDetail(
    val item: ListedVenueItem,
    val address: String,
    val phone: String,
    val mapLink: String,
    /** One line per run of days, e.g. "Mon–Sat 6:00 AM–11:00 PM". */
    val hours: List<String>,
    val about: String,
    val photos: List<ListedVenuePhoto>,
    val requestsOpen: Boolean,
    val preferredTimes: List<String>,
)

sealed interface AskToBookResult {
    data object Sent : AskToBookResult
    data class Error(val message: String) : AskToBookResult
}

class ListedVenueRepository {

    /** Every listed venue. Empty on failure or on a server without the endpoint. */
    suspend fun getListed(): List<ListedVenueItem> = withContext(Dispatchers.IO) {
        val body = ConditionalHttp.getText("${ApiConfig.BASE_URL}/api/venues/listed")
            ?: return@withContext emptyList()
        val arr = runCatching { JSONObject(body).optJSONArray("data") }.getOrNull() ?: JSONArray()
        (0 until arr.length()).mapNotNull { i -> runCatching { parseItem(arr.getJSONObject(i)) }.getOrNull() }
    }

    suspend fun getDetail(id: String): ListedVenueDetail? = withContext(Dispatchers.IO) {
        runCatching {
            val body = URL("${ApiConfig.BASE_URL}/api/venues/listed/$id").readText()
            val d = JSONObject(body).getJSONObject("data")
            val photos = d.optJSONArray("photos") ?: JSONArray()
            ListedVenueDetail(
                item = parseItem(d),
                address = d.str("address"),
                phone = d.str("phone"),
                mapLink = d.str("map_link"),
                hours = d.optJSONArray("hours").strings(),
                about = d.str("about"),
                photos = (0 until photos.length()).mapNotNull { i ->
                    val p = photos.optJSONObject(i) ?: return@mapNotNull null
                    p.str("url").takeIf { it.isNotBlank() }?.let { ListedVenuePhoto(it, p.str("credit")) }
                },
                requestsOpen = d.optBoolean("requests_open", false),
                preferredTimes = d.optJSONArray("preferred_times").strings(),
            )
        }.getOrNull()
    }

    /** "Ask to book" (POST /api/venues/listed/{id}/interest). The token is optional. */
    suspend fun askToBook(
        id: String,
        name: String,
        phone: String,
        sport: String?,
        preferredTime: String?,
        token: String?,
    ): AskToBookResult = withContext(Dispatchers.IO) {
        try {
            val payload = JSONObject().apply {
                put("name", name.trim())
                put("phone", phone.trim())
                if (!sport.isNullOrBlank()) put("sport", sport)
                if (!preferredTime.isNullOrBlank()) put("preferred_time", preferredTime)
            }
            val conn = (URL("${ApiConfig.BASE_URL}/api/venues/listed/$id/interest").openConnection() as HttpURLConnection).apply {
                requestMethod = "POST"
                doOutput = true
                connectTimeout = 15000
                readTimeout = 15000
                setRequestProperty("Content-Type", "application/json")
                setRequestProperty("Accept", "application/json")
                if (!token.isNullOrBlank()) setRequestProperty("Authorization", "Bearer $token")
            }
            conn.outputStream.use { it.write(payload.toString().toByteArray(Charsets.UTF_8)) }
            val code = conn.responseCode
            val stream = if (code >= 400) conn.errorStream else conn.inputStream
            val resp = stream?.let { BufferedReader(InputStreamReader(it)).use(BufferedReader::readText) }.orEmpty()
            conn.disconnect()
            when {
                code in 200..299 -> AskToBookResult.Sent
                code == 429 -> AskToBookResult.Error("Too many tries. Wait a minute and try again.")
                else -> {
                    val msg = runCatching { JSONObject(resp).optString("message") }.getOrNull()
                    AskToBookResult.Error(msg?.takeIf { it.isNotBlank() } ?: "That didn’t go through. Try again.")
                }
            }
        } catch (e: Exception) {
            AskToBookResult.Error("No connection. Check your internet and try again.")
        }
    }

    private fun parseItem(o: JSONObject) = ListedVenueItem(
        id = o.str("id"),
        name = o.str("name"),
        area = o.str("area"),
        city = o.str("city"),
        sports = o.optJSONArray("sports").strings(),
        latitude = if (o.isNull("latitude")) null else o.optDouble("latitude").takeIf { !it.isNaN() },
        longitude = if (o.isNull("longitude")) null else o.optDouble("longitude").takeIf { !it.isNaN() },
        image = o.str("image").takeIf { it.isNotBlank() },
        openState = o.str("open_state").takeIf { it.isNotBlank() },
        openNow = o.optBoolean("open_now", false),
        url = o.str("url"),
    )
}

private fun JSONObject.str(key: String): String =
    if (isNull(key)) "" else optString(key).let { if (it == "null") "" else it }

private fun JSONArray?.strings(): List<String> =
    if (this == null) emptyList() else (0 until length()).map { optString(it) }.filter { it.isNotBlank() && it != "null" }
