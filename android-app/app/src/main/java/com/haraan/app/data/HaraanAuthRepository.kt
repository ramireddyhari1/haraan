package com.haraan.app.data

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

/**
 * Result of verifying an email code. Existing users come back logged in ([newUser] = false,
 * [token] set). A brand-new email comes back verified-but-not-registered ([newUser] = true,
 * [token] null) — the caller then collects name + date of birth and calls [completeProfile].
 */
data class VerifyEmailResult(
  val newUser: Boolean,
  val token: String?,
  val userName: String?,
  val verificationToken: String?,
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

  // ── Email OTP: currently UNUSED by the UI ──────────────────────────────────
  // The login screen offers Google + WhatsApp OTP only (2026-07-17). These are kept,
  // not deleted, on purpose: a handful of accounts exist with a real email and no
  // phone, and WhatsApp OTP keys on the phone — so if email login has to come back
  // for them, it's a screen change, not a rewrite. The `/api/auth/email/` endpoints
  // are still live. Delete both sides together if email login is retired for good.

  /**
   * Step 1 of email login: request a code for [email] only. Whether the account is new is
   * decided by the backend and reported back on verify — not here.
   */
  suspend fun requestEmailOtp(email: String): RequestOtpResult = withContext(Dispatchers.IO) {
    val response = postJson(path = "/api/auth/email/request", jsonBody = JSONObject().put("email", email))

    if (response.code !in 200..299) {
      throw IllegalStateException(parseErrorMessage(response.body, "Unable to send the code."))
    }

    val json = JSONObject(response.body)
    RequestOtpResult(
      verificationToken = json.getString("verificationToken"),
      phone = json.optString("email", email),
      message = json.optString("message", "A login code has been sent to your email."),
      expiresInSeconds = json.optInt("expiresIn", 300),
    )
  }

  /** Step 2: verify the code. Existing users get a token; new users get sent to profile setup. */
  suspend fun verifyEmailOtp(verificationToken: String, otp: String): VerifyEmailResult = withContext(Dispatchers.IO) {
    val response = postJson(
      path = "/api/auth/email/verify",
      jsonBody = JSONObject()
        .put("verification_token", verificationToken)
        .put("otp", otp),
    )

    if (response.code !in 200..299) {
      throw IllegalStateException(parseErrorMessage(response.body, "Invalid code. Please try again."))
    }

    val json = JSONObject(response.body)
    val newUser = json.optBoolean("newUser", false)
    val user = json.optJSONObject("user")
    VerifyEmailResult(
      newUser = newUser,
      token = json.optString("token").takeIf { it.isNotBlank() },
      userName = user?.optString("name"),
      verificationToken = json.optString("verificationToken").takeIf { it.isNotBlank() } ?: verificationToken,
      message = json.optString("message", if (newUser) "Email verified." else "Login successful."),
    )
  }

  /** Step 3 (new users only): submit name + date of birth (yyyy-MM-dd) to create the account. */
  suspend fun completeEmailProfile(verificationToken: String, name: String, dateOfBirth: String): VerifyOtpResult =
    withContext(Dispatchers.IO) {
      val response = postJson(
        path = "/api/auth/email/complete",
        jsonBody = JSONObject()
          .put("verification_token", verificationToken)
          .put("name", name)
          .put("date_of_birth", dateOfBirth),
      )

      if (response.code !in 200..299) {
        throw IllegalStateException(parseErrorMessage(response.body, "Couldn't finish sign-up. Please try again."))
      }

      val json = JSONObject(response.body)
      val user = json.getJSONObject("user")
      VerifyOtpResult(
        token = json.getString("token"),
        userName = user.optString("name", "Haraan user"),
        message = json.optString("message", "Welcome to Haraan!"),
      )
    }

  /**
   * Email + password sign-in — the same credential the website accepts, and the
   * app's primary email path (it replaced WhatsApp OTP on the login screen).
   * Mirrors the web: an unknown email creates the account, a known one is verified,
   * so there is no separate "sign up" call. [name] is only used when creating.
   */
  suspend fun passwordLogin(email: String, password: String, name: String? = null): VerifyOtpResult =
    withContext(Dispatchers.IO) {
      val body = JSONObject()
        .put("email", email.trim())
        .put("password", password)
      if (!name.isNullOrBlank()) body.put("name", name.trim())

      val response = postJson(path = "/api/auth/password", jsonBody = body)

      if (response.code !in 200..299) {
        throw IllegalStateException(parseErrorMessage(response.body, "Couldn't sign you in. Please try again."))
      }

      val json = JSONObject(response.body)
      val user = json.getJSONObject("user")
      VerifyOtpResult(
        token = json.getString("token"),
        userName = user.optString("name", "Haraan user"),
        message = json.optString("message", "Login successful."),
      )
    }

  /**
   * "Continue with Google": exchange a Google ID token (from Credential Manager) for an app
   * JWT. The backend verifies the token with Google and creates the account on first sign-in,
   * so there's no separate profile step — the name comes from the Google account.
   */
  suspend fun googleSignIn(idToken: String): VerifyOtpResult = withContext(Dispatchers.IO) {
    val response = postJson(path = "/api/auth/google", jsonBody = JSONObject().put("id_token", idToken))

    if (response.code !in 200..299) {
      throw IllegalStateException(parseErrorMessage(response.body, "Google sign-in failed. Please try again."))
    }

    val json = JSONObject(response.body)
    val user = json.getJSONObject("user")
    VerifyOtpResult(
      token = json.getString("token"),
      userName = user.optString("name", "Haraan user"),
      message = json.optString("message", "Welcome to Haraan!"),
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
      if (body.isBlank()) return fallback
      val json = JSONObject(body)
      // Our own handlers use {"error": …}; Laravel's validator (422) uses
      // {"message": …, "errors": {field: [...]}} — prefer the specific field
      // message so "Your password must be at least 6 characters." reaches the user.
      json.optString("error").takeIf { it.isNotBlank() }
        ?: json.optJSONObject("errors")?.let { errors ->
          errors.keys().asSequence()
            .mapNotNull { errors.optJSONArray(it)?.optString(0) }
            .firstOrNull { it.isNotBlank() }
        }
        ?: json.optString("message").takeIf { it.isNotBlank() }
        ?: fallback
    } catch (_: Exception) {
      fallback
    }
  }

  private data class HttpResult(
    val code: Int,
    val body: String,
  )
}