package com.example.thanna.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL

/** One notification in the bell inbox (GET /api/notifications). */
data class NotificationItem(
  val id: Long,
  val title: String,
  val body: String,
  val imageUrl: String?,
  val deepLink: String?,
  val read: Boolean,
  val createdAt: String?,
)

/** The bell inbox: the delivered notifications for this user + the unread count. */
data class NotificationInbox(
  val unread: Int,
  val items: List<NotificationItem>,
)

/**
 * The app's bell inbox, backed by /api/notifications. The admin/Haraan team
 * composes messages in the Filament control panel; open apps refetch live via
 * the Reverb `notifications` signal. Mirrors [SupportRepository]'s HTTP shape.
 */
class NotificationRepository(
  private val baseUrl: String = ApiConfig.BASE_URL,
) {
  /** The user's inbox, newest first. Empty inbox on any failure (honest empty state). */
  suspend fun getInbox(token: String): NotificationInbox = withContext(Dispatchers.IO) {
    val root = JSONObject(request("/api/notifications", "GET", token, null))
    val arr = root.optJSONArray("notifications")
    val items = buildList {
      if (arr != null) {
        for (i in 0 until arr.length()) {
          val o = arr.getJSONObject(i)
          add(
            NotificationItem(
              id = o.optLong("id", 0L),
              title = o.optString("title", ""),
              body = o.optString("body", ""),
              imageUrl = o.optString("image_url", null).takeIf { !it.isNullOrBlank() && it != "null" },
              deepLink = o.optString("deep_link", null).takeIf { !it.isNullOrBlank() && it != "null" },
              read = o.optBoolean("read", false),
              createdAt = o.optString("created_at", null).takeIf { !it.isNullOrBlank() && it != "null" },
            )
          )
        }
      }
    }
    NotificationInbox(unread = root.optInt("unread", 0), items = items)
  }

  /** Mark one notification read, or all of them when [id] is null. */
  suspend fun markRead(token: String, id: Long? = null): Boolean = withContext(Dispatchers.IO) {
    val payload = JSONObject().apply { if (id != null) put("id", id) }.toString()
    runCatching { request("/api/notifications/read", "POST", token, payload) }.isSuccess
  }

  /** Register this device's FCM token (Phase 2 delivery). Best-effort. */
  suspend fun registerDevice(token: String, fcmToken: String): Boolean = withContext(Dispatchers.IO) {
    val payload = JSONObject().put("token", fcmToken).put("platform", "android").toString()
    runCatching { request("/api/devices/register", "POST", token, payload) }.isSuccess
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
        throw IllegalStateException("Request failed ($code)")
      }
      return text
    } finally {
      connection.disconnect()
    }
  }
}
