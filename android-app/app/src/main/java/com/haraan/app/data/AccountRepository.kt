package com.haraan.app.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL

data class AccountInfo(
  val name: String,
  val email: String?,
  val phone: String?,
  val avatar: String?,
  val playerId: String?,
  val district: String?,
  val state: String?,
  val memberSince: String?,
)

data class BookingLite(
  val id: Long,
  val status: String,
  val quantity: Int,
  val totalAmount: Double,
  val type: String = "event",   // "event" | "venue"
  val eventTitle: String,        // generic title (event title or venue name)
  val eventVenue: String?,       // generic subtitle (event venue or venue location)
  val eventDate: String?,        // generic date (event date or slot date)
  val slotLabel: String? = null, // venue bookings only: "Today · 06:00 AM"
  val ticketCode: String? = null,// scannable entry-pass code → QR `haraan:ticket:<code>`
  val imageUrl: String? = null,  // event poster / venue image for the schedule + pass
  val tierName: String? = null,  // ticket tier bought ("Gold"); null for venue slots
  val mapLink: String? = null,   // admin-set directions URL; null hides the pass's Directions button
)

/**
 * The common account profile — identity (from /auth/me) + event bookings
 * (from /bookings). Shared by both Events and GameHub. The cricket player
 * profile lives separately in [ProfileRepository].
 */
class AccountRepository(
  private val baseUrl: String = ApiConfig.BASE_URL,
) {
  suspend fun fetchAccount(token: String): AccountInfo = withContext(Dispatchers.IO) {
    val body = get("/api/auth/me", token)
    val user = JSONObject(body).getJSONObject("user")
    AccountInfo(
      name = user.optString("name", ""),
      email = user.optString("email", null).clean(),
      phone = user.optString("phone", null).clean(),
      avatar = user.optString("avatar", null).clean(),
      playerId = user.optString("playerId", null).clean(),
      district = user.optString("district", null).clean(),
      state = user.optString("state", null).clean(),
      memberSince = user.optString("createdAt", null).clean(),
    )
  }

  suspend fun fetchBookings(token: String): List<BookingLite> = withContext(Dispatchers.IO) {
    val body = get("/api/bookings", token)
    val arr = JSONObject(body).optJSONArray("data") ?: return@withContext emptyList()
    buildList {
      for (i in 0 until arr.length()) {
        val o = arr.getJSONObject(i)
        val type = o.optString("type", "event")
        val event = o.optJSONObject("event")
        val venue = o.optJSONObject("venue")
        val isVenue = type == "venue"
        add(
          BookingLite(
            id = o.optLong("id", 0L),
            status = o.optString("status", ""),
            quantity = o.optInt("quantity", 1),
            totalAmount = o.optDouble("totalAmount", 0.0),
            type = type,
            eventTitle = if (isVenue) (venue?.optString("name", "Venue") ?: "Venue") else (event?.optString("title", "Event") ?: "Event #${o.optInt("eventId", 0)}"),
            eventVenue = if (isVenue) venue?.optString("location", null).clean() else event?.optString("venue", null).clean(),
            eventDate = if (isVenue) o.optString("slotDate", null).clean() else event?.optString("date", null).clean(),
            slotLabel = o.optString("slotLabel", null).clean(),
            ticketCode = o.optString("ticketCode", null).clean(),
            imageUrl = if (isVenue) venue?.optString("image", null).clean() else event?.optString("image", null).clean(),
            tierName = o.optString("ticketTypeName", null).clean(),
            mapLink = if (isVenue) venue?.optString("mapLink", null).clean() else event?.optString("mapLink", null).clean(),
          )
        )
      }
    }
  }

  private fun get(path: String, token: String): String {
    val connection = (URL("${baseUrl.trimEnd('/')}$path").openConnection() as HttpURLConnection).apply {
      requestMethod = "GET"
      connectTimeout = 15000
      readTimeout = 15000
      setRequestProperty("Accept", "application/json")
      setRequestProperty("Authorization", "Bearer $token")
    }
    try {
      val code = connection.responseCode
      val stream = if (code >= 400) connection.errorStream else connection.inputStream
      val text = stream?.let { BufferedReader(InputStreamReader(it)).use { r -> r.readText() } } ?: ""
      if (code !in 200..299) {
        throw IllegalStateException(parseError(text))
      }
      return text
    } finally {
      connection.disconnect()
    }
  }

  private fun parseError(body: String): String = try {
    if (body.isBlank()) "Unable to load account." else JSONObject(body).optString("error", "Unable to load account.")
  } catch (_: Exception) {
    "Unable to load account."
  }

  private fun String?.clean(): String? = this?.takeIf { it.isNotBlank() && it != "null" }
}
