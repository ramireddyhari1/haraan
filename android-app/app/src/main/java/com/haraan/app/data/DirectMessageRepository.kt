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
  /** Group vs 1:1. Groups have a title (in [name]) and no single [playerId]. */
  val isGroup: Boolean = false,
  /** A few members' avatars (may hold nulls → render an initial), for the stacked row art. */
  val memberAvatars: List<String?> = emptyList(),
  val memberNames: List<String> = emptyList(),
  val memberCount: Int = 0,
)

/**
 * The message a reply is quoting, already flattened by the server.
 *
 * Carries the words, not a pointer: the quote has to render even when the original is far
 * above the loaded page, and an unsent original resolves to [deleted] rather than vanishing.
 */
data class QuotedMessage(
  val id: Long,
  val body: String,
  val deleted: Boolean,
  val senderName: String?,
  val mine: Boolean,
)

/** One emoji on a message: which, how many people, and whether one of them is you. */
data class MessageReaction(val emoji: String, val count: Int, val mine: Boolean)

data class ChatMessage(
  val id: Long,
  val body: String,
  /** True when this viewer sent it — decided by the SERVER, not by comparing ids here. */
  val mine: Boolean,
  val sentAt: String?,
  /** Unsent by its author: the row survives to keep the thread's order, the words do not. */
  val deleted: Boolean = false,
  /** Emoji reactions, already grouped and counted by the server. */
  val reactions: List<MessageReaction> = emptyList(),
  /** Someone else's words, passed on. Shown as a small "Forwarded" label. */
  val forwarded: Boolean = false,
  /** What this message is replying to, flattened for the bubble. Null when it replies to nothing. */
  val replyTo: QuotedMessage? = null,
  /** Who sent it — used to label incoming bubbles in a GROUP thread. */
  val senderName: String? = null,
  val senderAvatar: String? = null,
)

