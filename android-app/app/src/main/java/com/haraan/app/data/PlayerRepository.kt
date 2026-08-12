package com.haraan.app.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URLEncoder
import java.net.URL

/** A registered player resolved from a Player ID or username. */
data class PlayerLite(
  val playerId: String,
  val name: String,
  val district: String?,
  /** The @handle, when they've chosen one. Accounts created before usernames have none. */
  val username: String? = null,
  val avatar: String? = null,
)

/**
 * A player as they appear in social discovery — richer than [PlayerLite] because a
 * search result that is only a name reads dead. Kept as its own type so the squad
 * pickers that consume [PlayerLite] are untouched by the social surface.
 */
data class DiscoveredPlayer(
  val playerId: String,
  val name: String,
  val username: String?,
  val avatar: String?,
  val district: String?,
  val state: String?,
  val primarySport: String?,
  val matches: Int,
  val xp: Int,
  /** Null when nobody is signed in — the button renders as "Sign in to follow". */
  val isFollowing: Boolean?,
) {
  /** "@virat" when they have a handle, otherwise their HRN id. */
  val handleOrId: String get() = username?.let { "@$it" } ?: playerId

  /** "YSR Kadapa · 12 matches" — whichever halves actually exist. */
  val subtitle: String
    get() = listOfNotNull(
      district?.takeIf { it.isNotBlank() },
      matches.takeIf { it > 0 }?.let { "$it ${if (it == 1) "match" else "matches"}" },
    ).joinToString(" · ")
}

/**
 * Outcome of a directory search.
 *
 * `Unauthorized` exists because collapsing it into an empty list is a lie: the app
 * would tell someone "No players for hari" when the truth is their session expired.
 * A stored token can be present and still be rejected, so "we have a token" is not
 * the same as "we are signed in" — cf. the guest-token gate.
 */
sealed interface DiscoveryOutcome {
  data class Success(val players: List<DiscoveredPlayer>) : DiscoveryOutcome
  data object Unauthorized : DiscoveryOutcome
  data object Failed : DiscoveryOutcome
}

/**
 * One photo on a player's profile grid.
 *
 * [image] is the server's root-relative path ("/storage/posts/x.jpg"), exactly the shape
 * avatars come back in — run it through [ApiConfig.mediaUrl] before handing it to a loader.
 *
 * [mine] is the SERVER's answer to "may this viewer delete it", not a local comparison of
 * ids. The grid is public, so it renders for guests too; only the owner gets the delete
 * affordance, and only the owner's DELETE is honoured.
 */
data class PlayerPost(
  val id: Long,
  val image: String,
  val caption: String?,
  val createdAt: String?,
  val mine: Boolean,
)

/** One post in the Instagram-style Home feed: the photo plus its author and like state. */
data class FeedPost(
  val id: Long,
  val image: String,
  /** All carousel images in order (at least [image]); size > 1 renders a swipeable carousel. */
  val images: List<String>,
  val caption: String?,
  val createdAt: String?,
  val likeCount: Int,
  val liked: Boolean,
  val commentCount: Int,
  val saved: Boolean,
  val mine: Boolean,
  val authorPlayerId: String?,
  val authorName: String,
  val authorUsername: String?,
  val authorAvatar: String?,
)

/** One comment on a post. */
data class FeedComment(
  val id: Long,
  val body: String,
  val createdAt: String?,
  val authorPlayerId: String?,
  val authorName: String,
  val authorUsername: String?,
  val authorAvatar: String?,
)

/** One bubble in the Home feed's stories strip — a recent public poster. */
data class FeedStory(
  val playerId: String?,
  val name: String,
  val username: String?,
  val avatar: String?,
  val image: String?,
)

/** The Home feed payload: a stories strip on top and the vertical post feed below. */
data class HomeFeed(
  val stories: List<FeedStory>,
  val posts: List<FeedPost>,
)

/** One member of a team squad. Registered players have a Player ID; guests have a name only. */
data class SquadMember(
  val id: String,
  val name: String,
  val isGuest: Boolean = false,
  val isCaptain: Boolean = false,
  val isViceCaptain: Boolean = false,
)

/**
 * Player directory lookups. Mirrors [MatchRepository]'s HttpURLConnection + JWT style.
 */
