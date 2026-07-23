package com.haraan.app.data

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

/**
 * An issue topic offered before the chat starts. Admin-managed server-side, so
 * the app never hardcodes the list. [iconKey] names one of a fixed vector set —
 * an unknown key (a topic added after this build shipped) falls back to a chat
 * bubble rather than rendering nothing.
 */
data class SupportCategoryItem(
  val id: Long,
  val label: String,
  val iconKey: String,
  val subtitle: String?,
)

/** The user's support conversation as returned by the API. */
data class SupportThreadData(
  val status: String,
  val category: String?,
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

  /** Topics for the picker. Empty on failure — the chat still works without them. */
  suspend fun getCategories(token: String): List<SupportCategoryItem> = withContext(Dispatchers.IO) {
    val arr = JSONObject(request("/api/support/categories", "GET", token, null))
      .optJSONArray("categories") ?: return@withContext emptyList()
    buildList {
      for (i in 0 until arr.length()) {
        val o = arr.getJSONObject(i)
        val label = o.optString("label", "")
        if (label.isNotBlank()) {
          add(
            SupportCategoryItem(
              id = o.optLong("id", 0L),
              label = label,
              iconKey = o.optString("icon_key", "chat"),
              subtitle = o.optString("subtitle", null).takeIf { !it.isNullOrBlank() && it != "null" },
            )
          )
        }
      }
    }
  }

  /**
   * Post a message. [categoryId] labels the conversation with the topic the user
   * picked; the server only applies it to a thread that isn't classified yet.
   */
  suspend fun sendMessage(token: String, body: String, categoryId: Long? = null): SupportThreadData =
    withContext(Dispatchers.IO) {
      val payload = JSONObject().put("body", body)
        .apply { if (categoryId != null) put("category_id", categoryId) }
        .toString()
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
      category = thread?.optString("category", null).takeIf { !it.isNullOrBlank() && it != "null" },
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
