package com.example.thanna.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL

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
  private val baseUrl: String = "http://10.0.2.2:8000",
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
      connectTimeout = 15000
      readTimeout = 15000
      setRequestProperty("Content-Type", "application/json")
      setRequestProperty("Accept", "application/json")
    }

    connection.outputStream.use { outputStream ->
      outputStream.write(jsonBody.toString().toByteArray(Charsets.UTF_8))
    }

    val code = connection.responseCode
    val body = readBody(connection)
    connection.disconnect()
    return HttpResult(code = code, body = body)
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