package com.haraan.app.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL
import java.net.URLEncoder

/**
 * What the server said about a deletion attempt.
 *
 * [Refused] and [Failed] are kept apart because they need different words on screen: a
 * refusal is a rule the caller broke and the server explains it (a guest session has no
 * account), while a failure is the request not landing at all and is worth retrying.
 */
sealed interface DeleteAccountOutcome {
  data object Deleted : DeleteAccountOutcome
  data class Refused(val message: String) : DeleteAccountOutcome
  data class Failed(val message: String) : DeleteAccountOutcome
}

/**
 * Account deletion, in-app half — `DELETE /api/account`.
 *
 * Google Play requires both an in-app deletion path and a public web URL; this is the
 * in-app one, and haraan.app/account/delete is its twin. There is no id in the request:
 * a token can only ever delete the account it belongs to.
 *
 * The server ANONYMISES rather than deletes (bookings and payments are financial records
 * that have to survive), and it revokes every token this account holds — so once this
 * returns [DeleteAccountOutcome.Deleted] the token in hand is already dead and the caller
 * must drop its session rather than trying to refresh anything.
 */
class AccountDeletionRepository(
  private val baseUrl: String = ApiConfig.BASE_URL,
) {
  /**
   * @param reason optional, free text, shown to nobody but stored with the request.
   *
   * `confirm` travels as a QUERY PARAMETER rather than a JSON body on purpose:
   * HttpURLConnection is unreliable about bodies on DELETE (some stacks refuse to write
   * one, others quietly drop it), and a confirmation flag that can be silently dropped
   * would turn the server's deliberate "must be explicitly confirmed" guard into a 422
   * the user cannot get past. Verified against the live endpoint in both shapes.
   */
  suspend fun delete(token: String, reason: String? = null): DeleteAccountOutcome =
    withContext(Dispatchers.IO) {
      val query = buildString {
        append("?confirm=1")
        val trimmed = reason?.trim().orEmpty()
        if (trimmed.isNotEmpty()) {
          append("&reason=")
          append(URLEncoder.encode(trimmed.take(500), "UTF-8"))
        }
      }

      val connection = try {
        (URL("${baseUrl.trimEnd('/')}/api/account$query").openConnection() as HttpURLConnection).apply {
          requestMethod = "DELETE"
          connectTimeout = 15000
          // Deliberately long: this call erases rows across a dozen tables inside one
          // transaction, and a client that gives up early leaves the user staring at a
          // failure for work the server actually completed.
          readTimeout = 30000
          setRequestProperty("Accept", "application/json")
          setRequestProperty("Authorization", "Bearer $token")
        }
      } catch (e: Exception) {
        return@withContext DeleteAccountOutcome.Failed("Couldn't reach Haraan. Check your connection.")
      }

      try {
        val code = connection.responseCode
        val stream = if (code >= 400) connection.errorStream else connection.inputStream
        val body = stream?.let { BufferedReader(InputStreamReader(it)).use { r -> r.readText() } }.orEmpty()

        when {
          code in 200..299 -> DeleteAccountOutcome.Deleted
          // 401 is its own case: the token is already gone, so there is nothing left to
          // delete and nothing useful to retry — the caller should just end the session.
          code == 401 -> DeleteAccountOutcome.Refused("You're already signed out on this device.")
          code in 400..499 -> DeleteAccountOutcome.Refused(serverMessage(body) ?: "Haraan couldn't delete this account.")
          else -> DeleteAccountOutcome.Failed("Something went wrong at our end. Try again in a moment.")
        }
      } catch (e: Exception) {
        DeleteAccountOutcome.Failed("Couldn't reach Haraan. Check your connection.")
      } finally {
        connection.disconnect()
      }
    }

  /**
   * The server's own words, when it has any. Laravel answers a failed validation as
   * `{errors: {field: [msg]}}` and a refusal as `{error: msg}`, so both are read rather
   * than replacing a specific reason with a generic one.
   */
  private fun serverMessage(body: String): String? = runCatching {
    val json = JSONObject(body)
    json.optString("error").takeIf { it.isNotBlank() }
      ?: json.optJSONObject("errors")
        ?.let { errors -> errors.keys().asSequence().firstOrNull()?.let { errors.optJSONArray(it)?.optString(0) } }
        ?.takeIf { it.isNotBlank() }
      ?: json.optString("message").takeIf { it.isNotBlank() }
  }.getOrNull()
}
