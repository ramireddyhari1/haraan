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
  /** This viewer muted the thread: no phone notification, a bell-off on the row. */
  val muted: Boolean = false,
  /** Pinned to the top of this viewer's list. */
  val pinned: Boolean = false,
  /** Moved out of the main list by this viewer; a new message brings it back. */
  val archived: Boolean = false,
  /** "incoming" = a message request this viewer must answer; "outgoing" = waiting on them. */
  val request: String? = null,
  /** Set when this is a team chat made for a match. */
  val matchId: String? = null,
)

/** One answer in a poll, with its votes as this viewer sees them. */
data class ChatPollOption(
  val id: Long,
  val label: String,
  val votes: Int,
  val mine: Boolean,
  /** In a group: who picked it. Empty in a 1:1. */
  val voterNames: List<String> = emptyList(),
)

data class ChatPoll(
  val id: Long,
  val question: String,
  val multiple: Boolean,
  /** People who have voted at all. */
  val voters: Int,
  val options: List<ChatPollOption>,
)

/** One message found by searching inside a thread. */
data class ChatSearchHit(
  val id: Long,
  val body: String,
  val sentAt: String?,
  val mine: Boolean,
  val senderName: String?,
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
  /** A shared match / venue / player. Null for plain text. */
  val share: SharedCard? = null,
  /** Who sent it — matched against a "typing" signal to name the typist in a group. */
  val senderId: Long = 0L,
  /** A photo (absolute URL). [body] is then its caption, possibly empty. */
  val image: String? = null,
  /** The photo's pixel size, so the bubble holds its shape before the image loads. */
  val imageWidth: Int? = null,
  val imageHeight: Int? = null,
  /** "Ravi added Kiran" — a group event drawn centred, not as anyone's bubble. */
  val system: Boolean = false,
  /** Corrected by its author after sending. */
  val edited: Boolean = false,
  /** A voice note (absolute URL) and its length. */
  val audio: String? = null,
  val audioDurationMs: Int? = null,
  /** A poll, built for this viewer. */
  val poll: ChatPoll? = null,
)

/** One person in a conversation, as the info screen lists them. */
data class ChatMember(
  val playerId: String,
  val name: String,
  val username: String?,
  val avatar: String?,
  val isAdmin: Boolean,
  val isMe: Boolean,
)

/** What a thread's info screen shows. */
data class ChatInfo(
  val id: Long,
  val isGroup: Boolean,
  val title: String,
  val avatar: String?,
  val muted: Boolean,
  /** When a timed mute runs out (ISO); null while muted means "until turned back on". */
  val mutedUntil: String?,
  val isAdmin: Boolean,
  val maxMembers: Int,
  val members: List<ChatMember>,
)

/**
 * A match, venue or player shared into a chat, as the server built it for THIS viewer.
 *
 * Built at read time, never stored as a copy: a live match's [line] is the score now, and
 * [available] is false when the reader may not see it (a private match they're not in, a venue
 * taken down) — the card then shows only its neutral [title].
 */
