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
    val message: String
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
          message = message
        )
      } else {
        val errorMessage = parseErrorMessage(body, "Booking failed (Status code: $code)")
        BookingResult.Error(errorMessage)
      }
    } catch (e: Exception) {
      BookingResult.Error(e.message ?: "Failed to connect to server. Please check your network connection.")
    }
  }

  /** Reserve a venue slot for a date (POST /api/bookings/venue). */
  suspend fun bookVenueSlot(
    token: String,
    venueId: Int,
    slotId: Int?,
    date: String,
  ): BookingResult = withContext(Dispatchers.IO) {
    try {
      val jsonBody = JSONObject().apply {
        put("venueId", venueId)
        put("date", date)
        if (slotId != null) put("slotId", slotId)
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
