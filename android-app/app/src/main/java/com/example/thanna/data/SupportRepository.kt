package com.example.thanna.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL

/** One message in the in-app support conversation. */
data class SupportMessageItem(
  val id: Long,
  val body: String,
  val fromAdmin: Boolean,
  val createdAt: String?,
)

/** The user's support conversation as returned by the API. */
data class SupportThreadData(
  val status: String,
  val messages: List<SupportMessageItem>,
)

/**
 * In-app support chat backed by /api/support. The user talks to the Haraan
 * support team, who reply from the Filament control panel. The chat screen loads
 * [getThread] on open and polls it while visible; [sendMessage] posts a message.
 */
class SupportRepository(
  private val baseUrl: String = ApiConfig.BASE_URL,
) {
  suspend fun getThread(token: String): SupportThreadData = withContext(Dispatchers.IO) {
    parse(request("/api/support/thread", "GET", token, null))
  }

  suspend fun sendMessage(token: String, body: String): SupportThreadData = withContext(Dispatchers.IO) {
    val payload = JSONObject().put("body", body).toString()
    parse(request("/api/support/messages", "POST", token, payload))
  }

  private fun parse(responseBody: String): SupportThreadData {
    val root = JSONObject(responseBody)
    val thread = root.optJSONObject("thread")
    val arr = root.optJSONArray("messages")
    val messages = buildList {
      if (arr != null) {
        for (i in 0 until arr.length()) {
          val o = arr.getJSONObject(i)
          add(
            SupportMessageItem(
              id = o.optLong("id", 0L),
              body = o.optString("body", ""),
              fromAdmin = o.optString("from", "user") == "admin",
              createdAt = o.optString("created_at", null).takeIf { !it.isNullOrBlank() && it != "null" },
            )
          )
        }
      }
    }
    return SupportThreadData(
      status = thread?.optString("status", "open") ?: "open",
      messages = messages,
    )
  }

  private fun request(path: String, method: String, token: String, jsonBody: String?): String {
    val connection = (URL("${baseUrl.trimEnd('/')}$path").openConnection() as HttpURLConnection).apply {
      requestMethod = method
      connectTimeout = 15000
      readTimeout = 15000
      setRequestProperty("Accept", "application/json")
      setRequestProperty("Authorization", "Bearer $token")
      if (jsonBody != null) {
        doOutput = true
        setRequestProperty("Content-Type", "application/json")
      }
    }
    try {
      if (jsonBody != null) {
        connection.outputStream.use { it.write(jsonBody.toByteArray()) }
      }
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
    if (body.isBlank()) "Unable to reach support." else JSONObject(body).optString("error", "Unable to reach support.")
  } catch (_: Exception) {
    "Unable to reach support."
  }
}
