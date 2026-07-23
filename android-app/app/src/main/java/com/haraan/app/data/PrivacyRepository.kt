package com.haraan.app.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL

/**
 * The signed-in user's privacy controls.
 *
 * Every field defaults to `true` to mirror the server's defaults: Haraan is a
 * public leaderboard product, so "visible" is the state a user is already in.
 */
data class PrivacySettings(
  val publicProfile: Boolean = true,
  val showStats: Boolean = true,
  val showDistrict: Boolean = true,
  val discoverable: Boolean = true,
)

class PrivacyRepository(
  private val baseUrl: String = ApiConfig.BASE_URL,
) {
  suspend fun fetch(token: String): PrivacySettings = withContext(Dispatchers.IO) {
    parse(request("GET", token, body = null))
  }

  /**
   * Sends only the toggle that changed — the server treats absent keys as
   * "leave alone", so two screens can't clobber each other's settings.
   */
  suspend fun update(token: String, field: String, value: Boolean): PrivacySettings = withContext(Dispatchers.IO) {
    parse(request("PUT", token, body = JSONObject().put(field, value).toString()))
  }

  private fun request(method: String, token: String, body: String?): String {
    val connection = (URL("${baseUrl.trimEnd('/')}/api/account/privacy").openConnection() as HttpURLConnection).apply {
      requestMethod = method
      connectTimeout = 15000
      readTimeout = 15000
      setRequestProperty("Accept", "application/json")
      setRequestProperty("Authorization", "Bearer $token")
      if (body != null) {
        setRequestProperty("Content-Type", "application/json")
        doOutput = true
      }
    }

    try {
      if (body != null) {
        connection.outputStream.use { it.write(body.toByteArray()) }
      }
      val code = connection.responseCode
      val stream = if (code >= 400) connection.errorStream else connection.inputStream
      val text = stream?.let { BufferedReader(InputStreamReader(it)).use { r -> r.readText() } } ?: ""
      if (code !in 200..299) {
        throw IllegalStateException("Couldn't reach your privacy settings.")
      }
      return text
    } finally {
      connection.disconnect()
    }
  }

  private fun parse(raw: String): PrivacySettings {
    val o = JSONObject(raw).optJSONObject("settings") ?: JSONObject()
    return PrivacySettings(
      publicProfile = o.optBoolean("publicProfile", true),
      showStats = o.optBoolean("showStats", true),
      showDistrict = o.optBoolean("showDistrict", true),
      discoverable = o.optBoolean("discoverable", true),
    )
  }
}