/** A player you may start a chat or group with — a mutual follow. */
data class ChatCandidate(
  val playerId: String,
  val name: String,
  val username: String?,
  val avatar: String?,
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

  /** Creating a group. Ready carries the new thread so the UI can open it immediately. */
  sealed interface GroupResult {
    data class Ready(val thread: ChatThread) : GroupResult
    /** A chosen member is not a mutual follow. */
    data object NotAllowed : GroupResult
    data object Failed : GroupResult
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
          add(parseThread(arr.getJSONObject(i)))
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

  /**
   * The thread, oldest first. Fetching also marks it read server-side.
   *
   * Pass [sinceId] to get only what has arrived after that message — what the open-thread
   * poll uses, so a quiet conversation costs an empty array every few seconds instead of a
   * fresh copy of all 300 messages.
   */
  /**
   * A thread page, plus how far the OTHER side has got.
   *
   * Receipts come back as two timestamps for the whole conversation rather than a status on
   * every message: the client compares them against each message's own time and gets the
   * same answer in a fraction of the payload. Null in a group — see the controller.
   */
  data class ThreadPage(
    val messages: List<ChatMessage>,
    val theirDeliveredAt: String? = null,
    val theirReadAt: String? = null,
  )

  /** Kept for callers that only want the lines. */
  suspend fun messages(
    token: String,
    conversationId: Long,
    sinceId: Long? = null,
  ): List<ChatMessage> = page(token, conversationId, sinceId).messages

  suspend fun page(
    token: String,
    conversationId: Long,
    sinceId: Long? = null,
  ): ThreadPage = withContext(Dispatchers.IO) {
    val path = "/api/dm/$conversationId/messages" +
      if (sinceId != null && sinceId > 0) "?since_id=$sinceId" else ""
    val c = conn(path, "GET", token)
    try {
      if (c.responseCode !in 200..299) return@withContext ThreadPage(emptyList())
      val body = BufferedReader(InputStreamReader(c.inputStream)).use { it.readText() }
      val root = JSONObject(body)
      val arr = root.optJSONArray("results") ?: return@withContext ThreadPage(emptyList())
      val lines = buildList {
        for (i in 0 until arr.length()) {
          val o = arr.getJSONObject(i)
          add(
            ChatMessage(
              id = o.optLong("id"),
              body = o.optString("body", ""),
              mine = o.optBoolean("mine", false),
              sentAt = o.optString("sent_at", null).cleanNull(),
              deleted = o.optBoolean("deleted", false),
              forwarded = o.optBoolean("forwarded", false),
              replyTo = o.optJSONObject("reply_to")?.let { q ->
                QuotedMessage(
                  id = q.optLong("id"),
                  body = q.optString("body", ""),
                  deleted = q.optBoolean("deleted", false),
                  senderName = q.optString("sender_name", null).cleanNull(),
                  mine = q.optBoolean("mine", false),
                )
              },
              reactions = (o.optJSONArray("reactions") ?: org.json.JSONArray()).let { arr ->
                buildList {
                  for (j in 0 until arr.length()) {
                    val r = arr.optJSONObject(j) ?: continue
                    val emoji = r.optString("emoji")
                    if (emoji.isNotBlank()) {
                      add(MessageReaction(emoji, r.optInt("count", 1), r.optBoolean("mine", false)))
                    }
                  }
                }
              },
              senderName = o.optString("sender_name", null).cleanNull(),
              senderAvatar = o.optString("sender_avatar", null).cleanNull(),
            )
          )
        }
      }
      ThreadPage(
        messages = lines,
        theirDeliveredAt = root.optString("their_delivered_at", null).cleanNull(),
        theirReadAt = root.optString("their_read_at", null).cleanNull(),
      )
    } catch (_: Exception) {
      ThreadPage(emptyList())
    } finally {
      c.disconnect()
    }
  }

  /**
   * Unsend one of your own messages. True when the server accepted it; false when it
   * refused (not yours, or already gone) so the caller can say so instead of guessing.
   */
  suspend fun unsend(token: String, conversationId: Long, messageId: Long): Boolean =
    withContext(Dispatchers.IO) {
      val c = conn("/api/dm/$conversationId/messages/$messageId", "DELETE", token)
      try {
        c.responseCode in 200..299
      } catch (_: Exception) {
        false
      } finally {
        c.disconnect()
      }
    }

  /**
   * React to a message, or clear your reaction by sending the same emoji again (or a blank).
   * True when the server took it.
   */
  suspend fun react(token: String, conversationId: Long, messageId: Long, emoji: String): Boolean =
    withContext(Dispatchers.IO) {
      try {
        val c = conn("/api/dm/$conversationId/messages/$messageId/reaction", "POST", token)
        c.doOutput = true
        c.setRequestProperty("Content-Type", "application/json")
        c.outputStream.use { it.write(JSONObject().put("emoji", emoji).toString().toByteArray()) }
        val ok = c.responseCode in 200..299
        c.disconnect()
        ok
      } catch (_: Exception) {
        false
      }
    }

  /**
   * Forward a message into another of your conversations. True when the server took it —
   * it checks that you are in both threads.
   */
  suspend fun forward(token: String, messageId: Long, toConversationId: Long): Boolean =
    withContext(Dispatchers.IO) {
      try {
        val c = conn("/api/dm/messages/$messageId/forward", "POST", token)
        c.doOutput = true
        c.setRequestProperty("Content-Type", "application/json")
        c.outputStream.use { it.write(JSONObject().put("to", toConversationId).toString().toByteArray()) }
        val ok = c.responseCode in 200..299
        c.disconnect()
        ok
      } catch (_: Exception) {
        false
      }
    }

  /** Null means it did not send — the caller must not leave the bubble on screen. */
  suspend fun send(
    token: String,
    conversationId: Long,
    body: String,
    replyToId: Long? = null,
  ): ChatMessage? =
    withContext(Dispatchers.IO) {
      val c = conn("/api/dm/$conversationId/messages", "POST", token)
      try {
        val payload = JSONObject().put("body", body).also { if (replyToId != null && replyToId > 0) it.put("reply_to_id", replyToId) }.toString()
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

  /** Mutual follows — the honest contents of the "add members" picker. */
  suspend fun eligibleMembers(token: String): List<ChatCandidate> = withContext(Dispatchers.IO) {
    val c = conn("/api/dm/eligible", "GET", token)
    try {
      if (c.responseCode !in 200..299) return@withContext emptyList()
      val body = BufferedReader(InputStreamReader(c.inputStream)).use { it.readText() }
      val arr = JSONObject(body).optJSONArray("results") ?: return@withContext emptyList()
      buildList {
        for (i in 0 until arr.length()) {
          val o = arr.getJSONObject(i)
          val pid = o.optString("player_id", null).cleanNull() ?: continue
          add(
            ChatCandidate(
              playerId = pid,
              name = o.optString("name", "").ifBlank { "Player" },
              username = o.optString("username", null).cleanNull(),
              avatar = o.optString("avatar", null).cleanNull(),
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

  /** Create a group. On success returns the new thread so the UI can open it directly. */
  suspend fun createGroup(token: String, title: String, memberPlayerIds: List<String>): GroupResult =
    withContext(Dispatchers.IO) {
      val c = conn("/api/dm/group", "POST", token)
      try {
        val payload = JSONObject()
          .put("title", title.trim())
          .put("members", org.json.JSONArray(memberPlayerIds))
          .toString()
        c.outputStream.use { it.write(payload.toByteArray()) }
        when (c.responseCode) {
          in 200..299 -> {
            val res = BufferedReader(InputStreamReader(c.inputStream)).use { it.readText() }
            GroupResult.Ready(parseThread(JSONObject(res)))
          }
          403 -> GroupResult.NotAllowed
          else -> GroupResult.Failed
        }
      } catch (_: Exception) {
        GroupResult.Failed
      } finally {
        c.disconnect()
      }
    }

  /** Leave a group. False means it did not take, so the UI keeps the thread in place. */
  suspend fun leaveGroup(token: String, conversationId: Long): Boolean = withContext(Dispatchers.IO) {
    val c = conn("/api/dm/$conversationId/leave", "POST", token)
    try {
      c.outputStream.use { it.write("{}".toByteArray()) }
      c.responseCode in 200..299
    } catch (_: Exception) {
      false
    } finally {
      c.disconnect()
    }
  }

  /** One thread card → [ChatThread]. Shared by the list and group-create responses. */
  private fun parseThread(o: JSONObject): ChatThread {
    val avatars = o.optJSONArray("member_avatars")
    val names = o.optJSONArray("member_names")
    return ChatThread(
      id = o.optLong("id"),
      playerId = o.optString("player_id", null).cleanNull(),
      name = o.optString("name", "").ifBlank { "Player" },
      username = o.optString("username", null).cleanNull(),
      avatar = o.optString("avatar", null).cleanNull(),
      lastMessage = o.optString("last_message", null).cleanNull(),
      lastMessageAt = o.optString("last_message_at", null).cleanNull(),
      unreadCount = o.optInt("unread_count", 0),
      isGroup = o.optBoolean("is_group", false),
      memberAvatars = buildList {
        if (avatars != null) for (i in 0 until avatars.length()) {
          add(avatars.optString(i, null).cleanNull())
        }
      },
      memberNames = buildList {
        if (names != null) for (i in 0 until names.length()) {
          names.optString(i, null).cleanNull()?.let { add(it) }
        }
      },
      memberCount = o.optInt("member_count", 0),
    )
  }

  private fun String?.cleanNull(): String? =
    this?.takeIf { it.isNotBlank() && it != "null" }
}
