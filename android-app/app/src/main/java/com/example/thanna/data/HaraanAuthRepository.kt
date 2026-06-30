package com.example.thanna.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.ConnectException
import java.net.HttpURLConnection
import java.net.SocketTimeoutException
import java.net.URL
import java.net.UnknownHostException

data class RequestOtpResult(
  val verificationToken: String,
  val phone: String,
  val message: String,
  val expiresInSeconds: Int,
)

data class VerifyOtpResult(
  val token: String,
  val userName: String,
  val message: String,
)

class HaraanAuthRepository(
  // Single source of truth — reaches the local server via `adb reverse` on both
  // emulator and physical device. Hardcoding 10.0.2.2 broke login on real phones.
  private val baseUrl: String = ApiConfig.BASE_URL,
) {
  suspend fun requestOtp(phone: String): RequestOtpResult = withContext(Dispatchers.IO) {
    val response = postJson(
      path = "/api/auth/whatsapp/request",
      jsonBody = JSONObject().put("phone", phone),
    )

    if (response.code !in 200..299) {
      throw IllegalStateException(parseErrorMessage(response.body, "Unable to request OTP."))
    }

    val json = JSONObject(response.body)
    RequestOtpResult(
      verificationToken = json.getString("verificationToken"),
      phone = json.optString("phone", phone),
      message = json.optString("message", "OTP sent to WhatsApp."),
      expiresInSeconds = json.optInt("expiresIn", 300),
    )
  }

  suspend fun verifyOtp(verificationToken: String, otp: String): VerifyOtpResult = withContext(Dispatchers.IO) {
    val response = postJson(
      path = "/api/auth/whatsapp/verify",
      jsonBody = JSONObject()
        .put("verification_token", verificationToken)
        .put("otp", otp),
    )

    if (response.code !in 200..299) {
      throw IllegalStateException(parseErrorMessage(response.body, "Invalid OTP. Please try again."))
    }

    val json = JSONObject(response.body)
    val user = json.getJSONObject("user")
    VerifyOtpResult(
      token = json.getString("token"),
      userName = user.optString("name", "Haraan user"),
      message = json.optString("message", "Login successful via WhatsApp."),
    )
  }

  private fun postJson(path: String, jsonBody: JSONObject): HttpResult {
    val connection = (URL(baseUrl.trimEnd('/') + path).openConnection() as HttpURLConnection).apply {
      requestMethod = "POST"
      doOutput = true
      // Generous: the local Windows dev server has no opcache and bootstraps slowly
      // per request (~3-10s under load). Production responds in well under a second.
      connectTimeout = 30000
      readTimeout = 30000
      setRequestProperty("Content-Type", "application/json")
      setRequestProperty("Accept", "application/json")
    }

    try {
      connection.outputStream.use { outputStream ->
        outputStream.write(jsonBody.toString().toByteArray(Charsets.UTF_8))
      }

      val code = connection.responseCode
      val body = readBody(connection)
      return HttpResult(code = code, body = body)
    } catch (e: SocketTimeoutException) {
      throw IllegalStateException("The server is taking too long to respond. Please try again.")
    } catch (e: ConnectException) {
      throw IllegalStateException("Can't reach the server. Please check your connection.")
    } catch (e: UnknownHostException) {
      throw IllegalStateException("No internet connection. Please check your network.")
    } finally {
      connection.disconnect()
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
        JSONObject(body).optString("error", fallback)
      }
    } catch (_: Exception) {
      fallback
    }
  }

  private data class HttpResult(
    val code: Int,
    val body: String,
  )
}