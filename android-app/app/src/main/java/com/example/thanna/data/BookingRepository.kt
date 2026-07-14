package com.example.thanna.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL

sealed interface BookingResult {
  data class Success(
    val bookingId: Int,
    val quantity: Int,
    val totalAmount: String,
    val status: String,
    val message: String,
    // Scannable entry-pass code — used to render the QR `haraan:ticket:<code>`.
    val ticketCode: String? = null,
    // Number of passes issued (one per tier line); >1 for a mixed cart.
    val bookingCount: Int = 1
  ) : BookingResult

  data class Error(val message: String) : BookingResult
}

class BookingRepository(
  private val baseUrl: String = ApiConfig.BASE_URL,
) {
  suspend fun createBooking(
    token: String,
    eventId: Int,
    quantity: Int,
    ticketTypeId: Int? = null,
    couponCode: String? = null
  ): BookingResult = withContext(Dispatchers.IO) {
    try {
      val jsonBody = JSONObject().apply {
        put("eventId", eventId)
        put("quantity", quantity)
        if (ticketTypeId != null) {
          put("ticketTypeId", ticketTypeId)
        }
        if (!couponCode.isNullOrBlank()) {
          put("couponCode", couponCode)
        }
      }

      val connection = (URL(baseUrl.trimEnd('/') + "/api/bookings").openConnection() as HttpURLConnection).apply {
        requestMethod = "POST"
        doOutput = true
        connectTimeout = 15000
        readTimeout = 15000
        setRequestProperty("Content-Type", "application/json")
        setRequestProperty("Accept", "application/json")
        setRequestProperty("Authorization", "Bearer $token")
      }

      connection.outputStream.use { outputStream ->
        outputStream.write(jsonBody.toString().toByteArray(Charsets.UTF_8))
      }

      val code = connection.responseCode
      val body = readBody(connection)
      connection.disconnect()

      if (code in 200..299) {
        val json = JSONObject(body)
        val message = json.optString("message", "Booking created successfully.")
        val data = json.getJSONObject("data")
        BookingResult.Success(
          bookingId = data.getInt("id"),
          quantity = data.getInt("quantity"),
          totalAmount = data.optString("totalAmount", "0.00"),
          status = data.optString("status", "CONFIRMED"),
          message = message,
          ticketCode = data.optString("ticketCode").takeIf { it.isNotBlank() }
        )
      } else {
        val errorMessage = parseErrorMessage(body, "Booking failed (Status code: $code)")
        BookingResult.Error(errorMessage)
      }
    } catch (e: Exception) {
      BookingResult.Error(e.message ?: "Failed to connect to server. Please check your network connection.")
    }
  }

  /**
   * Place a multi-tier cart order (POST /api/bookings with `items[]`). Each line is
   * a (ticketTypeId, quantity) pair; a null id books the flat event price. The server
   * prices every line from its tier's live phase price and returns an aggregate plus
   * one pass per line.
   */
  suspend fun createOrder(
    token: String,
    eventId: Int,
    items: List<Pair<Int?, Int>>,
    couponCode: String? = null,
  ): BookingResult = withContext(Dispatchers.IO) {
    try {
      val jsonBody = JSONObject().apply {
        put("eventId", eventId)
        val arr = JSONArray()
        items.filter { it.second > 0 }.forEach { (tierId, qty) ->
          // A flat-price line (null tier) falls back to the legacy quantity field.
          if (tierId != null) {
            arr.put(JSONObject().apply {
              put("ticketTypeId", tierId)
              put("quantity", qty)
            })
          }
        }
        if (arr.length() > 0) {
          put("items", arr)
        } else {
          // No tiers selected → flat-price event: send a plain quantity.
          put("quantity", items.sumOf { it.second }.coerceAtLeast(1))
        }
        if (!couponCode.isNullOrBlank()) put("couponCode", couponCode)
      }

      val connection = (URL(baseUrl.trimEnd('/') + "/api/bookings").openConnection() as HttpURLConnection).apply {
        requestMethod = "POST"
        doOutput = true
        connectTimeout = 15000
        readTimeout = 15000
        setRequestProperty("Content-Type", "application/json")
        setRequestProperty("Accept", "application/json")
        setRequestProperty("Authorization", "Bearer $token")
      }
      connection.outputStream.use { it.write(jsonBody.toString().toByteArray(Charsets.UTF_8)) }

      val code = connection.responseCode
      val body = readBody(connection)
      connection.disconnect()

      if (code in 200..299) {
        val json = JSONObject(body)
        val data = json.getJSONObject("data")
        BookingResult.Success(
          bookingId = data.optInt("id"),
          quantity = data.optInt("quantity"),
          totalAmount = data.optString("totalAmount", "0.00"),
          status = data.optString("status", "CONFIRMED"),
          message = json.optString("message", "Booking confirmed."),
          ticketCode = data.optString("ticketCode").takeIf { it.isNotBlank() },
          bookingCount = data.optJSONArray("bookings")?.length() ?: 1,
        )
      } else {
        BookingResult.Error(parseErrorMessage(body, "Booking failed (Status code: $code)"))
      }
    } catch (e: Exception) {
      BookingResult.Error(e.message ?: "Failed to connect to server. Please check your network connection.")
    }
  }

  /** Result of a coupon-code check (POST /api/bookings/validate-coupon). */
  data class CouponResult(
    val valid: Boolean,
    val code: String?,
    val discount: Double,
    val message: String,
  )

  /**
   * Validate a coupon for the checkout preview. The discount is a flat ₹ amount off.
   * `eventId` scopes the check so an event-specific coupon is rejected on other events.
   */
  suspend fun validateCoupon(token: String, code: String, eventId: Int? = null): CouponResult = withContext(Dispatchers.IO) {
    try {
      val body = JSONObject().apply {
        put("code", code)
        if (eventId != null && eventId > 0) put("eventId", eventId)
      }
      val connection = (URL(baseUrl.trimEnd('/') + "/api/bookings/validate-coupon").openConnection() as HttpURLConnection).apply {
        requestMethod = "POST"
        doOutput = true
        connectTimeout = 15000
        readTimeout = 15000
        setRequestProperty("Content-Type", "application/json")
        setRequestProperty("Accept", "application/json")
        setRequestProperty("Authorization", "Bearer $token")
      }
      connection.outputStream.use { it.write(body.toString().toByteArray(Charsets.UTF_8)) }
      val json = JSONObject(readBody(connection))
      connection.disconnect()
      CouponResult(
        valid = json.optBoolean("valid", false),
        code = json.optString("code").takeIf { it.isNotBlank() },
        discount = json.optDouble("discount", 0.0),
        message = json.optString("message", "This code isn’t valid."),
      )
    } catch (e: Exception) {
      CouponResult(false, null, 0.0, e.message ?: "Couldn’t check that code.")
    }
  }

  /**
   * Reserve a court for a time window on a date (POST /api/bookings/venue).
   * [courtId] locks the physical court across every sport it hosts; [duration] is in hours
   * and drives both the reserved window and the price. The backend is authoritative on both.
   */
  suspend fun bookVenueSlot(
    token: String,
    venueId: Int,
    slotId: Int?,
    date: String,
    courtId: Int? = null,
    duration: Int = 1,
  ): BookingResult = withContext(Dispatchers.IO) {
    try {
      val jsonBody = JSONObject().apply {
        put("venueId", venueId)
        put("date", date)
        if (slotId != null) put("slotId", slotId)
        if (courtId != null) put("courtId", courtId)
        put("duration", duration)
      }

      val connection = (URL(baseUrl.trimEnd('/') + "/api/bookings/venue").openConnection() as HttpURLConnection).apply {
        requestMethod = "POST"
        doOutput = true
        connectTimeout = 15000
        readTimeout = 15000
        setRequestProperty("Content-Type", "application/json")
        setRequestProperty("Accept", "application/json")
        setRequestProperty("Authorization", "Bearer $token")
      }

      connection.outputStream.use { it.write(jsonBody.toString().toByteArray(Charsets.UTF_8)) }

      val code = connection.responseCode
      val body = readBody(connection)
      connection.disconnect()

      if (code in 200..299) {
        val data = JSONObject(body).getJSONObject("data")
        BookingResult.Success(
          bookingId = data.optInt("id", 0),
          quantity = data.optInt("quantity", 1),
          totalAmount = data.optString("totalAmount", "0"),
          status = data.optString("status", "CONFIRMED"),
          message = JSONObject(body).optString("message", "Venue booked."),
        )
      } else {
        BookingResult.Error(parseErrorMessage(body, "Booking failed (Status code: $code)"))
      }
    } catch (e: Exception) {
      BookingResult.Error(e.message ?: "Failed to connect. Please check your network.")
    }
  }

  private fun readBody(connection: HttpURLConnection): String {
    val stream = if (connection.responseCode >= 400) connection.errorStream else connection.inputStream
    if (stream == null) {
      return ""
    }
    return BufferedReader(InputStreamReader(stream)).use { reader ->
      reader.readText()
    }
  }

  private fun parseErrorMessage(body: String, fallback: String): String {
    return try {
      if (body.isBlank()) {
        fallback
      } else {
        val json = JSONObject(body)
        if (json.has("errors")) {
          // Validation error dictionary (e.g. couponCode validation)
          val errors = json.getJSONObject("errors")
          val firstKey = errors.keys().next()
          val firstErrorArr = errors.getJSONArray(firstKey)
          if (firstErrorArr.length() > 0) {
            firstErrorArr.getString(0)
          } else {
            json.optString("message", fallback)
          }
        } else {
          json.optString("error", json.optString("message", fallback))
        }
      }
    } catch (_: Exception) {
      fallback
    }
  }
}
