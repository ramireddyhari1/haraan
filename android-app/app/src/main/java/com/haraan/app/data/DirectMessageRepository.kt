package com.haraan.app.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL
import java.net.URLEncoder

/** One thread in the chat list, from the viewer's point of view. */
data class ChatThread(
  val id: Long,
  val playerId: String?,
  val name: String,
  val username: String?,
  val avatar: String?,
  val lastMessage: String?,
  val lastMessageAt: String?,
  val unreadCount: Int,
)

data class ChatMessage(
  val id: Long,
  val body: String,
  /** True when this viewer sent it — decided by the SERVER, not by comparing ids here. */
  val mine: Boolean,
  val sentAt: String?,
)

/**
 * Player-to-player direct messages.
 *
 * Deliberately NOT the support repository: `/api/support` is the player↔admin desk,
 * a different system with a different shape. Messaging here is gated on a MUTUAL
 * follow server-side, so [openWith] can legitimately fail with 403 — see
 * [OpenChatResult.NotAllowed], which the UI must explain rather than swallow.
 */
class DirectMessageRepository(
  private val baseUrl: String = ApiConfig.BASE_URL,
) {

  /**
   * Loading the thread list. "Nothing here" and "the call failed" MUST be different
   * values: collapsing them renders a server error as a friendly empty state, which
   * is how a screen ends up confidently telling you that you have no messages when
   * it never reached the server.
   */
  sealed interface ThreadsResult {
    data class Ready(val threads: List<ChatThread>, val unreadTotal: Int) : ThreadsResult
    data object Failed : ThreadsResult
  }

  sealed interface OpenChatResult {
    data class Ready(val conversationId: Long) : OpenChatResult
    /** Mutual follow missing. The UI says why instead of failing silently. */
    data object NotAllowed : OpenChatResult
    data object Failed : OpenChatResult
  }

  private fun conn(path: String, method: String, token: String): HttpURLConnection =
    (URL("${baseUrl.trimEnd('/')}$path").openConnection() as HttpURLConnection).apply {
      requestMethod = method
      connectTimeout = 12000
      readTimeout = 12000
      setRequestProperty("Accept", "application/json")
      setRequestProperty("Authorization", "Bearer $token")
      if (method == "POST") {
        doOutput = true
        setRequestProperty("Content-Type", "application/json")
      }
    }

  /** Every thread, newest activity first, plus the total unread for the tab badge. */
  suspend fun threads(token: String): ThreadsResult = withContext(Dispatchers.IO) {
    val c = conn("/api/dm", "GET", token)
    try {
      if (c.responseCode !in 200..299) return@withContext ThreadsResult.Failed
      val body = BufferedReader(InputStreamReader(c.inputStream)).use { it.readText() }
      val json = JSONObject(body)
      val arr = json.optJSONArray("results")
      val list = buildList {
        if (arr != null) for (i in 0 until arr.length()) {
          val o = arr.getJSONObject(i)
          add(
            ChatThread(
              id = o.optLong("id"),
              playerId = o.optString("player_id", null).cleanNull(),
              name = o.optString("name", "").ifBlank { "Player" },
              username = o.optString("username", null).cleanNull(),
              avatar = o.optString("avatar", null).cleanNull(),
              lastMessage = o.optString("last_message", null).cleanNull(),
              lastMessageAt = o.optString("last_message_at", null).cleanNull(),
              unreadCount = o.optInt("unread_count", 0),
            )
          )
        }
      }
      ThreadsResult.Ready(list, json.optInt("unread_total", 0))
    } catch (_: Exception) {
      ThreadsResult.Failed
    } finally {
      c.disconnect()
    }
  }

  /** Open (or start) the 1:1 with a player. */
  suspend fun openWith(token: String, playerId: String): OpenChatResult = withContext(Dispatchers.IO) {
    val encoded = URLEncoder.encode(playerId.trim(), "UTF-8")
    val c = conn("/api/dm/with/$encoded", "POST", token)
    try {
      c.outputStream.use { it.write("{}".toByteArray()) }
      when (c.responseCode) {
        in 200..299 -> {
          val body = BufferedReader(InputStreamReader(c.inputStream)).use { it.readText() }
          OpenChatResult.Ready(JSONObject(body).optLong("id"))
        }
        403 -> OpenChatResult.NotAllowed
        else -> OpenChatResult.Failed
      }
    } catch (_: Exception) {
      OpenChatResult.Failed
    } finally {
      c.disconnect()
    }
  }

  /** The thread, oldest first. Fetching also marks it read server-side. */
  suspend fun messages(token: String, conversationId: Long): List<ChatMessage> = withContext(Dispatchers.IO) {
    val c = conn("/api/dm/$conversationId/messages", "GET", token)
    try {
      if (c.responseCode !in 200..299) return@withContext emptyList()
      val body = BufferedReader(InputStreamReader(c.inputStream)).use { it.readText() }
      val arr = JSONObject(body).optJSONArray("results") ?: return@withContext emptyList()
      buildList {
        for (i in 0 until arr.length()) {
          val o = arr.getJSONObject(i)
          add(
            ChatMessage(
              id = o.optLong("id"),
              body = o.optString("body", ""),
              mine = o.optBoolean("mine", false),
              sentAt = o.optString("sent_at", null).cleanNull(),
            )
          )
        }
      }
    } catch (_: Exception) {
      emptyList()
    } finally {
      c.disconnect()
    }
  }

  /** Null means it did not send — the caller must not leave the bubble on screen. */
  suspend fun send(token: String, conversationId: Long, body: String): ChatMessage? =
    withContext(Dispatchers.IO) {
      val c = conn("/api/dm/$conversationId/messages", "POST", token)
      try {
        val payload = JSONObject().put("body", body).toString()
        c.outputStream.use { it.write(payload.toByteArray()) }
        if (c.responseCode !in 200..299) return@withContext null
        val res = BufferedReader(InputStreamReader(c.inputStream)).use { it.readText() }
        val o = JSONObject(res)
        ChatMessage(
          id = o.optLong("id"),
          body = o.optString("body", body),
          mine = true,
          sentAt = o.optString("sent_at", null).cleanNull(),
        )
      } catch (_: Exception) {
        null
      } finally {
        c.disconnect()
      }
    }

  private fun String?.cleanNull(): String? =
    this?.takeIf { it.isNotBlank() && it != "null" }
}