class PlayerRepository(
  private val baseUrl: String = ApiConfig.BASE_URL,
) {
  /**
   * Resolve a registered player by Player ID. Returns null when not found (404)
   * or on any error, so callers can treat it as "not a valid player".
   */
  suspend fun lookup(token: String, playerId: String): PlayerLite? = withContext(Dispatchers.IO) {
    val trimmed = playerId.trim()
    if (trimmed.isEmpty()) return@withContext null

    val encoded = URLEncoder.encode(trimmed, "UTF-8")
    val connection = (URL("${baseUrl.trimEnd('/')}/api/players/lookup?playerId=$encoded").openConnection() as HttpURLConnection).apply {
      requestMethod = "GET"
      connectTimeout = 10000
      readTimeout = 10000
      setRequestProperty("Accept", "application/json")
      setRequestProperty("Authorization", "Bearer $token")
    }

    try {
      val code = connection.responseCode
      if (code !in 200..299) return@withContext null
      val stream = connection.inputStream ?: return@withContext null
      val body = BufferedReader(InputStreamReader(stream)).use { it.readText() }
      parsePlayer(JSONObject(body), fallbackId = trimmed)
    } catch (_: Exception) {
      null
    } finally {
      connection.disconnect()
    }
  }

  /**
   * Find players to add to a squad, by @username or name (an exact Player ID also works).
   *
   * This exists because the old flow demanded a teammate's Player ID (HRN-000123) typed
   * from memory — which meant in practice you could only build a squad with people
   * standing next to you. Returns empty for anything under 2 characters, and on any
   * failure, so the picker just shows nothing rather than an error mid-typing.
   */
  suspend fun search(token: String, query: String): List<PlayerLite> = withContext(Dispatchers.IO) {
    val q = query.trim()
    if (q.length < 2) return@withContext emptyList()

    val encoded = URLEncoder.encode(q, "UTF-8")
    val connection = (URL("${baseUrl.trimEnd('/')}/api/players/find?q=$encoded").openConnection() as HttpURLConnection).apply {
      requestMethod = "GET"
      connectTimeout = 10000
      readTimeout = 10000
      setRequestProperty("Accept", "application/json")
      setRequestProperty("Authorization", "Bearer $token")
    }

    try {
      val code = connection.responseCode
      if (code !in 200..299) return@withContext emptyList()
      val stream = connection.inputStream ?: return@withContext emptyList()
      val body = BufferedReader(InputStreamReader(stream)).use { it.readText() }
      val arr = JSONObject(body).optJSONArray("results") ?: return@withContext emptyList()
      buildList {
        for (i in 0 until arr.length()) {
          parsePlayer(arr.getJSONObject(i), fallbackId = "")?.let { add(it) }
        }
      }
    } catch (_: Exception) {
      emptyList()
    } finally {
      connection.disconnect()
    }
  }

  /**
   * Social search — the same `/api/players/find` endpoint the squad picker uses,
   * but parsed into the richer [DiscoveredPlayer] shape (follow state, matches, XP).
   *
   * The server resolves follow state for the whole page in one query, so a 20-result
   * search costs one round trip rather than twenty.
   */
  suspend fun discover(token: String, query: String): DiscoveryOutcome = withContext(Dispatchers.IO) {
    val q = query.trim()
    if (q.length < 2) return@withContext DiscoveryOutcome.Success(emptyList())

    val encoded = URLEncoder.encode(q, "UTF-8")
    val connection = (URL("${baseUrl.trimEnd('/')}/api/players/find?q=$encoded").openConnection() as HttpURLConnection).apply {
      requestMethod = "GET"
      connectTimeout = 10000
      readTimeout = 10000
      setRequestProperty("Accept", "application/json")
      setRequestProperty("Authorization", "Bearer $token")
    }

    try {
      val code = connection.responseCode
      // A stored token can be expired or issued against another environment. Say so
      // rather than reporting an empty directory.
      if (code == 401 || code == 403) return@withContext DiscoveryOutcome.Unauthorized
      if (code !in 200..299) return@withContext DiscoveryOutcome.Failed

      val body = BufferedReader(InputStreamReader(connection.inputStream)).use { it.readText() }
      val arr = JSONObject(body).optJSONArray("results")
        ?: return@withContext DiscoveryOutcome.Success(emptyList())

      DiscoveryOutcome.Success(buildList {
        for (i in 0 until arr.length()) parseDiscovered(arr.getJSONObject(i))?.let { add(it) }
      })
    } catch (_: Exception) {
      DiscoveryOutcome.Failed
    } finally {
      connection.disconnect()
    }
  }

  /**
   * Who follows [playerId], or who they follow — `relation` is "followers" or
   * "following".
   *
   * Reuses [DiscoveryOutcome] and [parseDiscovered] because the server returns the
   * same player-card shape here as it does for search, so a follower row and a search
   * row are the same object and can share one row composable.
   */
  suspend fun followList(token: String?, playerId: String, relation: String): DiscoveryOutcome =
    withContext(Dispatchers.IO) {
      val encoded = URLEncoder.encode(playerId.trim(), "UTF-8")
      val connection = (URL("${baseUrl.trimEnd('/')}/api/players/$encoded/$relation").openConnection() as HttpURLConnection).apply {
        requestMethod = "GET"
        connectTimeout = 10000
        readTimeout = 10000
        setRequestProperty("Accept", "application/json")
        if (!token.isNullOrBlank()) setRequestProperty("Authorization", "Bearer $token")
      }

      try {
        val code = connection.responseCode
        if (code == 401 || code == 403) return@withContext DiscoveryOutcome.Unauthorized
        if (code !in 200..299) return@withContext DiscoveryOutcome.Failed

        val body = BufferedReader(InputStreamReader(connection.inputStream)).use { it.readText() }
        val arr = JSONObject(body).optJSONArray("results")
          ?: return@withContext DiscoveryOutcome.Success(emptyList())

        DiscoveryOutcome.Success(buildList {
          for (i in 0 until arr.length()) parseDiscovered(arr.getJSONObject(i))?.let { add(it) }
        })
      } catch (_: Exception) {
        DiscoveryOutcome.Failed
      } finally {
        connection.disconnect()
      }
    }

  /**
   * Follow or unfollow, returning the state the SERVER settled on rather than what
   * we optimistically assumed. Null means the call failed and the caller should roll
   * its optimistic toggle back — silently leaving a filled "Following" button on a
   * request that never landed is the worst outcome here.
   *
   * POST for both directions: Android's HttpURLConnection has no dependable
   * DELETE path, which is why the API exposes a POST `/unfollow` twin.
   */
  suspend fun setFollowing(token: String, playerId: String, follow: Boolean): Boolean? =
    withContext(Dispatchers.IO) {
      val path = if (follow) "follow" else "unfollow"
      val encoded = URLEncoder.encode(playerId.trim(), "UTF-8")
      val connection = (URL("${baseUrl.trimEnd('/')}/api/players/$encoded/$path").openConnection() as HttpURLConnection).apply {
        requestMethod = "POST"
        connectTimeout = 10000
        readTimeout = 10000
        doOutput = true
        setRequestProperty("Accept", "application/json")
        setRequestProperty("Content-Type", "application/json")
        setRequestProperty("Authorization", "Bearer $token")
      }

      try {
        connection.outputStream.use { it.write("{}".toByteArray()) }
        if (connection.responseCode !in 200..299) return@withContext null
        val body = BufferedReader(InputStreamReader(connection.inputStream)).use { it.readText() }
        JSONObject(body).optBoolean("is_following", follow)
      } catch (_: Exception) {
        null
      } finally {
        connection.disconnect()
      }
    }

  /**
   * The photo grid on [playerId]'s profile, newest first.
   *
   * Token is optional because the grid is public — a guest opening a shared profile still
   * sees the photos. Passing it when we have one is what makes `mine` true on your own
   * profile, so don't drop it. Null means the call failed; an empty list means "no posts
   * yet", and the two must stay distinguishable or a network blip renders as an empty
   * state that invites you to post something you already posted.
   */
  suspend fun posts(token: String?, playerId: String): List<PlayerPost>? = withContext(Dispatchers.IO) {
    val encoded = URLEncoder.encode(playerId.trim(), "UTF-8")
    val connection = (URL("${baseUrl.trimEnd('/')}/api/players/$encoded/posts").openConnection() as HttpURLConnection).apply {
      requestMethod = "GET"
      connectTimeout = 10000
      readTimeout = 10000
      setRequestProperty("Accept", "application/json")
      if (!token.isNullOrBlank()) setRequestProperty("Authorization", "Bearer $token")
    }

    try {
      if (connection.responseCode !in 200..299) return@withContext null
      val body = BufferedReader(InputStreamReader(connection.inputStream)).use { it.readText() }
      val arr = JSONObject(body).optJSONArray("results") ?: return@withContext emptyList()
      buildList {
        for (i in 0 until arr.length()) parsePost(arr.getJSONObject(i))?.let { add(it) }
      }
    } catch (_: Exception) {
      null
    } finally {
      connection.disconnect()
    }
  }

  /**
   * Add a photo to your OWN grid — the server takes the poster from the token, so there is
   * no player id to pass and no way to post onto someone else's profile.
   *
   * Multipart by hand, mirroring [ProfileRepository.uploadAvatar]; the field name `image`
   * has to match the controller's validation key. Returns the created post so the caller
   * can prepend it without a refetch, or null on failure.
   */
  suspend fun uploadPost(
    token: String,
    imageBytes: ByteArray,
    mimeType: String,
    caption: String? = null,
  ): PlayerPost? = withContext(Dispatchers.IO) {
    val boundary = "----HaraanBoundary${System.currentTimeMillis()}"
    val connection = (URL("${baseUrl.trimEnd('/')}/api/players/posts").openConnection() as HttpURLConnection).apply {
      requestMethod = "POST"
      doOutput = true
      connectTimeout = 30000
      readTimeout = 30000
      setRequestProperty("Content-Type", "multipart/form-data; boundary=$boundary")
      setRequestProperty("Accept", "application/json")
      setRequestProperty("Authorization", "Bearer $token")
    }
    val ext = when (mimeType.lowercase()) {
      "image/png" -> "png"
      "image/webp" -> "webp"
      else -> "jpg"
    }
    val lineEnd = "\r\n"
    val dashes = "--"

    try {
      connection.outputStream.use { out ->
        out.write("$dashes$boundary$lineEnd".toByteArray())
        out.write("Content-Disposition: form-data; name=\"image\"; filename=\"post.$ext\"$lineEnd".toByteArray())
        out.write("Content-Type: $mimeType$lineEnd$lineEnd".toByteArray())
        out.write(imageBytes)
        out.write(lineEnd.toByteArray())
        if (!caption.isNullOrBlank()) {
          out.write("$dashes$boundary$lineEnd".toByteArray())
          out.write("Content-Disposition: form-data; name=\"caption\"$lineEnd$lineEnd".toByteArray())
          out.write(caption.trim().toByteArray(Charsets.UTF_8))
          out.write(lineEnd.toByteArray())
        }
        out.write("$dashes$boundary$dashes$lineEnd".toByteArray())
      }
      if (connection.responseCode !in 200..299) return@withContext null
      val body = BufferedReader(InputStreamReader(connection.inputStream)).use { it.readText() }
      parsePost(JSONObject(body))
    } catch (_: Exception) {
      null
    } finally {
      connection.disconnect()
    }
  }

  /**
   * Upload a carousel post: one or more JPEG images as `images[]`, plus an optional caption.
   * The server stores them in order (first = cover). Returns the created post (cover) or null.
   */
  suspend fun uploadPost(
    token: String,
    images: List<ByteArray>,
    caption: String? = null,
  ): PlayerPost? = withContext(Dispatchers.IO) {
    if (images.isEmpty()) return@withContext null
    val boundary = "----HaraanBoundary${System.currentTimeMillis()}"
    val connection = (URL("${baseUrl.trimEnd('/')}/api/players/posts").openConnection() as HttpURLConnection).apply {
      requestMethod = "POST"
      doOutput = true
      connectTimeout = 30000
      readTimeout = 60000
      setRequestProperty("Content-Type", "multipart/form-data; boundary=$boundary")
      setRequestProperty("Accept", "application/json")
      setRequestProperty("Authorization", "Bearer $token")
    }
    val lineEnd = "\r\n"
    val dashes = "--"
    try {
      connection.outputStream.use { out ->
        images.forEachIndexed { i, bytes ->
          out.write("$dashes$boundary$lineEnd".toByteArray())
          // Laravel reads a repeated `images[]` field as a file array.
          out.write("Content-Disposition: form-data; name=\"images[]\"; filename=\"post$i.jpg\"$lineEnd".toByteArray())
          out.write("Content-Type: image/jpeg$lineEnd$lineEnd".toByteArray())
          out.write(bytes)
          out.write(lineEnd.toByteArray())
        }
        if (!caption.isNullOrBlank()) {
          out.write("$dashes$boundary$lineEnd".toByteArray())
          out.write("Content-Disposition: form-data; name=\"caption\"$lineEnd$lineEnd".toByteArray())
          out.write(caption.trim().toByteArray(Charsets.UTF_8))
          out.write(lineEnd.toByteArray())
        }
        out.write("$dashes$boundary$dashes$lineEnd".toByteArray())
      }
      if (connection.responseCode !in 200..299) return@withContext null
      val body = BufferedReader(InputStreamReader(connection.inputStream)).use { it.readText() }
      parsePost(JSONObject(body))
    } catch (_: Exception) {
      null
    } finally {
      connection.disconnect()
    }
  }

  /**
   * Delete one of your own posts. The server re-checks ownership, so a wrong id fails
   * there rather than deleting someone else's photo.
   *
   * Hits the POST twin `/posts/{id}/delete`, not the DELETE verb, for the same reason
   * [setFollowing] uses a POST `/unfollow`: HttpURLConnection has no dependable
   * DELETE-with-body path. (A method-override header would not help — Laravel only honours
   * overrides via a `_method` form field, and only once explicitly enabled.)
   */
  suspend fun deletePost(token: String, postId: Long): Boolean = withContext(Dispatchers.IO) {
    val connection = (URL("${baseUrl.trimEnd('/')}/api/players/posts/$postId/delete").openConnection() as HttpURLConnection).apply {
      requestMethod = "POST"
      doOutput = true
      connectTimeout = 10000
      readTimeout = 10000
      setRequestProperty("Accept", "application/json")
      setRequestProperty("Content-Type", "application/json")
      setRequestProperty("Authorization", "Bearer $token")
    }
    try {
      connection.outputStream.use { it.write("{}".toByteArray()) }
      connection.responseCode in 200..299
    } catch (_: Exception) {
      false
    } finally {
      connection.disconnect()
    }
  }

  /**
   * The Instagram-style Home feed: recent posts from public accounts + a stories strip.
   * Optional auth — a token makes `liked`/`mine` accurate; without one the feed still loads.
   * Null means the call failed; an empty feed is a real, distinguishable state.
   */
  suspend fun homeFeed(token: String?): HomeFeed? = withContext(Dispatchers.IO) {
    val connection = (URL("${baseUrl.trimEnd('/')}/api/posts/feed").openConnection() as HttpURLConnection).apply {
      requestMethod = "GET"
      connectTimeout = 10000
      readTimeout = 10000
      setRequestProperty("Accept", "application/json")
      if (!token.isNullOrBlank()) setRequestProperty("Authorization", "Bearer $token")
    }
    try {
      if (connection.responseCode !in 200..299) return@withContext null
      val body = BufferedReader(InputStreamReader(connection.inputStream)).use { it.readText() }
      val root = JSONObject(body)
      val storyArr = root.optJSONArray("stories")
      val postArr = root.optJSONArray("posts")
      val stories = buildList {
        if (storyArr != null) for (i in 0 until storyArr.length()) parseStory(storyArr.getJSONObject(i))?.let { add(it) }
      }
      val posts = buildList {
        if (postArr != null) for (i in 0 until postArr.length()) parseFeedPost(postArr.getJSONObject(i))?.let { add(it) }
      }
      HomeFeed(stories = stories, posts = posts)
    } catch (_: Exception) {
      null
    } finally {
      connection.disconnect()
    }
  }

  /**
   * Like or unlike a post. Hits the POST twins (`/like`, `/unlike`) rather than the DELETE
   * verb, same reason [deletePost] does. Returns the server-authoritative (liked, count) so
   * a double-tap on a slow line settles the heart from the server, not a local guess; null
   * on failure so the caller can roll the optimistic toggle back.
   */
  suspend fun setLike(token: String, postId: Long, liked: Boolean): Pair<Boolean, Int>? = withContext(Dispatchers.IO) {
    val path = if (liked) "like" else "unlike"
    val connection = (URL("${baseUrl.trimEnd('/')}/api/players/posts/$postId/$path").openConnection() as HttpURLConnection).apply {
      requestMethod = "POST"
      doOutput = true
      connectTimeout = 10000
      readTimeout = 10000
      setRequestProperty("Accept", "application/json")
      setRequestProperty("Content-Type", "application/json")
      setRequestProperty("Authorization", "Bearer $token")
    }
    try {
      connection.outputStream.use { it.write("{}".toByteArray()) }
      if (connection.responseCode !in 200..299) return@withContext null
      val body = BufferedReader(InputStreamReader(connection.inputStream)).use { it.readText() }
      val root = JSONObject(body)
      root.optBoolean("liked", liked) to root.optInt("like_count", 0)
    } catch (_: Exception) {
      null
    } finally {
      connection.disconnect()
    }
  }

  /** Edit your own post's caption. Returns true on success. */
  suspend fun updateCaption(token: String, postId: Long, caption: String?): Boolean = withContext(Dispatchers.IO) {
    val payload = JSONObject().put("caption", caption?.trim() ?: "")
    val connection = (URL("${baseUrl.trimEnd('/')}/api/players/posts/$postId/caption").openConnection() as HttpURLConnection).apply {
      requestMethod = "POST"
      doOutput = true
      connectTimeout = 10000
      readTimeout = 10000
      setRequestProperty("Accept", "application/json")
      setRequestProperty("Content-Type", "application/json")
      setRequestProperty("Authorization", "Bearer $token")
    }
    try {
      connection.outputStream.use { it.write(payload.toString().toByteArray(Charsets.UTF_8)) }
      connection.responseCode in 200..299
    } catch (_: Exception) {
      false
    } finally {
      connection.disconnect()
    }
  }

  /** Save / unsave (bookmark) a post. Returns true on success. Uses the POST twins. */
  suspend fun setSave(token: String, postId: Long, saved: Boolean): Boolean = withContext(Dispatchers.IO) {
    val path = if (saved) "save" else "unsave"
    val connection = (URL("${baseUrl.trimEnd('/')}/api/players/posts/$postId/$path").openConnection() as HttpURLConnection).apply {
      requestMethod = "POST"
      doOutput = true
      connectTimeout = 10000
      readTimeout = 10000
      setRequestProperty("Accept", "application/json")
      setRequestProperty("Content-Type", "application/json")
      setRequestProperty("Authorization", "Bearer $token")
    }
    try {
      connection.outputStream.use { it.write("{}".toByteArray()) }
      connection.responseCode in 200..299
    } catch (_: Exception) {
      false
    } finally {
      connection.disconnect()
    }
  }

  /** A post's comment thread (oldest first). Null on failure. */
  suspend fun postComments(token: String?, postId: Long): List<FeedComment>? = withContext(Dispatchers.IO) {
    val connection = (URL("${baseUrl.trimEnd('/')}/api/posts/$postId/comments").openConnection() as HttpURLConnection).apply {
      requestMethod = "GET"
      connectTimeout = 10000
      readTimeout = 10000
      setRequestProperty("Accept", "application/json")
      if (!token.isNullOrBlank()) setRequestProperty("Authorization", "Bearer $token")
    }
    try {
      if (connection.responseCode !in 200..299) return@withContext null
      val body = BufferedReader(InputStreamReader(connection.inputStream)).use { it.readText() }
      val arr = JSONObject(body).optJSONArray("results") ?: return@withContext emptyList()
      buildList {
        for (i in 0 until arr.length()) parseComment(arr.getJSONObject(i))?.let { add(it) }
      }
    } catch (_: Exception) {
      null
    } finally {
      connection.disconnect()
    }
  }

  /** Post a comment. Returns the created comment (prepend/append without a refetch), or null. */
  suspend fun addComment(token: String, postId: Long, body: String): FeedComment? = withContext(Dispatchers.IO) {
    val payload = JSONObject().put("body", body.trim())
    val connection = (URL("${baseUrl.trimEnd('/')}/api/players/posts/$postId/comments").openConnection() as HttpURLConnection).apply {
      requestMethod = "POST"
      doOutput = true
      connectTimeout = 10000
      readTimeout = 10000
      setRequestProperty("Accept", "application/json")
      setRequestProperty("Content-Type", "application/json")
      setRequestProperty("Authorization", "Bearer $token")
    }
    try {
      connection.outputStream.use { it.write(payload.toString().toByteArray(Charsets.UTF_8)) }
      if (connection.responseCode !in 200..299) return@withContext null
      val respBody = BufferedReader(InputStreamReader(connection.inputStream)).use { it.readText() }
      parseComment(JSONObject(respBody))
    } catch (_: Exception) {
      null
    } finally {
      connection.disconnect()
    }
  }

  private fun parseComment(json: JSONObject): FeedComment? {
    val id = json.optLong("id", 0L).takeIf { it > 0L } ?: return null
    val body = json.optString("body", "").clean() ?: return null
    val author = json.optJSONObject("author")
    return FeedComment(
      id = id,
      body = body,
      createdAt = json.optString("created_at", null).clean(),
      authorPlayerId = author?.optString("player_id", null).clean(),
      authorName = author?.optString("name", null).clean() ?: "Player",
      authorUsername = author?.optString("username", null).clean(),
      authorAvatar = author?.optString("avatar", null).clean(),
    )
  }

  private fun parsePost(json: JSONObject): PlayerPost? {
    val id = json.optLong("id", 0L).takeIf { it > 0L } ?: return null
    val image = json.optString("image", "").clean() ?: return null
    return PlayerPost(
      id = id,
      image = image,
      caption = json.optString("caption", null).clean(),
      createdAt = json.optString("created_at", null).clean(),
      mine = json.optBoolean("mine", false),
    )
  }

  private fun parseFeedPost(json: JSONObject): FeedPost? {
    val id = json.optLong("id", 0L).takeIf { it > 0L } ?: return null
    val image = json.optString("image", "").clean() ?: return null
    val author = json.optJSONObject("author")
    val imagesArr = json.optJSONArray("images")
    val images = buildList {
      if (imagesArr != null) for (i in 0 until imagesArr.length()) imagesArr.optString(i, null).clean()?.let { add(it) }
    }.ifEmpty { listOf(image) }
    return FeedPost(
      id = id,
      image = image,
      images = images,
      caption = json.optString("caption", null).clean(),
      createdAt = json.optString("created_at", null).clean(),
      likeCount = json.optInt("like_count", 0),
      liked = json.optBoolean("liked", false),
      commentCount = json.optInt("comment_count", 0),
      saved = json.optBoolean("saved", false),
      mine = json.optBoolean("mine", false),
      authorPlayerId = author?.optString("player_id", null).clean(),
      authorName = author?.optString("name", null).clean() ?: "Player",
      authorUsername = author?.optString("username", null).clean(),
      authorAvatar = author?.optString("avatar", null).clean(),
    )
  }

  private fun parseStory(json: JSONObject): FeedStory? {
    val name = json.optString("name", null).clean() ?: "Player"
    return FeedStory(
      playerId = json.optString("player_id", null).clean(),
      name = name,
      username = json.optString("username", null).clean(),
      avatar = json.optString("avatar", null).clean(),
      image = json.optString("image", null).clean(),
    )
  }

  private fun parseDiscovered(json: JSONObject): DiscoveredPlayer? {
    val id = json.optString("player_id", "").takeIf { it.isNotBlank() } ?: return null
    return DiscoveredPlayer(
      playerId = id,
      name = json.optString("name", "").takeIf { it.isNotBlank() } ?: "Player",
      username = json.optString("username", null).clean(),
      avatar = json.optString("avatar", null).clean(),
      district = json.optString("district", null).clean(),
      state = json.optString("state", null).clean(),
      primarySport = json.optString("primary_sport", null).clean(),
      matches = json.optInt("matches", 0),
      xp = json.optInt("xp", 0),
      // `optBoolean` can't distinguish false from absent, so read the raw value.
      isFollowing = if (json.isNull("is_following")) null else json.optBoolean("is_following"),
    )
  }

  private fun parsePlayer(json: JSONObject, fallbackId: String): PlayerLite? {
    val id = json.optString("player_id", fallbackId).takeIf { it.isNotBlank() } ?: return null
    return PlayerLite(
      playerId = id,
      name = json.optString("name", ""),
      district = json.optString("district", null).clean(),
      username = json.optString("username", null).clean(),
      avatar = json.optString("avatar", null).clean(),
    )
  }

  private fun String?.clean(): String? = this?.takeIf { it.isNotBlank() && it != "null" }
}