data class SharedCard(
  /** "match", "venue" or "player". */
  val type: String,
  /** Match id, venue id, or Player ID (HRN…). */
  val id: String,
  val available: Boolean,
  val title: String,
  val subtitle: String = "",
  val image: String? = null,
  /** The score for a match, "From ₹600/hr" for a venue. */
  val line: String = "",
  /** LIVE / UPCOMING / RESULT, a rating, VERIFIED. */
  val badge: String? = null,
  /** A finished match's result line. */
  val note: String? = null,
  val live: Boolean = false,
  val sport: String = "",
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
    data class Ready(
      val threads: List<ChatThread>,
      val unreadTotal: Int,
      /** The public channel this inbox listens on. Null = live inbox off in /control. */
      val inboxChannel: String? = null,
      /** Whether the share (+) button shows in a conversation — a /control switch. */
      val shareCards: Boolean = false,
      /** Whether the photo button shows in a conversation — a /control switch. */
      val photos: Boolean = false,
      /** How many chats a player may pin — a /control setting. */
      val maxPins: Int = 3,
      /** Message requests waiting for this viewer. */
      val requestsCount: Int = 0,
      val voiceNotes: Boolean = false,
      val polls: Boolean = false,
    ) : ThreadsResult
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
    /** Refused with the server's own sentence (e.g. a team chat with nobody accepted yet). */
    data class Refused(val message: String) : GroupResult
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
      ThreadsResult.Ready(
        threads = list,
        unreadTotal = json.optInt("unread_total", 0),
        inboxChannel = json.optString("inbox_channel", null).cleanNull(),
        shareCards = json.optBoolean("share_cards", false),
        photos = json.optBoolean("photos", false),
        maxPins = json.optInt("max_pins", 3),
        requestsCount = json.optInt("requests_count", 0),
        voiceNotes = json.optBoolean("voice_notes", false),
        polls = json.optBoolean("polls", false),
      )
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
    /** More history above this page. False on a delta, where the question doesn't arise. */
    val hasMore: Boolean = false,
    /** False when the call failed — an empty page and a failed one must not look alike. */
    val ok: Boolean = true,
    /** The reader's own user id, so their own "typing" echo can be ignored. */
    val viewerId: Long = 0L,
    /** Whether "typing…" is switched on in /control. */
    val typingIndicator: Boolean = false,
    /** 1:1 only, when /control allows it: the other person's last activity (ISO) and "online". */
    val theirLastSeenAt: String? = null,
    val theirOnline: Boolean = false,
    /** Minutes after sending that a message may be edited; 0 = editing off. */
    val editWindowMinutes: Int = 0,
    /** "incoming" / "outgoing" while a message request waits; null otherwise. */
    val request: String? = null,
    /** For an outgoing request: how many more messages may go before they answer. */
    val requestMessagesLeft: Int? = null,
    /** Whether this viewer may post here right now (false = the composer is replaced). */
    val canSend: Boolean = true,
    val voiceNotes: Boolean = false,
    val voiceMaxSeconds: Int = 120,
    val polls: Boolean = false,
  )

  /** Kept for callers that only want the lines. */
  suspend fun messages(
    token: String,
    conversationId: Long,
    sinceId: Long? = null,
  ): List<ChatMessage> = page(token, conversationId, sinceId).messages

  /**
   * [limit] asks for the newest page of that size; with [beforeId] it is the page just above
   * that message — what scrolling to the top loads. [sinceId] is the "what's new" delta.
   */
  suspend fun page(
    token: String,
    conversationId: Long,
    sinceId: Long? = null,
    beforeId: Long? = null,
    limit: Int? = null,
  ): ThreadPage = withContext(Dispatchers.IO) {
    val query = buildList {
      if (sinceId != null && sinceId > 0) add("since_id=$sinceId")
      if (beforeId != null && beforeId > 0) add("before_id=$beforeId")
      if (limit != null && limit > 0) add("limit=$limit")
    }
    val path = "/api/dm/$conversationId/messages" +
      if (query.isEmpty()) "" else "?" + query.joinToString("&")
    val c = conn(path, "GET", token)
    try {
      if (c.responseCode !in 200..299) return@withContext ThreadPage(emptyList(), ok = false)
      val body = BufferedReader(InputStreamReader(c.inputStream)).use { it.readText() }
      val root = JSONObject(body)
      val arr = root.optJSONArray("results") ?: return@withContext ThreadPage(emptyList(), ok = false)
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
              share = o.optJSONObject("share")?.let(::parseCard),
              senderId = o.optLong("sender_id", 0L),
              image = ApiConfig.mediaUrl(o.optString("image", null).cleanNull()),
              imageWidth = o.optInt("image_width", 0).takeIf { it > 0 },
              imageHeight = o.optInt("image_height", 0).takeIf { it > 0 },
              system = o.optString("kind", "") == "system",
              edited = o.optBoolean("edited", false),
              audio = ApiConfig.mediaUrl(o.optString("audio", null).cleanNull()),
              audioDurationMs = o.optInt("audio_duration_ms", 0).takeIf { it > 0 },
              poll = o.optJSONObject("poll")?.let(::parsePoll),
            )
          )
        }
      }
      ThreadPage(
        messages = lines,
        theirDeliveredAt = root.optString("their_delivered_at", null).cleanNull(),
        theirReadAt = root.optString("their_read_at", null).cleanNull(),
        hasMore = root.optBoolean("has_more", false),
        viewerId = root.optLong("viewer_id", 0L),
        typingIndicator = root.optBoolean("typing_indicator", false),
        theirLastSeenAt = root.optString("their_last_seen_at", null).cleanNull(),
        theirOnline = root.optBoolean("their_online", false),
        editWindowMinutes = root.optInt("edit_window_minutes", 0),
        request = root.optString("request", null).cleanNull(),
        requestMessagesLeft = if (root.isNull("request_messages_left")) null else root.optInt("request_messages_left"),
        canSend = root.optBoolean("can_send", true),
        voiceNotes = root.optBoolean("voice_notes", false),
        voiceMaxSeconds = root.optInt("voice_max_seconds", 120),
        polls = root.optBoolean("polls", false),
      )
    } catch (_: Exception) {
      ThreadPage(emptyList(), ok = false)
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
    share: SharedCard? = null,
  ): ChatMessage? =
    withContext(Dispatchers.IO) {
      val c = conn("/api/dm/$conversationId/messages", "POST", token)
      try {
        val payload = JSONObject().put("body", body).also {
          if (replyToId != null && replyToId > 0) it.put("reply_to_id", replyToId)
          if (share != null) it.put("share", JSONObject().put("type", share.type).put("id", share.id))
        }.toString()
        c.outputStream.use { it.write(payload.toByteArray()) }
        if (c.responseCode !in 200..299) return@withContext null
        val res = BufferedReader(InputStreamReader(c.inputStream)).use { it.readText() }
        val o = JSONObject(res)
        ChatMessage(
          id = o.optLong("id"),
          body = o.optString("body", body),
          mine = true,
          sentAt = o.optString("sent_at", null).cleanNull(),
          share = o.optJSONObject("share")?.let(::parseCard) ?: share,
        )
      } catch (_: Exception) {
        null
      } finally {
        c.disconnect()
      }
    }

  /**
   * "I'm typing here." Fire-and-forget: nothing typed is sent, the server throttles it, and a
   * failure costs only the dots on the other phone.
   */
  suspend fun typing(token: String, conversationId: Long) = withContext(Dispatchers.IO) {
    val c = conn("/api/dm/$conversationId/typing", "POST", token)
    try {
      c.outputStream.use { it.write("{}".toByteArray()) }
      c.responseCode
    } catch (_: Exception) {
      0
    } finally {
      c.disconnect()
    }
  }

  /**
   * What the share picker lists for one [type] ("match" / "venue" / "player"), filtered by
   * [q]. Empty on failure — the picker says "nothing found" either way, and nothing is sent.
   */
  suspend fun shareSearch(token: String, type: String, q: String): List<SharedCard> =
    withContext(Dispatchers.IO) {
      val path = "/api/dm/share/search?type=" + URLEncoder.encode(type, "UTF-8") +
        "&q=" + URLEncoder.encode(q.trim(), "UTF-8")
      val c = conn(path, "GET", token)
      try {
        if (c.responseCode !in 200..299) return@withContext emptyList()
        val body = BufferedReader(InputStreamReader(c.inputStream)).use { it.readText() }
        val arr = JSONObject(body).optJSONArray("results") ?: return@withContext emptyList()
        buildList { for (i in 0 until arr.length()) arr.optJSONObject(i)?.let { add(parseCard(it)) } }
      } catch (_: Exception) {
        emptyList()
      } finally {
        c.disconnect()
      }
    }

  /** One card, fresh — a LIVE match card re-reads itself with this while it is on screen. */
  suspend fun shareCard(token: String, type: String, id: String): SharedCard? =
    withContext(Dispatchers.IO) {
      val path = "/api/dm/share/card?type=" + URLEncoder.encode(type, "UTF-8") +
        "&id=" + URLEncoder.encode(id, "UTF-8")
      val c = conn(path, "GET", token)
      try {
        if (c.responseCode !in 200..299) return@withContext null
        parseCard(JSONObject(BufferedReader(InputStreamReader(c.inputStream)).use { it.readText() }))
      } catch (_: Exception) {
        null
      } finally {
        c.disconnect()
      }
    }

  private fun parseCard(o: JSONObject): SharedCard = SharedCard(
    type = o.optString("type"),
    id = o.optString("id"),
    available = o.optBoolean("available", false),
    title = o.optString("title", ""),
    subtitle = o.optString("subtitle", ""),
    // Venue photos come back absolute; player avatars are root-relative "/storage/…".
    image = ApiConfig.mediaUrl(o.optString("image", null).cleanNull()),
    line = o.optString("line", ""),
    badge = o.optString("badge", null).cleanNull(),
    note = o.optString("note", null).cleanNull(),
    live = o.optBoolean("live", false),
    sport = o.optString("sport", ""),
  )

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
      muted = o.optBoolean("muted", false),
      pinned = o.optBoolean("pinned", false),
      archived = o.optBoolean("archived", false),
      request = o.optString("request", null).cleanNull(),
      matchId = o.optString("match_id", null).cleanNull(),
    )
  }

  // ── Tier 3: polls, voice notes, message requests, team chats ──────────────────────

  private fun parsePoll(o: JSONObject): ChatPoll {
    val arr = o.optJSONArray("options") ?: org.json.JSONArray()
    return ChatPoll(
      id = o.optLong("id"),
      question = o.optString("question", ""),
      multiple = o.optBoolean("multiple", false),
      voters = o.optInt("voters", 0),
      options = buildList {
        for (i in 0 until arr.length()) {
          val op = arr.optJSONObject(i) ?: continue
          val names = op.optJSONArray("voter_names")
          add(
            ChatPollOption(
              id = op.optLong("id"),
              label = op.optString("label", ""),
              votes = op.optInt("votes", 0),
              mine = op.optBoolean("mine", false),
              voterNames = buildList { if (names != null) for (j in 0 until names.length()) add(names.optString(j)) },
            )
          )
        }
      },
    )
  }

  /** Send a poll. Null means it did not send. */
  suspend fun createPoll(token: String, conversationId: Long, question: String, options: List<String>, multiple: Boolean): ChatMessage? =
    withContext(Dispatchers.IO) {
      val c = conn("/api/dm/$conversationId/poll", "POST", token)
      try {
        val payload = JSONObject()
          .put("question", question.trim())
          .put("options", org.json.JSONArray(options.map { it.trim() }.filter { it.isNotEmpty() }))
          .put("multiple", multiple)
        c.outputStream.use { it.write(payload.toString().toByteArray()) }
        if (c.responseCode !in 200..299) return@withContext null
        val o = JSONObject(BufferedReader(InputStreamReader(c.inputStream)).use { it.readText() })
        ChatMessage(
          id = o.optLong("id"),
          body = o.optString("body", question),
          mine = true,
          sentAt = o.optString("sent_at", null).cleanNull(),
          poll = o.optJSONObject("poll")?.let(::parsePoll),
        )
      } catch (_: Exception) {
        null
      } finally {
        c.disconnect()
      }
    }

  /** Replace this viewer's vote (empty = take it back). Returns the poll as it now stands. */
  suspend fun vote(token: String, conversationId: Long, pollId: Long, optionIds: List<Long>): ChatPoll? =
    withContext(Dispatchers.IO) {
      val c = conn("/api/dm/$conversationId/polls/$pollId/vote", "POST", token)
      try {
        c.outputStream.use { it.write(JSONObject().put("options", org.json.JSONArray(optionIds)).toString().toByteArray()) }
        if (c.responseCode !in 200..299) return@withContext null
        JSONObject(BufferedReader(InputStreamReader(c.inputStream)).use { it.readText() })
          .optJSONObject("poll")?.let(::parsePoll)
      } catch (_: Exception) {
        null
      } finally {
        c.disconnect()
      }
    }

  /** Upload a recorded voice note (AAC in .m4a). Null means it did not send. */
  suspend fun sendVoice(token: String, conversationId: Long, audio: ByteArray, durationMs: Int, replyToId: Long?): ChatMessage? =
    withContext(Dispatchers.IO) {
      val boundary = "----HaraanVoice${System.currentTimeMillis()}"
      val c = (URL("${baseUrl.trimEnd('/')}/api/dm/$conversationId/voice").openConnection() as HttpURLConnection).apply {
        requestMethod = "POST"
        doOutput = true
        connectTimeout = 20000
        readTimeout = 60000
        setRequestProperty("Content-Type", "multipart/form-data; boundary=$boundary")
        setRequestProperty("Accept", "application/json")
        setRequestProperty("Authorization", "Bearer $token")
      }
      try {
        c.outputStream.use { out ->
          fun field(k: String, v: String) {
            out.write("--$boundary\r\nContent-Disposition: form-data; name=\"$k\"\r\n\r\n$v\r\n".toByteArray())
          }
          field("duration_ms", durationMs.toString())
          if (replyToId != null && replyToId > 0) field("reply_to_id", replyToId.toString())
          out.write("--$boundary\r\nContent-Disposition: form-data; name=\"audio\"; filename=\"voice.m4a\"\r\nContent-Type: audio/mp4\r\n\r\n".toByteArray())
          out.write(audio)
          out.write("\r\n--$boundary--\r\n".toByteArray())
        }
        if (c.responseCode !in 200..299) return@withContext null
        val o = JSONObject(BufferedReader(InputStreamReader(c.inputStream)).use { it.readText() })
        ChatMessage(
          id = o.optLong("id"),
          body = "",
          mine = true,
          sentAt = o.optString("sent_at", null).cleanNull(),
          audio = ApiConfig.mediaUrl(o.optString("audio", null).cleanNull()),
          audioDurationMs = o.optInt("audio_duration_ms", durationMs),
        )
      } catch (_: Exception) {
        null
      } finally {
        c.disconnect()
      }
    }

  suspend fun acceptRequest(token: String, conversationId: Long): Outcome =
    postOutcome("/api/dm/$conversationId/request/accept", token, JSONObject())

  suspend fun declineRequest(token: String, conversationId: Long): Outcome =
    postOutcome("/api/dm/$conversationId/request/decline", token, JSONObject())

  /** Open (or make) the team chat for a match this viewer hosts. */
  suspend fun teamGroup(token: String, matchId: String): GroupResult = withContext(Dispatchers.IO) {
    val c = conn("/api/dm/match/${URLEncoder.encode(matchId, "UTF-8")}/team", "POST", token)
    try {
      c.outputStream.use { it.write("{}".toByteArray()) }
      when (c.responseCode) {
        in 200..299 -> GroupResult.Ready(parseThread(JSONObject(BufferedReader(InputStreamReader(c.inputStream)).use { it.readText() })))
        in 400..499 -> GroupResult.Refused(readError(c) ?: "Couldn't open the team chat.")
        else -> GroupResult.Failed
      }
    } catch (_: Exception) {
      GroupResult.Failed
    } finally {
      c.disconnect()
    }
  }

  // ── Tier 2: list housekeeping, edits, search ───────────────────────────────────────

  private suspend fun postOutcome(path: String, token: String, body: JSONObject): Outcome = withContext(Dispatchers.IO) {
    val c = conn(path, "POST", token)
    try {
      c.outputStream.use { it.write(body.toString().toByteArray()) }
      outcomeOf(c)
    } catch (_: Exception) {
      Outcome.Failed
    } finally {
      c.disconnect()
    }
  }

  /** Pin or unpin. Refused (with the server's sentence) past the /control pin cap. */
  suspend fun pin(token: String, conversationId: Long, pinned: Boolean): Outcome =
    postOutcome("/api/dm/$conversationId/pin", token, JSONObject().put("pinned", pinned))

  suspend fun archive(token: String, conversationId: Long, archived: Boolean): Outcome =
    postOutcome("/api/dm/$conversationId/archive", token, JSONObject().put("archived", archived))

  /** "Delete chat" for this viewer only. */
  suspend fun clear(token: String, conversationId: Long): Outcome =
    postOutcome("/api/dm/$conversationId/clear", token, JSONObject())

  /** Correct one of your own messages. Refused outside the edit window. */
  suspend fun edit(token: String, conversationId: Long, messageId: Long, body: String): Outcome =
    postOutcome("/api/dm/$conversationId/messages/$messageId/edit", token, JSONObject().put("body", body.trim()))

  /** Messages in this thread containing [q], newest first. Empty on failure or a short query. */
  suspend fun search(token: String, conversationId: Long, q: String): List<ChatSearchHit> = withContext(Dispatchers.IO) {
    val c = conn("/api/dm/$conversationId/search?q=" + URLEncoder.encode(q.trim(), "UTF-8"), "GET", token)
    try {
      if (c.responseCode !in 200..299) return@withContext emptyList()
      val arr = JSONObject(BufferedReader(InputStreamReader(c.inputStream)).use { it.readText() })
        .optJSONArray("results") ?: return@withContext emptyList()
      buildList {
        for (i in 0 until arr.length()) {
          val o = arr.optJSONObject(i) ?: continue
          add(
            ChatSearchHit(
              id = o.optLong("id"),
              body = o.optString("body", ""),
              sentAt = o.optString("sent_at", null).cleanNull(),
              mine = o.optBoolean("mine", false),
              senderName = o.optString("sender_name", null).cleanNull(),
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

  // ── Photos, mute, info and group management ─────────────────────────────────────────

  /** Outcome of a call that can be refused for a reason the UI should say out loud. */
  sealed interface Outcome {
    data object Ok : Outcome
    /** The server's own sentence ("Only the group admin can remove people."). */
    data class Refused(val message: String) : Outcome
    data object Failed : Outcome
  }

  private fun readError(c: HttpURLConnection): String? = try {
    val stream = c.errorStream ?: return null
    val txt = BufferedReader(InputStreamReader(stream)).use { it.readText() }
    JSONObject(txt).optString("error", null).cleanNull()
      ?: JSONObject(txt).optString("message", null).cleanNull()
  } catch (_: Exception) {
    null
  }

  private fun outcomeOf(c: HttpURLConnection): Outcome {
    val code = c.responseCode
    return when {
      code in 200..299 -> Outcome.Ok
      code in 400..499 -> Outcome.Refused(readError(c) ?: "That didn't work.")
      else -> Outcome.Failed
    }
  }

  /** Multipart upload of one JPEG as `image`, plus string fields. */
  private fun multipart(path: String, token: String, image: ByteArray, fields: Map<String, String>): HttpURLConnection {
    val boundary = "----HaraanChat${System.currentTimeMillis()}"
    val c = (URL("${baseUrl.trimEnd('/')}$path").openConnection() as HttpURLConnection).apply {
      requestMethod = "POST"
      doOutput = true
      connectTimeout = 20000
      readTimeout = 60000
      setRequestProperty("Content-Type", "multipart/form-data; boundary=$boundary")
      setRequestProperty("Accept", "application/json")
      setRequestProperty("Authorization", "Bearer $token")
    }
    c.outputStream.use { out ->
      fields.forEach { (k, v) ->
        out.write("--$boundary\r\n".toByteArray())
        out.write("Content-Disposition: form-data; name=\"$k\"\r\n\r\n".toByteArray())
        out.write(v.toByteArray(Charsets.UTF_8))
        out.write("\r\n".toByteArray())
      }
      out.write("--$boundary\r\n".toByteArray())
      out.write("Content-Disposition: form-data; name=\"image\"; filename=\"photo.jpg\"\r\n".toByteArray())
      out.write("Content-Type: image/jpeg\r\n\r\n".toByteArray())
      out.write(image)
      out.write("\r\n--$boundary--\r\n".toByteArray())
    }
    return c
  }

  /** Send a photo (already shrunk to JPEG). Null means it did not send. */
  suspend fun sendPhoto(
    token: String,
    conversationId: Long,
    jpeg: ByteArray,
    caption: String,
    replyToId: Long?,
  ): ChatMessage? = withContext(Dispatchers.IO) {
    var c: HttpURLConnection? = null
    try {
      val fields = buildMap {
        if (caption.isNotBlank()) put("body", caption.trim())
        if (replyToId != null && replyToId > 0) put("reply_to_id", replyToId.toString())
      }
      c = multipart("/api/dm/$conversationId/photo", token, jpeg, fields)
      if (c.responseCode !in 200..299) return@withContext null
      val o = JSONObject(BufferedReader(InputStreamReader(c.inputStream)).use { it.readText() })
      ChatMessage(
        id = o.optLong("id"),
        body = o.optString("body", ""),
        mine = true,
        sentAt = o.optString("sent_at", null).cleanNull(),
        image = ApiConfig.mediaUrl(o.optString("image", null).cleanNull()),
        imageWidth = o.optInt("image_width", 0).takeIf { it > 0 },
        imageHeight = o.optInt("image_height", 0).takeIf { it > 0 },
      )
    } catch (_: Exception) {
      null
    } finally {
      c?.disconnect()
    }
  }

  /** Mute for [hours] (8, 168, or -1 = until turned back on); 0 unmutes. Null on failure. */
  suspend fun mute(token: String, conversationId: Long, hours: Int): Boolean? = withContext(Dispatchers.IO) {
    val c = conn("/api/dm/$conversationId/mute", "POST", token)
    try {
      c.outputStream.use { it.write(JSONObject().put("hours", hours).toString().toByteArray()) }
      if (c.responseCode !in 200..299) return@withContext null
      JSONObject(BufferedReader(InputStreamReader(c.inputStream)).use { it.readText() }).optBoolean("muted")
    } catch (_: Exception) {
      null
    } finally {
      c.disconnect()
    }
  }

  /** Members, admin and the viewer's mute. Null when it could not be read. */
  suspend fun info(token: String, conversationId: Long): ChatInfo? = withContext(Dispatchers.IO) {
    val c = conn("/api/dm/$conversationId/info", "GET", token)
    try {
      if (c.responseCode !in 200..299) return@withContext null
      val o = JSONObject(BufferedReader(InputStreamReader(c.inputStream)).use { it.readText() })
      val arr = o.optJSONArray("members") ?: org.json.JSONArray()
      ChatInfo(
        id = o.optLong("id"),
        isGroup = o.optBoolean("is_group", false),
        title = o.optString("title", "").cleanNull() ?: "Chat",
        avatar = ApiConfig.mediaUrl(o.optString("avatar", null).cleanNull()),
        muted = o.optBoolean("muted", false),
        mutedUntil = o.optString("muted_until", null).cleanNull(),
        isAdmin = o.optBoolean("is_admin", false),
        maxMembers = o.optInt("max_members", 50),
        members = buildList {
          for (i in 0 until arr.length()) {
            val m = arr.optJSONObject(i) ?: continue
            val pid = m.optString("player_id", null).cleanNull() ?: continue
            add(
              ChatMember(
                playerId = pid,
                name = m.optString("name", "").ifBlank { "Player" },
                username = m.optString("username", null).cleanNull(),
                avatar = ApiConfig.mediaUrl(m.optString("avatar", null).cleanNull()),
                isAdmin = m.optBoolean("is_admin", false),
                isMe = m.optBoolean("is_me", false),
              )
            )
          }
        },
      )
    } catch (_: Exception) {
      null
    } finally {
      c.disconnect()
    }
  }

  suspend fun renameGroup(token: String, conversationId: Long, title: String): Outcome = withContext(Dispatchers.IO) {
    val c = conn("/api/dm/$conversationId/rename", "POST", token)
    try {
      c.outputStream.use { it.write(JSONObject().put("title", title.trim()).toString().toByteArray()) }
      outcomeOf(c)
    } catch (_: Exception) {
      Outcome.Failed
    } finally {
      c.disconnect()
    }
  }

  suspend fun addMembers(token: String, conversationId: Long, playerIds: List<String>): Outcome = withContext(Dispatchers.IO) {
    val c = conn("/api/dm/$conversationId/members", "POST", token)
    try {
      c.outputStream.use {
        it.write(JSONObject().put("members", org.json.JSONArray(playerIds)).toString().toByteArray())
      }
      outcomeOf(c)
    } catch (_: Exception) {
      Outcome.Failed
    } finally {
      c.disconnect()
    }
  }

  suspend fun removeMember(token: String, conversationId: Long, playerId: String): Outcome = withContext(Dispatchers.IO) {
    val encoded = URLEncoder.encode(playerId, "UTF-8")
    val c = conn("/api/dm/$conversationId/members/$encoded", "DELETE", token)
    try {
      outcomeOf(c)
    } catch (_: Exception) {
      Outcome.Failed
    } finally {
      c.disconnect()
    }
  }

  /** Change a group's photo. Returns the new absolute URL, or null on failure. */
  suspend fun setGroupPhoto(token: String, conversationId: Long, jpeg: ByteArray): String? = withContext(Dispatchers.IO) {
    var c: HttpURLConnection? = null
    try {
      c = multipart("/api/dm/$conversationId/avatar", token, jpeg, emptyMap())
      if (c.responseCode !in 200..299) return@withContext null
      ApiConfig.mediaUrl(
        JSONObject(BufferedReader(InputStreamReader(c.inputStream)).use { it.readText() })
          .optString("avatar", null).cleanNull()
      )
    } catch (_: Exception) {
      null
    } finally {
      c?.disconnect()
    }
  }

  private fun String?.cleanNull(): String? =
    this?.takeIf { it.isNotBlank() && it != "null" }
}
